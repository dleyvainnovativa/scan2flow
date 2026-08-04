<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\Area;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;

/**
 * The PLAN layer — tenant-wide entitlement ceiling, checked at resource CREATION.
 *
 * Distinct from the permission layer:
 *   - Plan  = "what may this CUSTOMER have?" (max templates/areas/users) — here.
 *   - Perms = "what may this USER do with what exists?" — your existing Policies.
 * They compose top-down: the plan gates creation of resources; permissions gate
 * access to resources that exist. You can't permission a user onto an area the
 * plan never let you create.
 *
 * NULL limit = unlimited. No plan on the tenant = unlimited (fail-open on plan
 * is intentional: a misconfigured tenant shouldn't be unable to work; the
 * super-admin assigns a plan in T-4).
 */
class PlanGate
{
    public function __construct(private TenantContext $context) {}

    /** Throw if the current tenant is at/over its limit for $resource. */
    public function ensureCanCreate(string $resource, ?int $tenantId = null): void
    {
        $tenantId ??= $this->context->id();
        if ($tenantId === null) {
            return; // no tenant context (platform op) — not gated here
        }

        $limit = $this->limit($resource, $tenantId);
        if ($limit === null) {
            return; // unlimited
        }

        if ($this->currentCount($resource, $tenantId) >= $limit) {
            throw new PlanLimitException($resource, $limit);
        }
    }

    /** How many more of $resource the tenant may create (null = unlimited). */
    public function remaining(string $resource, ?int $tenantId = null): ?int
    {
        $tenantId ??= $this->context->id();
        $limit = $this->limit($resource, $tenantId);
        if ($limit === null) {
            return null;
        }
        return max(0, $limit - $this->currentCount($resource, $tenantId));
    }

    private function limit(string $resource, int $tenantId): ?int
    {
        $tenant = Tenant::find($tenantId);
        $plan = $tenant?->plan; // may be null → unlimited (fail-open)
        return $plan ? $plan->limitFor($resource) : null;
    }

    /**
     * Count existing rows for the tenant. These models are tenant-scoped, but we
     * pass tenantId explicitly and bypass scope so this works even in platform
     * contexts. (withoutScope + explicit where keeps it correct either way.)
     */
    private function currentCount(string $resource, int $tenantId): int
    {
        return $this->context->withoutScope(function () use ($resource, $tenantId) {
            $model = match ($resource) {
                'templates' => Template::class,
                'areas'     => Area::class,
                'users'     => User::class,
                default     => null,
            };
            if (! $model) {
                return 0;
            }
            return $model::where('tenant_id', $tenantId)->count();
        });
    }
}
