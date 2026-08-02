<?php

namespace App\Ingestion\Ocr;

use App\Contracts\OcrEngine;

/**
 * No-op OCR engine for M1-P0. Lets the ingestion pipeline run end-to-end using
 * only CFDI XML metadata, before real OCR (M1-P1) is wired in.
 */
class NullOcrEngine implements OcrEngine
{
    public function extractText(string $absolutePdfPath): string
    {
        return '';
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
