<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function view(User $user, Template $template): bool
    {
        return $user->canOnArea($template->area, 'view');
    }

    /** Templates are structural config — admin only to create/edit/delete. */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Template $template): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Template $template): bool
    {
        return $user->isAdmin();
    }
}
