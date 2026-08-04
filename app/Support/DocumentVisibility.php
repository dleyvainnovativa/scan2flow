<?php

namespace App\Support;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for QA status visibility.
 *
 * Rule: a user sees non-approved documents (pending/rejected) ONLY in areas
 * where they can edit or approve (or if admin). Everywhere else — and for
 * pure view-only users — only 'approved' documents are visible.
 *
 * ⚠️ IMPORTANT: this scope handles the STATUS dimension ONLY. It assumes the
 * query is ALREADY constrained to areas the user may view (e.g.
 * whereIn('area_id', $viewableAreaIds)). Applying it to an unconstrained
 * Document::query() would expose approved docs from areas the user can't access.
 * Always compose: area-view constraint FIRST, then this scope. Verified safe by
 * simulation across viewer/editor/approver/admin × approved/pending/rejected.
 *
 * Every place that lists/searches/streams documents MUST apply this, or
 * unapproved documents leak. Centralizing it here keeps the five touchpoints
 * (list, search metadata, search OCR, dashboard, file routes) consistent.
 */
class DocumentVisibility
{
    /**
     * Constrain a Document query to what $user is allowed to see by status.
     *
     * @param  Builder  $query  a query on the Document model (aliased as documents)
     */
    public static function scope(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query; // admins see all statuses
        }

        // Area ids where the user can edit or approve → may see all statuses.
        // $privilegedAreaIds = $user->areas()
        //     ->where(function ($q) {
        //         $q->wherePivot('can_edit', true)->orWherePivot('can_approve', true);
        //     })
        //     ->pluck('areas.id')
        //     ->all();
        $privilegedAreaIds = $user->areas()
            ->where(function (Builder $query) {
                $query->where('area_user.can_edit', true)
                    ->orWhere('area_user.can_approve', true);
            })
            ->pluck('areas.id')
            ->all();

        return $query->where(function (Builder $q) use ($privilegedAreaIds) {
            $q->where('documents.status', 'approved');
            if (! empty($privilegedAreaIds)) {
                // In privileged areas, also show pending/rejected.
                $q->orWhereIn('documents.area_id', $privilegedAreaIds);
            }
        });
    }

    /**
     * Per-document check (for routes that load a single document: stream,
     * download, show). True if $user may see this document given its status.
     */
    public static function canSee(User $user, $document): bool
    {
        if ($document->status === 'approved' || $user->isAdmin()) {
            return true;
        }
        // Non-approved: only if user can edit or approve that area.
        return $user->canOnArea($document->area, 'edit')
            || $user->canOnArea($document->area, 'approve');
    }

    /** Whether $user can approve/reject in the given area (and isn't the uploader). */
    public static function canApprove(User $user, Area $area): bool
    {
        return $user->canOnArea($area, 'approve');
    }
}
