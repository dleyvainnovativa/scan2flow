<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records audit entries. Kept deliberately simple and fail-safe: logging must
 * never break the action it's recording, so writes are wrapped defensively.
 */
class AuditService
{
    /**
     * @param  string  $action   viewed|downloaded|edited|deleted|uploaded|login
     * @param  Model|null  $subject  the model the action targets (optional)
     * @param  string|null  $summary  human-readable description
     */
    public function log(string $action, ?Model $subject = null, ?string $summary = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => Auth::id(),
                'action'         => $action,
                'auditable_type' => $subject ? $subject::class : null,
                'auditable_id'   => $subject?->getKey(),
                'summary'        => $summary,
                'ip_address'     => Request::ip(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Swallow — an audit failure should not interrupt the user's action.
            report($e);
        }
    }
}
