<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\PageBalanceService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Area;
use Illuminate\Support\Str;

/**
 * Platform (super-admin) tenant management. Every query runs via
 * TenantContext::withoutScope() because platform operators live above tenancy.
 */
class TenantController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private PageBalanceService $balance,
        private \App\Services\FirebaseService $firebase,              // >>> ADD
        private \App\Services\TenantMigrationService $migration,
    ) {}

    public function index()
    {
        $tenants = $this->context->withoutScope(
            fn() =>
            Tenant::with('plan')
                ->withCount('users')
                ->orderBy('name')
                ->get()
        );

        $plans = Plan::where('is_active', true)->orderBy('name')->get();

        return view('platform.tenants.index', compact('tenants', 'plans'));
    }

    public function show(Tenant $tenant)
    {
        return $this->context->withoutScope(function () use ($tenant) {
            $tenant->load('plan');
            $usage = UsageEvent::where('tenant_id', $tenant->id)
                ->orderByDesc('occurred_at')
                ->limit(50)
                ->get();

            $counts = [
                'users'     => User::where('tenant_id', $tenant->id)->count(),
                'areas'     => DB::table('areas')->where('tenant_id', $tenant->id)->count(),
                'templates' => DB::table('templates')->where('tenant_id', $tenant->id)->count(),
                'documents' => DB::table('documents')->where('tenant_id', $tenant->id)->count(),
            ];

            return view('platform.tenants.show', compact('tenant', 'usage', 'counts'));
        });
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'plan_id'     => ['nullable', 'exists:plans,id'],
            // First admin for the new tenant:
            'admin_name'  => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $result = $this->context->withoutScope(function () use ($data) {
            $plan = $data['plan_id'] ? \App\Models\Plan::find($data['plan_id']) : null;

            // Guard: email not already used by a local user (any tenant).
            if (\App\Models\User::where('email', $data['admin_email'])->exists()) {
                abort(422, 'Ese correo ya está registrado.');
            }

            return DB::transaction(function () use ($data, $plan) {
                $tenant = \App\Models\Tenant::create([
                    'name'         => $data['name'],
                    'slug'         => $this->uniqueSlug($data['name']),
                    'plan_id'      => $plan?->id,
                    'page_balance' => $plan?->page_bundle ?? 0,
                    'status'       => 'active',
                ]);

                // Create the Firebase account first (outside is fine, but do it here
                // so a failure aborts the whole thing).
                $uid = $this->firebase->createUser(
                    $data['admin_email'],
                    $data['admin_password'],
                    $data['admin_name']
                );

                $user = \App\Models\User::create([
                    'firebase_uid' => $uid,
                    'name'         => $data['admin_name'],
                    'email'        => $data['admin_email'],
                    'role'         => 'admin',      // tenant admin
                    'is_active'    => true,
                    'tenant_id'    => $tenant->id,   // explicit — born into this tenant
                ]);

                return ['tenant' => $tenant, 'user' => $user];
            });
        });

        return response()->json([
            'message' => 'Tenant creado con su administrador.',
            'tenant'  => $result['tenant'],
        ], 201);
    }

    // ── 2. Reassign a USER to a different tenant (safe — carries nothing) ───────

    public function reassignUser(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'user_id'   => ['required', 'integer'],
            'to_tenant' => ['required', 'exists:tenants,id'],
        ]);

        $this->context->withoutScope(function () use ($data) {
            $user = \App\Models\User::findOrFail($data['user_id']);

            // Guard: don't move a platform admin into a tenant.
            if ($user->is_platform_admin) {
                abort(422, 'No puedes asignar un administrador de plataforma a un tenant.');
            }

            $user->update(['tenant_id' => $data['to_tenant']]);
        });

        return response()->json(['message' => 'Usuario reasignado.']);
    }

    // ── 3. Area migration — dry-run (preview) ──────────────────────────────────

    public function previewAreaMove(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'area_id'   => ['required', 'integer'],
            'to_tenant' => ['required', 'exists:tenants,id'],
        ]);

        $plan = $this->context->withoutScope(function () use ($data) {
            $area = \App\Models\Area::findOrFail($data['area_id']);
            $to   = \App\Models\Tenant::findOrFail($data['to_tenant']);
            return $this->migration->dryRun($area, $to);
        });

        return response()->json(['preview' => $plan]);
    }

    // ── 4. Area migration — commit (cascade + file move) ───────────────────────

    public function migrateArea(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'area_id'   => ['required', 'integer'],
            'to_tenant' => ['required', 'exists:tenants,id'],
            'confirm'   => ['required', 'accepted'], // must explicitly confirm
        ]);

        $result = $this->context->withoutScope(function () use ($data) {
            $area = \App\Models\Area::findOrFail($data['area_id']);
            $to   = \App\Models\Tenant::findOrFail($data['to_tenant']);
            return $this->migration->migrate($area, $to);
        });

        return response()->json([
            'message' => "Área migrada. {$result['files_moved']} archivos movidos.",
            'result'  => $result,
        ]);
    }

    public function assignPlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id'          => ['required', 'exists:plans,id'],
            'grant_page_bundle' => ['nullable', 'boolean'],
        ]);

        $this->context->withoutScope(function () use ($tenant, $data, $request) {
            $plan = Plan::findOrFail($data['plan_id']);
            $tenant->update(['plan_id' => $plan->id]);

            // Optionally credit the plan's page bundle on assignment.
            if ($request->boolean('grant_page_bundle') && $plan->page_bundle > 0) {
                $this->balance->credit(
                    $plan->page_bundle,
                    $tenant->id,
                    $request->user()->id,
                    "Asignación de plan {$plan->name}"
                );
            }
        });

        return response()->json(['message' => 'Plan asignado.']);
    }

    public function topUp(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'pages' => ['required', 'integer', 'min:1', 'max:10000000'],
            'note'  => ['nullable', 'string', 'max:255'],
        ]);

        $newBalance = $this->context->withoutScope(
            fn() =>
            $this->balance->credit(
                $data['pages'],
                $tenant->id,
                $request->user()->id,
                $data['note'] ?? 'Recarga manual'
            )
        );

        return response()->json([
            'message'     => "Se agregaron {$data['pages']} páginas.",
            'new_balance' => $newBalance,
        ]);
    }

    public function setStatus(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $this->context->withoutScope(fn() => $tenant->update(['status' => $data['status']]));

        return response()->json([
            'message' => $data['status'] === 'active' ? 'Tenant activado.' : 'Tenant suspendido.',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
