<?php

namespace App\Services;

use App\Exceptions\InsufficientPagesException;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Manages a tenant's drawdown page balance and the usage_events audit trail.
 *
 * The debit is CONCURRENCY-SAFE: it uses a single conditional UPDATE
 *   UPDATE tenants SET page_balance = page_balance - N
 *   WHERE id = ? AND page_balance >= N
 * If that affects 0 rows, the balance was insufficient — no lock needed, and
 * two parallel capture workers can never both overdraw (the DB serializes the
 * conditional updates). This matters because capture runs as a parallel worker
 * pool.
 */
class PageBalanceService
{
    public function __construct(private TenantContext $context) {}

    /** Current balance for a tenant (defaults to the context tenant). */
    public function balance(?int $tenantId = null): int
    {
        $tenantId ??= $this->context->id();
        // Read the raw column, bypassing model scope concerns.
        return (int) DB::table('tenants')->where('id', $tenantId)->value('page_balance');
    }

    /** True if the tenant can cover `pages` (non-locking pre-check for UX). */
    public function canCover(int $pages, ?int $tenantId = null): bool
    {
        return $this->balance($tenantId) >= $pages;
    }

    /**
     * Atomically debit `pages` from the tenant's balance and log a usage_event.
     * Throws InsufficientPagesException if the balance can't cover it.
     *
     * Call this INSIDE the same DB transaction as document creation so a failure
     * rolls back the document too (no doc created without a paid page, no page
     * debited without a doc).
     *
     * @return int the balance remaining after the debit
     */
    public function debit(
        int $pages,
        ?int $tenantId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $causedBy = null,
        ?string $note = null,
    ): int {
        $tenantId ??= $this->context->id();
        if ($tenantId === null) {
            throw new \LogicException('No tenant in context for page debit.');
        }
        if ($pages <= 0) {
            return $this->balance($tenantId); // nothing to debit
        }

        // Atomic conditional decrement — the race-safe core.
        $affected = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('page_balance', '>=', $pages)
            ->update([
                'page_balance' => DB::raw('page_balance - ' . (int) $pages),
                'updated_at'   => now(),
            ]);

        if ($affected === 0) {
            // Either insufficient balance or tenant missing.
            throw new InsufficientPagesException($tenantId, $pages, $this->balance($tenantId));
        }

        $remaining = $this->balance($tenantId);

        UsageEvent::create([
            'tenant_id'     => $tenantId,
            'metric'        => 'pages_ingested',
            'quantity'      => -$pages,
            'balance_after' => $remaining,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'caused_by'     => $causedBy,
            'note'          => $note,
        ]);

        return $remaining;
    }

    /**
     * Credit (top up) a tenant's balance — used by super-admin top-ups (T-4).
     * @return int new balance
     */
    public function credit(int $pages, int $tenantId, ?int $causedBy = null, ?string $note = null): int
    {
        if ($pages <= 0) {
            return $this->balance($tenantId);
        }

        DB::table('tenants')->where('id', $tenantId)->update([
            'page_balance' => DB::raw('page_balance + ' . (int) $pages),
            'updated_at'   => now(),
        ]);

        $balance = $this->balance($tenantId);

        UsageEvent::create([
            'tenant_id'     => $tenantId,
            'metric'        => 'pages_topup',
            'quantity'      => $pages,
            'balance_after' => $balance,
            'caused_by'     => $causedBy,
            'note'          => $note ?? 'Recarga de páginas',
        ]);

        return $balance;
    }
}
