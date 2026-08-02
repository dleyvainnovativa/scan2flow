<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->canOnArea($document->area, 'view');
    }

    public function download(User $user, Document $document): bool
    {
        return $user->canOnArea($document->area, 'download');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->canOnArea($document->area, 'edit');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->canOnArea($document->area, 'edit');
    }
}
