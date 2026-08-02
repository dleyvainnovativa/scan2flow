<?php

namespace App\Ingestion\Ocr;

use App\Contracts\OcrEngine;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Local OCR via poppler + Tesseract.
 *
 * Strategy:
 *   1. Try `pdftotext` — if the PDF has a real text layer (most digital CFDIs),
 *      this is instant and exact. If it yields enough text, we're done.
 *   2. Otherwise the PDF is likely scanned: rasterize each page with `pdftoppm`
 *      and run `tesseract -l spa` on the images, concatenating the text.
 *
 * Requires: poppler-utils (pdftotext, pdftoppm) and tesseract-ocr + spa pack.
 * If binaries are missing, isAvailable() returns false and the ingestion
 * pipeline simply proceeds without OCR text.
 */
class TesseractOcrEngine implements OcrEngine
{
    private array $bin;
    private string $lang;
    private int $minChars;
    private int $dpi;
    private int $maxPages;
    private int $timeout;

    public function __construct()
    {
        $this->bin      = config('ocr.bin');
        $this->lang     = config('ocr.lang', 'spa');
        $this->minChars = (int) config('ocr.text_layer_min_chars', 40);
        $this->dpi      = (int) config('ocr.dpi', 200);
        $this->maxPages = (int) config('ocr.max_pages', 30);
        $this->timeout  = (int) config('ocr.timeout', 120);
    }

    public function isAvailable(): bool
    {
        // Available if at least pdftotext OR (pdftoppm + tesseract) resolve.
        return $this->binaryExists($this->bin['pdftotext'])
            || ($this->binaryExists($this->bin['pdftoppm']) && $this->binaryExists($this->bin['tesseract']));
    }

    public function extractText(string $absolutePdfPath): string
    {
        if (! is_file($absolutePdfPath)) {
            return '';
        }

        // --- 1. Digital PDF path: pdftotext ---
        $digital = $this->tryPdfToText($absolutePdfPath);
        if (mb_strlen(trim($digital)) >= $this->minChars) {
            return $this->clean($digital);
        }

        // --- 2. Scanned PDF path: pdftoppm + tesseract ---
        if ($this->binaryExists($this->bin['pdftoppm']) && $this->binaryExists($this->bin['tesseract'])) {
            $scanned = $this->tryScanOcr($absolutePdfPath);
            if (trim($scanned) !== '') {
                return $this->clean($scanned);
            }
        }

        // Return whatever little the text layer had (may be empty).
        return $this->clean($digital);
    }

    /** Extract embedded text layer (fast path for digital PDFs). */
    private function tryPdfToText(string $pdf): string
    {
        if (! $this->binaryExists($this->bin['pdftotext'])) {
            return '';
        }
        try {
            // '-' sends output to stdout; '-layout' preserves reading order.
            $process = new Process([$this->bin['pdftotext'], '-layout', $pdf, '-']);
            $process->setTimeout($this->timeout);
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : '';
        } catch (Throwable $e) {
            Log::warning('pdftotext failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /** Rasterize pages and OCR each with Tesseract. */
    private function tryScanOcr(string $pdf): string
    {
        $workDir = sys_get_temp_dir() . '/ocr_' . uniqid('', true);
        if (! mkdir($workDir) && ! is_dir($workDir)) {
            return '';
        }

        try {
            $prefix = $workDir . '/page';
            // pdftoppm -png -r DPI [-l MAXPAGES] input prefix  → prefix-1.png, ...
            $args = [$this->bin['pdftoppm'], '-png', '-r', (string) $this->dpi];
            if ($this->maxPages > 0) {
                $args[] = '-l';
                $args[] = (string) $this->maxPages;
            }
            $args[] = $pdf;
            $args[] = $prefix;

            $ppm = new Process($args);
            $ppm->setTimeout($this->timeout);
            $ppm->run();
            if (! $ppm->isSuccessful()) {
                Log::warning('pdftoppm failed', ['error' => $ppm->getErrorOutput()]);
                return '';
            }

            $images = glob($workDir . '/page*.png') ?: [];
            sort($images); // page-1, page-2, ... natural order for zero-padded names

            $texts = [];
            foreach ($images as $img) {
                $texts[] = $this->ocrImage($img);
            }

            return implode("\n\n", array_filter($texts));
        } catch (Throwable $e) {
            Log::warning('scan OCR failed', ['error' => $e->getMessage()]);
            return '';
        } finally {
            $this->cleanupDir($workDir);
        }
    }

    /** Run Tesseract on a single image, returning its text. */
    private function ocrImage(string $imagePath): string
    {
        try {
            // tesseract <image> stdout -l spa
            $process = new Process([
                $this->bin['tesseract'], $imagePath, 'stdout', '-l', $this->lang,
            ]);
            $process->setTimeout($this->timeout);
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : '';
        } catch (Throwable $e) {
            Log::warning('tesseract failed', ['image' => $imagePath, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /** Check a binary resolves (either absolute path or on PATH via `command -v`). */
    private function binaryExists(string $binary): bool
    {
        if (str_contains($binary, '/') && is_executable($binary)) {
            return true;
        }
        try {
            $probe = new Process(['command', '-v', $binary]);
            $probe->run();
            return $probe->isSuccessful() && trim($probe->getOutput()) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Normalize whitespace; keep line breaks meaningful for snippets. */
    private function clean(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function cleanupDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
