<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenantUnscoped;

class User extends Authenticatable implements AuthenticatableContract
{
    use BelongsToTenantUnscoped;
    use Notifiable;

    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'role',
        'is_active',
        'last_login_at',
        'tenant_id',
        'is_platform_admin'
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'is_platform_admin' => 'boolean'
    ];

    public function getAuthPassword(): string
    {
        return '';
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /* ---- Relationships ---------------------------------------------------- */

    /** Areas this user can access, with per-area permission flags on the pivot. */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class)
            ->withPivot(['can_view', 'can_download', 'can_edit', 'can_approve'])  // >>> ADD can_approve
            ->withTimestamps();
    }

    /* ---- Role / permission helpers --------------------------------------- */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve a permission for a given area. Admins always pass.
     * $ability is one of: view, download, edit.
     */
    public function canOnArea(Area $area, string $ability): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $pivot = $this->areas()->where('areas.id', $area->id)->first()?->pivot;
        if (! $pivot) {
            return false;
        }

        return match ($ability) {
            'view'     => (bool) ($pivot->can_view || $pivot->can_edit || $pivot->can_approve), // approve/edit imply view
            'download' => (bool) $pivot->can_download,
            'edit'     => (bool) $pivot->can_edit,
            'approve'  => (bool) $pivot->can_approve,   // >>> ADD
            default    => false,
        };
    }
}
