<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'metric', 'quantity', 'balance_after',
        'subject_type', 'subject_id', 'caused_by', 'note', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
