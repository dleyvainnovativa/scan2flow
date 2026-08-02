<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentStorageService;
use App\Services\PdfPageCounter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Populate page_count for documents that don't have it yet (e.g. rows created
 * before this feature). Streams each PDF to a temp file (files may live on a
 * non-local disk) and counts pages.
 *
 *   php artisan documents:count-pages          # only missing
 *   php artisan documents:count-pages --all    # recount everything
 */
class CountDocumentPages extends Command
{
    protected $signature = 'documents:count-pages {--all : Recount all, not just missing}';
    protected $description = 'Calcula y guarda el número de páginas de los documentos PDF';

    public function handle(PdfPageCounter $counter, DocumentStorageService $storage): int
    {
        $query = Document::whereNotNull('pdf_path');
        if (! $this->option('all')) {
            $query->whereNull('page_count');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('No hay documentos por procesar.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$total} documento(s)…");
        $bar = $this->output->createProgressBar($total);
        $disk = Storage::disk($storage->disk());
        $updated = 0;

        $query->chunkById(100, function ($docs) use ($counter, $disk, &$updated, $bar) {
            foreach ($docs as $doc) {
                $bar->advance();
                if (! $disk->exists($doc->pdf_path)) {
                    continue;
                }

                // Copy to a temp file so the counter has an absolute local path.
                $tmp = tempnam(sys_get_temp_dir(), 'pc_') . '.pdf';
                file_put_contents($tmp, $disk->get($doc->pdf_path));

                $pages = $counter->count($tmp);
                @unlink($tmp);

                if ($pages !== null) {
                    $doc->update(['page_count' => $pages]);
                    $updated++;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. Actualizados: {$updated}/{$total}.");

        return self::SUCCESS;
    }
}
