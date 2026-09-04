<?php

namespace App\Ingestion;

use App\Contracts\AiStructuring;
use App\Contracts\OcrEngine;
use App\Models\Document;
use App\Models\IngestionRecord;
use App\Models\Template;
use App\Services\DocumentStorageService;
use App\Services\MetadataService;
use App\Services\PdfPageCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Ingestion\InputSourceResolver;
use App\Services\PageBalanceService;
use App\Exceptions\InsufficientPagesException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates ingestion for a template's INPUT folder:
 *   scan → pair PDF+XML by base name → parse CFDI XML → map to fields →
 *   (OCR — null in M1-P0) → (AI structuring — null in M1-P0) →
 *   create Document + metadata + content → move files to structured storage →
 *   record status.
 *
 * Idempotent per (template, base_name) via ingestion_records unique key.
 */
class IngestionService
{
    public function __construct(
        private CfdiParser $cfdi,
        private CfdiFieldMapper $mapper,
        private MetadataService $metadata,
        private DocumentStorageService $storage,
        private OcrEngine $ocr,
        private AiStructuring $ai,
        private IngestionArchiver $archiver,
        private PdfPageCounter $pageCounter,
        private PageBalanceService $balance,   // >>> ADD
        private InputSourceResolver $inputSource
    ) {}

    /**
     * Process one template's INPUT folder. Returns a summary counts array.
     *
     * @return array{found:int, created:int, skipped:int, failed:int, details:array}
     */
    public function ingestTemplate(Template $template): array
    {
        $summary = ['found' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];

        $source = $this->inputSource->resolve($template);   // disk + path
        $disk = $source['disk'];
        $path = $source['path'];

        // Validate the input path exists on the disk (works local + sftp).
        try {
            $pairs = $this->pairFiles($disk, $path);
        } catch (\Throwable $e) {
            $summary['details'][] = "No se pudo leer la carpeta INPUT: {$e->getMessage()}";
            return $summary;
        }
        $summary['found'] = count($pairs);

        foreach ($pairs as $base => $files) {
            // Idempotency: skip base names already ingested for this template.
            $existing = IngestionRecord::where('template_id', $template->id)
                ->where('base_name', $base)->first();
            if ($existing && $existing->status === 'done') {
                $summary['skipped']++;
                continue;
            }

            $record = $existing ?: new IngestionRecord([
                'template_id' => $template->id,
                'base_name'   => $base,
            ]);
            $record->source_pdf_path = $files['pdf'] ?? null;
            $record->source_xml_path = $files['xml'] ?? null;
            $record->status = 'processing';
            $record->error = null;
            $record->save();

            try {
                $document = $this->processPair($template, $disk, $files);

                $record->document_id = $document->id;
                $record->status = 'done';
                $record->save();
                $summary['created']++;
                $this->archiveOnDisk($disk, $source['processedPath'], $files, success: true);

                // $this->archiver->archive($inputDir, $files, success: true);
            } catch (InsufficientPagesException $e) {
                // Must come BEFORE the Throwable catch — it's more specific
                // (InsufficientPagesException extends Throwable). Don't archive
                // as failure: leave the file in INPUT for a retry after top-up.
                $record->status = 'failed';
                $record->error  = 'Saldo de páginas insuficiente.';
                $record->save();
                $summary['failed']++;
                $summary['details'][] = "«{$base}»: {$e->getMessage()}";
                continue;
            } catch (Throwable $e) {
                $record->status = 'failed';
                $record->error = mb_substr($e->getMessage(), 0, 250);
                $record->save();
                $summary['failed']++;
                $summary['details'][] = "«{$base}»: {$e->getMessage()}";
                $this->archiveOnDisk($disk, $source['processedPath'], $files, success: false);

                // $this->archiver->archive($inputDir, $files, success: false);
            }
        }

        return $summary;
    }

    /**
     * Pair files in a directory by base name. A pair needs at least a PDF;
     * the XML is optional but expected for CFDI.
     *
     * @return array<string, array{pdf?:string, xml?:string}>
     */
    private function pairFiles(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $dir): array
    {
        $pairs = [];
        foreach ($disk->files($dir) as $filePath) {   // returns file paths, no dirs
            $file = basename($filePath);
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $base = pathinfo($file, PATHINFO_FILENAME);
            if ($ext === 'pdf')      $pairs[$base]['pdf'] = $filePath;
            elseif ($ext === 'xml')  $pairs[$base]['xml'] = $filePath;
        }
        return array_filter($pairs, fn($p) => isset($p['pdf']));
    }

