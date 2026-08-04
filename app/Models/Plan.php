<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'max_templates', 'max_areas', 'max_users',
        'page_bundle', 'price_cents', 'currency', 'is_active',
    ];

    protected $casts = [
        'max_templates' => 'integer',
        'max_areas'     => 'integer',
        'max_users'     => 'integer',
        'page_bundle'   => 'integer',
        'price_cents'   => 'integer',
        'is_active'     => 'boolean',
    ];

    // Plans are platform-level, not tenant-scoped.

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** NULL limit means unlimited. */
    public function limitFor(string $resource): ?int
    {
        return match ($resource) {
            'templates' => $this->max_templates,
            'areas'     => $this->max_areas,
            'users'     => $this->max_users,
            default     => 0,
        };
    }
}
