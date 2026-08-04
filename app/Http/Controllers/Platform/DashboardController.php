<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Platform (super-admin) dashboard — cross-tenant operator metrics.
 * Everything runs via TenantContext::withoutScope() because platform admins
 * operate above tenancy.
 */
class DashboardController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index()
    {
        return $this->context->withoutScope(function () {
            // ── Tenants ────────────────────────────────────────────────────
            $tenantCounts = [
                'total'     => Tenant::count(),
                'active'    => Tenant::where('status', 'active')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
            ];

            // ── Pages consumed (ingested) across ALL tenants ───────────────
            // Debits are stored as negative quantities; flip the sign.
            $pagesConsumed = (int) abs(
                UsageEvent::where('metric', 'pages_ingested')->sum('quantity')
            );

            // Pages sold/granted (top-ups) across all tenants.
            $pagesSold = (int) UsageEvent::where('metric', 'pages_topup')->sum('quantity');

            // Current outstanding balance held by all tenants (unused pages).
            $pagesOutstanding = (int) Tenant::sum('page_balance');

            // ── Revenue-ish: sum of plan prices for tenants on a paid plan ──
            // Approximate MRR proxy = Σ price_cents of each tenant's assigned plan.
            $revenueByCurrency = DB::table('tenants')
                ->join('plans', 'tenants.plan_id', '=', 'plans.id')
                ->where('tenants.status', 'active')
                ->groupBy('plans.currency')
                ->selectRaw('plans.currency as currency, SUM(plans.price_cents) as cents')
                ->get();

            // ── Recent tenant signups ──────────────────────────────────────
            $recentTenants = Tenant::with('plan')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            // ── Recent usage activity (any tenant) ─────────────────────────
            $recentActivity = UsageEvent::with('tenant')
                ->orderByDesc('occurred_at')
                ->limit(10)
                ->get();

            // ── Plan distribution (how many tenants on each plan) ──────────
            $planDistribution = DB::table('tenants')
                ->leftJoin('plans', 'tenants.plan_id', '=', 'plans.id')
                ->groupBy('plans.name')
                ->selectRaw('COALESCE(plans.name, "Sin plan") as plan_name, COUNT(*) as tenants')
                ->orderByDesc('tenants')
                ->get();

            return view('platform.dashboard', compact(
                'tenantCounts',
                'pagesConsumed',
                'pagesSold',
                'pagesOutstanding',
                'revenueByCurrency',
                'recentTenants',
                'recentActivity',
                'planDistribution'
            ));
        });
    }
}
