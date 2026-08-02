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
    ) {}

    /**
     * Process one template's INPUT folder. Returns a summary counts array.
     *
     * @return array{found:int, created:int, skipped:int, failed:int, details:array}
     */
    public function ingestTemplate(Template $template): array
    {
        $summary = ['found' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];

        $inputDir = $template->input_folder_path;
        if (! $inputDir || ! is_dir($inputDir)) {
            $summary['details'][] = "Carpeta INPUT no encontrada: " . ($inputDir ?: '(vacía)');
            return $summary;
        }

        $pairs = $this->pairFiles($inputDir);
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
                $document = $this->processPair($template, $files);
                $record->document_id = $document->id;
                $record->status = 'done';
                $record->save();
                $summary['created']++;
                $this->archiver->archive($inputDir, $files, success: true);
            } catch (Throwable $e) {
                $record->status = 'failed';
                $record->error = mb_substr($e->getMessage(), 0, 250);
                $record->save();
                $summary['failed']++;
                $summary['details'][] = "«{$base}»: {$e->getMessage()}";
                $this->archiver->archive($inputDir, $files, success: false);
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
    private function pairFiles(string $dir): array
    {
        $pairs = [];
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = rtrim($dir, '/') . '/' . $file;
            if (! is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $base = pathinfo($file, PATHINFO_FILENAME);

            if ($ext === 'pdf') {
                $pairs[$base]['pdf'] = $path;
            } elseif ($ext === 'xml') {
                $pairs[$base]['xml'] = $path;
            }
        }

        // Only keep entries that have a PDF (the document's primary file).
        return array_filter($pairs, fn($p) => isset($p['pdf']));
    }

    /** Process a single PDF(+XML) pair into a Document. */
    private function processPair(Template $template, array $files): Document
    {
        $pdfPath = $files['pdf'];
        $xmlPath = $files['xml'] ?? null;

        // 1. Parse CFDI XML (primary metadata source).
        $cfdiValues = [];
        if ($xmlPath && is_file($xmlPath)) {
            $cfdiValues = $this->cfdi->parse(file_get_contents($xmlPath));
        }
        $mapped = $this->mapper->map($template, $cfdiValues);

        // 2. OCR (null in M1-P0) — full text for search/highlight.
        $ocrText = '';
        if ($this->ocr->isAvailable()) {
            $ocrText = $this->ocr->extractText($pdfPath);
        }

        // 3. AI structuring (null in M1-P0) for fields the XML didn't fill.

        if ($template->ai_enabled && $this->ai->isAvailable() && $ocrText !== '') {
            $aiValues = $this->ai->structure($ocrText, $template, $mapped);
            // XML/known values win; AI only fills gaps (array_merge order matters:
            // $mapped last so it overrides any overlapping AI keys).
            $mapped = array_merge($aiValues, $mapped);
        }

        // 4. Derive a human title (prefer folio/uuid, else base name).
        $title = $this->deriveTitle($template, $mapped, $files);


        // 5. Persist inside a transaction.
        return DB::transaction(function () use ($template, $pdfPath, $xmlPath, $mapped, $ocrText, $title) {
            $storedPdf = $this->storeFromLocal($template, $pdfPath, 'pdf');
            $storedXml = $xmlPath ? $this->storeFromLocal($template, $xmlPath, 'xml', pathinfo($storedPdf, PATHINFO_FILENAME)) : null;
            $ocrStatus = match (true) {
                ! $this->ocr->isAvailable() => 'not_applicable', // no OCR engine bound
                $ocrText !== ''             => 'done',           // text extracted
                default                     => 'failed',         // engine ran, no text
            };

            $pageCount = $this->pageCounter->count($pdfPath);

            $document = Document::create([
                'area_id'     => $template->area_id,
                'template_id' => $template->id,
                'title'       => $title,
                'pdf_path'    => $storedPdf,
                'page_count' => $pageCount,
                'xml_path'    => $storedXml,
                'ocr_status' => $ocrStatus,
                'uploaded_by' => null,        // no human — ingested
                'status'      => 'pending',   // >>> ADD
            ]);

            $this->metadata->sync($document, $mapped);

            if ($ocrText !== '') {
                $document->content()->create(['body' => $ocrText, 'source' => 'ocr']);
            }

            return $document;
        });
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
        foreach (['folio', 'uuid', 'serie'] as $key) {
            if (! empty($mapped[$key])) {
                return $template->name . ' ' . $mapped[$key];
            }
        }
        return pathinfo($files['pdf'], PATHINFO_FILENAME);
    }
}
