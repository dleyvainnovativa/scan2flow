<?php

namespace App\Jobs;

use App\Ingestion\IngestionService;
use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes one template's INPUT folder on a queue worker, so large/scanned
 * batches don't block a web request or a cron tick. Retries with backoff;
 * ingestion itself is idempotent, so a retry won't duplicate documents.
 */
class IngestTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $timeout;

    public function __construct(public int $templateId)
    {
        $this->tries   = (int) config('ingestion.tries', 3);
        $this->timeout = (int) config('ingestion.job_timeout', 900);
        $this->onQueue(config('ingestion.queue', 'ingestion'));
    }

    /** Per-attempt backoff (seconds). */
    public function backoff(): array
    {
        return config('ingestion.backoff', [30, 120, 300]);
    }

    public function handle(IngestionService $ingestion): void
    {
        $template = Template::find($this->templateId);
        if (! $template) {
            return; // template deleted since dispatch — nothing to do
        }

        $summary = $ingestion->ingestTemplate($template);

        Log::info('Ingestion job completed', [
            'template_id' => $template->id,
            'summary'     => $summary,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Ingestion job failed permanently', [
            'template_id' => $this->templateId,
            'error'       => $e->getMessage(),
        ]);
    }
}
