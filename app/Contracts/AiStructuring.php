<?php

namespace App\Contracts;

use App\Models\Template;

/**
 * AI structuring backend. Takes OCR/plain text plus a template's field
 * definitions and returns a map of field key => extracted value for fields not
 * already filled from structured sources (e.g. CFDI XML).
 *
 * M1-P0 ships NullAiStructuring (returns nothing). M1-P2 adds an OpenAI impl.
 */
interface AiStructuring
{
    /**
     * @param  string    $text            OCR / document text
     * @param  Template  $template        defines the fields to extract
     * @param  array<string,mixed> $known values already resolved (e.g. from XML)
     * @return array<string,string>       field key => value for missing fields
     */
    public function structure(string $text, Template $template, array $known = []): array;

    public function isAvailable(): bool;
}
