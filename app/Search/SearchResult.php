<?php

namespace App\Search;

use App\Models\Document;

/**
 * A single search hit: the document, a relevance score, where it matched, and a
 * highlighted snippet (with <mark> around matched terms) for the results list.
 */
class SearchResult
{
    public function __construct(
        public Document $document,
        public float $score = 0.0,
        public string $matchedIn = '',      // 'metadata' | 'ocr' | 'both'
        public ?string $snippet = null,     // may contain <mark>…</mark>
    ) {}
}
