<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * User-centric permission editor: manage ONE user's access across ALL areas
 * (the inverse of the area-centric panel on areas/{id}).
 *
 * Individual checkbox toggles reuse the existing AreaController@setPermission
 * endpoint. This controller adds:
 *   - edit():  render the matrix (all areas + this user's current pivots)
 *   - bulk():  apply one flag change across many areas in a single request
 */
class UserPermissionController extends Controller
{
    /** Render the permission matrix for a user. */
    public function edit(Request $request, User $user)
    {
        // Admin-only (route is behind 'admin' middleware); belt-and-suspenders.
        abort_unless($request->user()->isAdmin(), 403);

        // All areas in the current tenant (Area is tenant-scoped).
        $areas = Area::orderBy('name')->get();

        // This user's current per-area flags, keyed by area_id.
        $pivots = $user->areas()->get()->keyBy('id')->map(fn ($a) => [
            'can_view'     => (bool) $a->pivot->can_view,
            'can_download' => (bool) $a->pivot->can_download,
            'can_edit'     => (bool) $a->pivot->can_edit,
            'can_approve'  => (bool) $a->pivot->can_approve,
        ]);

        return view('users.permissions', compact('user', 'areas', 'pivots'));
    }

    /**
     * Bulk apply: set ONE flag to a value across many areas for this user.
     * Body: { flag: 'can_view'|'can_download'|'can_edit'|'can_approve',
     *         value: bool, area_ids: int[] }
     *
     * Runs the same imply-view + detach-when-empty rules as setPermission, once
     * per area, inside a transaction. Returns the resulting flags per area so
     * the UI can sync.
     */
    public function bulk(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'flag'       => ['required', 'in:can_view,can_download,can_edit,can_approve'],
            'value'      => ['required', 'boolean'],
            'area_ids'   => ['required', 'array'],
            'area_ids.*' => ['integer'],
        ]);

        // Constrain to areas in the current tenant (Area is scoped, so this
        // can't touch another tenant's areas even if ids were forged).
        $areas = Area::whereIn('id', $data['area_ids'])->get();

        $results = [];

        DB::transaction(function () use ($areas, $user, $data, &$results) {
            foreach ($areas as $area) {
                // Start from current flags so we only change the one flag.
                $current = $area->users()->where('users.id', $user->id)->first()?->pivot;
                $flags = [
                    'can_view'     => (bool) ($current->can_view ?? false),
                    'can_download' => (bool) ($current->can_download ?? false),
                    'can_edit'     => (bool) ($current->can_edit ?? false),
                    'can_approve'  => (bool) ($current->can_approve ?? false),
                ];

                $flags[$data['flag']] = $data['value'];

                $results[$area->id] = $this->applyFlags($area, $user->id, $flags);
            }
        });

        return response()->json([
            'message' => 'Permisos actualizados.',
            'results' => $results, // area_id => final flags (or null if detached)
        ]);
    }

    /**
     * Shared write: imply-view + detach-when-empty, returns final flags (or null
     * if detached). Mirrors AreaController@setPermission so behavior is identical.
     */
    private function applyFlags(Area $area, int $userId, array $flags): ?array
    {
        // Nothing granted → detach.
        if (! $flags['can_view'] && ! $flags['can_download'] && ! $flags['can_edit'] && ! $flags['can_approve']) {
            $area->users()->detach($userId);
            return null;
        }

        // download/edit/approve imply view.
        if ($flags['can_download'] || $flags['can_edit'] || $flags['can_approve']) {
            $flags['can_view'] = true;
        }

        $area->users()->syncWithoutDetaching([$userId => $flags]);

        return $flags;
    }
}
