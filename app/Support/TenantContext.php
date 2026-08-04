<?php

namespace App\Support;

/**
 * Holds the "current tenant" for the lifetime of a request or a queued job.
 *
 * Resolved from the authenticated user in web requests (middleware), and set
 * explicitly from the job payload in capture workers (which have no auth user).
 * The global scope reads this to constrain every query.
 *
 * Registered as a singleton so it's shared across the container within one
 * request/job, and reset between queued jobs.
 */
class TenantContext
{
    private ?int $tenantId = null;
    private bool $bypassed = false;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->bypassed = false;
    }

    /**
     * Run a callback with tenant scoping disabled (super-admin/platform ops that
     * legitimately need to see across tenants). Restores state afterward.
     */
    public function withoutScope(callable $callback)
    {
        $prev = $this->bypassed;
        $this->bypassed = true;
        try {
            return $callback();
        } finally {
            $this->bypassed = $prev;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }
}
