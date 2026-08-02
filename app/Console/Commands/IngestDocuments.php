<?php

namespace App\Console\Commands;

use App\Ingestion\IngestionService;
use App\Models\Template;
use Illuminate\Console\Command;

/**
 * Ingest documents from templates' INPUT folders.
 *
 *   php artisan documents:ingest              # all templates with an INPUT path
 *   php artisan documents:ingest --template=3 # a single template by id
 *
 * Intended for cron: * * * * * php artisan schedule:run (see console schedule),
 * or call directly.
 */
class IngestDocuments extends Command
{
    protected $signature = 'documents:ingest {--template= : Ingest only this template id}';

    protected $description = 'Procesa los PDFs/XMLs en las carpetas INPUT de las plantillas';

    public function handle(IngestionService $ingestion): int
    {
        $templates = $this->option('template')
            ? Template::where('id', $this->option('template'))->get()
            : Template::whereNotNull('input_folder_path')->get();

        if ($templates->isEmpty()) {
            $this->warn('No hay plantillas con carpeta INPUT configurada.');
            return self::SUCCESS;
        }

        $totals = ['found' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($templates as $template) {
            $this->info("Procesando: {$template->name} (#{$template->id})");
            $summary = $ingestion->ingestTemplate($template);

            foreach (['found', 'created', 'skipped', 'failed'] as $k) {
                $totals[$k] += $summary[$k];
            }

            $this->line("  Encontrados: {$summary['found']}  Creados: {$summary['created']}  Omitidos: {$summary['skipped']}  Fallidos: {$summary['failed']}");
            foreach ($summary['details'] as $detail) {
                $this->line("  - {$detail}");
            }
        }

        $this->newLine();
        $this->info("Total → Creados: {$totals['created']}, Omitidos: {$totals['skipped']}, Fallidos: {$totals['failed']}");

        return self::SUCCESS;
    }
}
