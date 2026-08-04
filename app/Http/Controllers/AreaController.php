<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admins see all areas; members see only areas they can view.
        $areas = $user->isAdmin()
            ? Area::withCount(['templates', 'documents'])
            ->withSum('documents as pages_sum', 'page_count')
            ->orderBy('name')->get()
            : $user->areas()->where('can_view', true)
            ->withCount(['templates', 'documents'])
            ->withSum('documents as pages_sum', 'page_count')
            ->orderBy('name')->get();

        return view('areas.index', compact('areas'));
    }

    public function show(Request $request, Area $area)
    {
        // dd($request);
        $this->authorize('view', $area);
        $area->load(['templates.fields']);
        // Permission management panel data (admin only).
        $users = $request->user()->isAdmin()
            ? User::active()->forCurrentTenant()->orderBy('name')->get()
            : collect();
        $granted = $area->users()->pluck('users.id')->all();
        $pivots = $area->users()->get()->keyBy('id');
        $remainingTemplates = app(\App\Services\PlanGate::class)->remaining('templates');
        return view('areas.show', compact('area', 'users', 'granted', 'pivots', 'remainingTemplates'));
    }

    public function store(AreaRequest $request)
    {
        $this->authorize('create', Area::class);
        app(\App\Services\PlanGate::class)->ensureCanCreate('areas');
        $area = Area::create($request->validated());

        return response()->json(['message' => 'Área creada.', 'area' => $area], 201);
    }

    public function update(AreaRequest $request, Area $area)
    {
        $this->authorize('update', $area);

        $area->update($request->validated());

        return response()->json(['message' => 'Área actualizada.', 'area' => $area]);
    }

    public function destroy(Area $area)
    {
        $this->authorize('delete', $area);

        $area->delete();

        return response()->json(['message' => 'Área eliminada.']);
    }

    /** Grant/update/revoke a user's permissions on this area. */
    public function setPermission(Request $request, Area $area)
    {
        $this->authorize('managePermissions', $area);

        $data = $request->validate([
            'user_id'      => [
                'required',
                // Must be a user in the CURRENT tenant — prevents attaching a
                // foreign-tenant user by passing their id (User is unscoped).
                \Illuminate\Validation\Rule::exists('users', 'id')
                    ->where('tenant_id', app(\App\Support\TenantContext::class)->id()),
            ],
            'can_view'     => ['boolean'],
            'can_download' => ['boolean'],
            'can_edit'     => ['boolean'],
            'can_approve'  => ['boolean'],          // >>> ADD
        ]);

        // If nothing is granted, detach entirely.
        if (! $data['can_view'] && ! $data['can_download'] && ! $data['can_edit'] && ! ($data['can_approve'] ?? false)) {
            $area->users()->detach($data['user_id']);
            return response()->json(['message' => 'Permisos revocados.']);
        }

        // download/edit/approve imply view.
        if ($data['can_download'] || $data['can_edit'] || ($data['can_approve'] ?? false)) {
            $data['can_view'] = true;
        }

        $area->users()->syncWithoutDetaching([
            $data['user_id'] => [
                'can_view'     => $data['can_view'] ?? false,
                'can_download' => $data['can_download'] ?? false,
                'can_edit'     => $data['can_edit'] ?? false,
                'can_approve'  => $data['can_approve'] ?? false,   // >>> ADD
            ],
        ]);

        return response()->json(['message' => 'Permisos actualizados.']);
    }
}
