<?php

namespace App\Jobs\Concerns;

use App\Support\TenantContext;

/**
 * For queued jobs that operate within a tenant. Jobs have no authenticated
 * user, so they must set the tenant context explicitly from their payload —
 * and CLEAR it afterward, because a long-running queue worker reuses the same
 * process (and the singleton TenantContext) across jobs. Without the reset,
 * tenant A's context would leak into tenant B's next job.
 *
 * Usage in a job:
 *   use TenantAwareJob;
 *   public int $tenantId;  // set in constructor
 *   public function handle(...) {
 *       $this->withTenant(function () { ...actual work... });
 *   }
 */
trait TenantAwareJob
{
    /**
     * Run the job body with the tenant context set, then always clear it.
     */
    protected function withTenant(callable $work)
    {
        $ctx = app(TenantContext::class);
        $previous = $ctx->id();

        $ctx->set($this->tenantId);
        try {
            return $work();
        } finally {
            // Restore prior state (usually null) so the next job starts clean.
            $ctx->set($previous);
        }
    }
}
