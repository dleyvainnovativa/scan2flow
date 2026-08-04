<?php

namespace App\Jobs;

use App\Ingestion\IngestionService;
use App\Jobs\Concerns\TenantAwareJob;
use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes one template's INPUT folder on a queue worker.
 *
 * Tenant-aware (T-0/T-3): carries tenant_id, sets the tenant context for the
 * job body (so every query + document write is correctly scoped), and clears it
 * afterward. Idempotent, so retries won't duplicate documents.
 *
 * Basic per-tenant fairness: WithoutOverlapping keyed by tenant means one
 * tenant can't have two overlapping ingestion jobs for the same template
 * hammering at once; combine with a worker pool for cross-tenant parallelism.
 */
class IngestTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAwareJob;

    public int $tries;
    public int $timeout;
    public int $tenantId;

    public function __construct(public int $templateId, int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->tries    = (int) config('ingestion.tries', 3);
        $this->timeout  = (int) config('ingestion.job_timeout', 900);
        $this->onQueue(config('ingestion.queue', 'ingestion'));
    }

    /**
     * Per-tenant + per-template overlap guard. Keyed by tenant so tenants don't
     * block each other; the release() lets a queued duplicate retry shortly
     * rather than being dropped.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("ingest:{$this->tenantId}:{$this->templateId}"))
                ->releaseAfter(30)
                ->expireAfter(config('ingestion.job_timeout', 900) + 60),
        ];
    }

    public function backoff(): array
    {
        return config('ingestion.backoff', [30, 120, 300]);
    }

    public function handle(IngestionService $ingestion): void
    {
        // Set tenant context for the whole job body; cleared in finally so a
        // long-running worker never leaks this tenant into the next job.
        $this->withTenant(function () use ($ingestion) {
            // With tenant context set, the global scope means find() only
            // resolves a template owned by this tenant (extra safety).
            $template = Template::find($this->templateId);
            if (! $template) {
                Log::warning('Ingestion job: template not found in tenant', [
                    'template_id' => $this->templateId,
                    'tenant_id'   => $this->tenantId,
                ]);
                return;
            }

            $summary = $ingestion->ingestTemplate($template);

            Log::info('Ingestion job completed', [
                'tenant_id'   => $this->tenantId,
                'template_id' => $template->id,
                'summary'     => $summary,
            ]);
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Ingestion job failed permanently', [
            'tenant_id'   => $this->tenantId,
            'template_id' => $this->templateId,
            'error'       => $e->getMessage(),
        ]);
    }
}
