<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    /** Admins manage areas; members only view areas they're granted. */
    public function viewAny(User $user): bool
    {
        return true; // list is filtered per-user in the controller
    }

    public function view(User $user, Area $area): bool
    {
        return $user->canOnArea($area, 'view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }

    /** Manage which users can access the area (admin only). */
    public function managePermissions(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }
}