    /**
     * Process a single PDF(+XML) pair into a Document.
     *
     * $files['pdf'] / $files['xml'] are now DISK-RELATIVE paths (local or sftp).
     * We read bytes through the disk. OCR (tesseract) and pdfinfo need a REAL
     * local path, so we pull the PDF into a temp file, process it, and clean up.
     */
    private function processPair(Template $template, $disk, array $files): Document
    {
        $pdfPath = $files['pdf'];
        $xmlPath = $files['xml'] ?? null;

        // 1. Parse CFDI XML (primary metadata source) — read bytes via the disk.
        $cfdiValues = [];
        if ($xmlPath) {
            $cfdiValues = $this->cfdi->parse($disk->get($xmlPath));
        }
        $mapped = $this->mapper->map($template, $cfdiValues);

        // Pull the PDF to a temp LOCAL file: OCR + pdfinfo can't run over SFTP.
        $tmpPdf = tempnam(sys_get_temp_dir(), 'ingest_') . '.pdf';
        file_put_contents($tmpPdf, $disk->get($pdfPath));

        try {
            // 2. OCR — full text for search/highlight (runs on the temp file).
            $ocrText = '';
            if ($this->ocr->isAvailable()) {
                $ocrText = $this->ocr->extractText($tmpPdf);
            }

            // 3. AI structuring for fields the XML didn't fill.
            if ($template->ai_enabled && $this->ai->isAvailable() && $ocrText !== '') {
                $aiValues = $this->ai->structure($ocrText, $template, $mapped);
                // XML/known values win; AI only fills gaps ($mapped last).
                $mapped = array_merge($aiValues, $mapped);
            }

            // 4. Derive a human title (prefer folio/uuid, else base name).
            $title = $this->deriveTitle($template, $mapped, $files);

            // Page count from the temp file; balance pre-check before spending.
            $pageCount = $this->pageCounter->count($tmpPdf) ?? 0;
            if ($pageCount > 0 && ! $this->balance->canCover($pageCount, $template->tenant_id)) {
                throw new InsufficientPagesException(
                    $template->tenant_id,
                    $pageCount,
                    $this->balance->balance($template->tenant_id)
                );
            }

            // 5. Persist inside a transaction. XML bytes are read via the disk
            //    into a temp file too, so storeFromLocal can copy it as before.
            $tmpXml = null;
            if ($xmlPath) {
                $tmpXml = tempnam(sys_get_temp_dir(), 'ingest_') . '.xml';
                file_put_contents($tmpXml, $disk->get($xmlPath));
            }

            try {
                return DB::transaction(function () use ($template, $tmpPdf, $tmpXml, $mapped, $ocrText, $title, $pageCount) {
                    $storedPdf = $this->storeFromLocal($template, $tmpPdf, 'pdf');
                    $storedXml = $tmpXml ? $this->storeFromLocal($template, $tmpXml, 'xml', pathinfo($storedPdf, PATHINFO_FILENAME)) : null;

                    $ocrStatus = match (true) {
                        ! $this->ocr->isAvailable() => 'not_applicable',
                        $ocrText !== ''             => 'done',
                        default                     => 'failed',
                    };

                    $document = Document::create([
                        'area_id'     => $template->area_id,
                        'template_id' => $template->id,
                        'title'       => $title,
                        'pdf_path'    => $storedPdf,
                        'page_count'  => $pageCount,
                        'xml_path'    => $storedXml,
                        'ocr_status'  => $ocrStatus,
                        'uploaded_by' => null,        // no human — ingested
                        'status'      => 'pending',
                    ]);

                    $this->metadata->sync($document, $mapped);

                    if ($ocrText !== '') {
                        $document->content()->create(['body' => $ocrText, 'source' => 'ocr']);
                    }

                    if ($pageCount > 0) {
                        $this->balance->debit(
                            pages: $pageCount,
                            tenantId: $template->tenant_id,
                            subjectType: \App\Models\Document::class,
                            subjectId: $document->id,
                            causedBy: null,
                            note: 'Ingesta: ' . $document->title,
                        );
                    }
                    return $document;
                });
            } finally {
                if ($tmpXml) {
                    @unlink($tmpXml);
                }
            }
        } finally {
            @unlink($tmpPdf); // always clean up the temp PDF
        }
    }

    /**
     * Copy a file from the local INPUT folder into the structured storage disk
     * using the same layout DocumentStorageService uses for uploads.
     */
    private function storeFromLocal(Template $template, string $localPath, string $ext, ?string $baseName = null): string
    {
        $disk = Storage::disk($this->storage->disk());
        $dir = $this->storage->directoryFor($template);
        $name = ($baseName ?: pathinfo($localPath, PATHINFO_FILENAME)) . '.' . $ext;

        $target = "{$dir}/{$name}";
        $i = 2;
        while ($disk->exists($target)) {
            $target = "{$dir}/" . pathinfo($name, PATHINFO_FILENAME) . "-{$i}.{$ext}";
            $i++;
        }

        $disk->put($target, file_get_contents($localPath));

        return $target;
    }

    private function deriveTitle(Template $template, array $mapped, array $files): string
    {
        $originalName = pathinfo($files['pdf'], PATHINFO_FILENAME);

        // Per-template toggle via the `title_source` column:
        //   'original' → use the source PDF's filename as-is
        //   'derived'  → build a title from CFDI metadata (folio/uuid/serie),
        //                falling back to the filename. (default)
        if (($template->title_source ?? 'derived') === 'original') {
            return $originalName;
        }

        foreach (['folio', 'uuid', 'serie'] as $key) {
            if (! empty($mapped[$key])) {
                return $template->name . ' ' . $mapped[$key];
            }
        }

        return $originalName;
    }

    private function archiveOnDisk($disk, string $processedDir, array $files, bool $success): void
    {
        // Move both pdf + xml into processed/ (or a failed/ subfolder).
        $sub = $success ? '' : 'failed/';
        foreach (['pdf', 'xml'] as $k) {
            if (empty($files[$k])) continue;
            $from = $files[$k];
            $to   = rtrim($processedDir, '/') . '/' . $sub . basename($from);
            try {
                $disk->makeDirectory(dirname($to));
                $disk->move($from, $to);           // works local + sftp
            } catch (\Throwable $e) {
                Log::warning('Archive move failed', ['from' => $from, 'to' => $to, 'error' => $e->getMessage()]);
            }
        }
    }
}
