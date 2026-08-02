<?php

namespace App\Contracts;

/**
 * OCR backend contract. M1-P0 ships a NullOcrEngine (no-op) so the pipeline
 * runs without OCR; M1-P1 adds TesseractOcrEngine (local), and later an
 * AwsTextractOcrEngine — swapping is a one-line binding change in
 * IngestionServiceProvider. Callers only depend on this interface.
 */
interface OcrEngine
{
    /**
     * Extract text from a PDF file (absolute path on the local filesystem).
     * Returns the full extracted text. Implementations that also produce word
     * bounding boxes may expose them separately; the base contract is text.
     *
     * @param  string  $absolutePdfPath
     * @return string  extracted plain text ('' if none)
     */
    public function extractText(string $absolutePdfPath): string;

    /** Whether this engine is actually configured/available to run. */
    public function isAvailable(): bool;
}
