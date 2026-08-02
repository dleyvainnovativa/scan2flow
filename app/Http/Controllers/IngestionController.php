<?php

namespace App\Http\Controllers;

use App\Ingestion\IngestionService;
use App\Jobs\IngestTemplateJob;
use App\Models\IngestionRecord;
use App\Models\Template;
use Illuminate\Http\Request;

class IngestionController extends Controller
{
    public function __construct(private IngestionService $ingestion) {}

    public function index()
    {
        $templates = Template::with('area')
            ->whereNotNull('input_folder_path')
            ->orderBy('name')
            ->get();

        $records = IngestionRecord::with(['template', 'document'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Status counts for the dashboard cards.
        $counts = IngestionRecord::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $counts = [
            'done'    => (int) ($counts['done'] ?? 0),
            'failed'  => (int) ($counts['failed'] ?? 0),
            'pending' => (int) (($counts['pending'] ?? 0) + ($counts['processing'] ?? 0)),
            'skipped' => (int) ($counts['skipped'] ?? 0),
        ];

        $mode = config('ingestion.mode', 'sync');

        return view('ingestion.index', compact('templates', 'records', 'counts', 'mode'));
    }

    /** Run ingestion for a template — inline (sync) or dispatched (queue). */
    public function run(Request $request, Template $template)
    {
        abort_unless($template->input_folder_path, 422, 'La plantilla no tiene carpeta INPUT.');

        if (config('ingestion.mode', 'sync') === 'queue') {
            IngestTemplateJob::dispatch($template->id);

            return response()->json([
                'message' => 'Procesamiento en cola. Se ejecutará en segundo plano.',
                'queued'  => true,
            ]);
        }

        $summary = $this->ingestion->ingestTemplate($template);

        return response()->json([
            'message' => "Procesado: {$summary['created']} creados, {$summary['skipped']} omitidos, {$summary['failed']} fallidos.",
            'summary' => $summary,
            'queued'  => false,
        ]);
    }

    /** Re-queue / re-run just the failed records (clears their state to retry). */
    public function retryFailed(Request $request, Template $template)
    {
        // Delete failed records so the next run reprocesses those base names.
        IngestionRecord::where('template_id', $template->id)
            ->where('status', 'failed')
            ->delete();

        if (config('ingestion.mode', 'sync') === 'queue') {
            IngestTemplateJob::dispatch($template->id);
            return response()->json(['message' => 'Reintento en cola.', 'queued' => true]);
        }

        $summary = $this->ingestion->ingestTemplate($template);

        return response()->json([
            'message' => "Reintento: {$summary['created']} creados, {$summary['failed']} fallidos.",
            'summary' => $summary,
        ]);
    }
}
