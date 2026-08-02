<?php

namespace App\Ingestion\Ai;

use App\Contracts\AiStructuring;
use App\Models\Template;

/**
 * No-op AI structuring for M1-P0 / M1-P1. Returns nothing, so metadata comes
 * solely from the CFDI XML until the OpenAI implementation lands in M1-P2.
 */
class NullAiStructuring implements AiStructuring
{
    public function structure(string $text, Template $template, array $known = []): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
