<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'plan_id',
        'page_balance',
        'isolation',
        'status',
    ];

    protected $casts = [
        'page_balance' => 'integer',
    ];

    // NOTE: Tenant itself is NOT tenant-scoped (it's the root). No BelongsToTenant.

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
