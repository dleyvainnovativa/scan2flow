<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Search backend contract. Swap MySQL FULLTEXT for Meilisearch later by binding
 * a different implementation in SearchServiceProvider — callers don't change.
 */
interface SearchEngine
{
    /**
     * Search documents by a free-text query across metadata + OCR content,
     * restricted to areas the user may view.
     *
     * @param  array{area_id?:int, template_id?:int, metadata?:array<string,string>}  $filters
     * @return Collection<int, \App\Search\SearchResult>
     */
    public function search(string $query, User $user, array $filters = [], int $limit = 30): Collection;
}
