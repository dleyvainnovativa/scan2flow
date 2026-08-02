<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Counts pages in a PDF. Primary: poppler `pdfinfo` (already installed for OCR).
 * Fallback: a lightweight byte-scan for /Type /Page objects — not as exact as
 * pdfinfo, but avoids returning null when the binary is unavailable.
 */
class PdfPageCounter
{
    private string $bin;
    private int $timeout;

    public function __construct()
    {
        // Reuse the OCR config's binary path convention if present.
        $this->bin = config('documents.pdfinfo_bin', env('OCR_BIN_PDFINFO', 'pdfinfo'));
        $this->timeout = 30;
    }

    /**
     * Return the page count for a PDF at an absolute local path, or null if it
     * can't be determined.
     */
    public function count(string $absolutePdfPath): ?int
    {
        if (! is_file($absolutePdfPath)) {
            return null;
        }

        $viaPdfinfo = $this->viaPdfinfo($absolutePdfPath);
        if ($viaPdfinfo !== null) {
            return $viaPdfinfo;
        }

        return $this->viaByteScan($absolutePdfPath);
    }

    private function viaPdfinfo(string $path): ?int
    {
        try {
            $process = new Process([$this->bin, $path]);
            $process->setTimeout($this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            // pdfinfo prints a line like: "Pages:           12"
            if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $m)) {
                return (int) $m[1];
            }
        } catch (Throwable $e) {
            Log::warning('pdfinfo page count failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Fallback: count "/Type /Page" occurrences (not /Pages). Approximate but
     * works without any binary. Good enough when pdfinfo isn't available.
     */
    private function viaByteScan(string $path): ?int
    {
        try {
            $content = file_get_contents($path);
            if ($content === false) {
                return null;
            }
            // Match "/Type /Page" not followed by "s" (to exclude "/Pages").
            $count = preg_match_all('/\/Type\s*\/Page(?![sA-Za-z])/', $content);
            return $count > 0 ? $count : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
