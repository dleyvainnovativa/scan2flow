<?php

namespace App\Search;

use App\Contracts\SearchEngine;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MySQL FULLTEXT search across document metadata + OCR content.
 *
 * Strategy: run two FULLTEXT queries (metadata values, OCR body), union the
 * document ids with their scores, restrict to areas the user can view, then
 * hydrate + build highlighted snippets. Kept intentionally simple; swap for
 * Meilisearch when scale/highlight quality demands it.
 */
class MysqlFulltextEngine implements SearchEngine
{
    public function search(string $query, User $user, array $filters = [], int $limit = 30): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $allowedAreaIds = $this->allowedAreaIds($user);
        if ($allowedAreaIds->isEmpty()) {
            return collect();
        }

        $boolean = $this->toBooleanQuery($query);

        // --- Match in OCR content ---
        $contentHits = DB::table('document_contents')
            ->join('documents', 'documents.id', '=', 'document_contents.document_id')
            ->whereIn('documents.area_id', $allowedAreaIds)
            ->where(fn($q) => $this->applyStatusVisibility($q, $user))
            ->when(isset($filters['area_id']), fn($q) => $q->where('documents.area_id', $filters['area_id']))
            ->when(isset($filters['template_id']), fn($q) => $q->where('documents.template_id', $filters['template_id']))
            ->whereRaw('MATCH(document_contents.body) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->selectRaw('documents.id as document_id, MATCH(document_contents.body) AGAINST (? IN BOOLEAN MODE) as score', [$boolean])
            ->limit($limit * 2)
            ->get();

        // --- Match in metadata values ---
        $metaHits = DB::table('document_metadata')
            ->join('documents', 'documents.id', '=', 'document_metadata.document_id')
            ->whereIn('documents.area_id', $allowedAreaIds)
            ->where(fn($q) => $this->applyStatusVisibility($q, $user))
            ->when(isset($filters['area_id']), fn($q) => $q->where('documents.area_id', $filters['area_id']))
            ->when(isset($filters['template_id']), fn($q) => $q->where('documents.template_id', $filters['template_id']))
            ->whereRaw('MATCH(document_metadata.value) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->selectRaw('documents.id as document_id, MATCH(document_metadata.value) AGAINST (? IN BOOLEAN MODE) as score', [$boolean])
            ->limit($limit * 2)
            ->get();

        // --- Merge scores + track where each matched ---
        $scores = [];   // id => score
        $where = [];    // id => set of sources
        foreach ($contentHits as $h) {
            $scores[$h->document_id] = ($scores[$h->document_id] ?? 0) + (float) $h->score;
            $where[$h->document_id]['ocr'] = true;
        }
        foreach ($metaHits as $h) {
            $scores[$h->document_id] = ($scores[$h->document_id] ?? 0) + (float) $h->score;
            $where[$h->document_id]['metadata'] = true;
        }

        if (empty($scores)) {
            return collect();
        }

        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);

        $documents = Document::with(['area', 'template', 'metadata.field', 'content'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $terms = $this->terms($query);

        return collect($ids)->map(function ($id) use ($documents, $scores, $where, $terms) {
            $doc = $documents->get($id);
            if (! $doc) {
                return null;
            }
            $sources = array_keys($where[$id] ?? []);
            $matchedIn = count($sources) > 1 ? 'both' : ($sources[0] ?? '');

            return new SearchResult(
                document: $doc,
                score: (float) $scores[$id],
                matchedIn: $matchedIn,
                snippet: $this->buildSnippet($doc, $terms),
            );
        })->filter()->values();
    }

    /** Areas the user may view (admins: all). */
    private function allowedAreaIds(User $user): Collection
    {
        if ($user->isAdmin()) {
            return DB::table('areas')->pluck('id');
        }
        return $user->areas()->where('can_view', true)->pluck('areas.id');
    }

    /**
     * QA status visibility for search (mirrors App\Support\DocumentVisibility).
     * Applied as a nested where() on the raw documents-joined query so that
     * non-approved documents only surface in areas the user can edit/approve.
     * Admins are unconstrained. Without this, unapproved docs leak into search.
     */
    private function applyStatusVisibility($query, User $user): void
    {
        if ($user->isAdmin()) {
            return; // no constraint
        }

        $privilegedAreaIds = $user->areas()
            ->where(function ($q) {
                $q->where('can_edit', true)->orWhere('can_approve', true);
            })
            ->pluck('areas.id')
            ->all();

        $query->where('documents.status', 'approved');
        if (! empty($privilegedAreaIds)) {
            $query->orWhereIn('documents.area_id', $privilegedAreaIds);
        }
    }

    /** Convert a plain query to BOOLEAN MODE with prefix matching per term. */
    private function toBooleanQuery(string $query): string
    {
        return collect($this->terms($query))
            ->map(fn($t) => '+' . $t . '*')
            ->implode(' ');
    }

    /** Split query into sanitized terms (drops FULLTEXT operators/noise). */
    private function terms(string $query): array
    {
        $clean = preg_replace('/[+\-><\(\)~*"@]+/', ' ', $query);
        return collect(preg_split('/\s+/', trim($clean)))
            ->filter(fn($t) => mb_strlen($t) >= 2)   // FULLTEXT min token len
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build a highlighted snippet. Prefers OCR body context around the first
     * matched term; falls back to matched metadata values.
     */
    private function buildSnippet(Document $doc, array $terms): ?string
    {
        $body = optional($doc->content)->body;

        if ($body) {
            $snippet = $this->contextAround($body, $terms, 160);
            if ($snippet) {
                return $this->markTerms($snippet, $terms);
            }
        }

        // Fall back to whichever metadata value contains a term.
        foreach ($doc->metadata as $m) {
            foreach ($terms as $t) {
                if ($m->value && Str::contains(Str::lower($m->value), Str::lower($t))) {
                    return $m->field->label . ': ' . $this->markTerms(e($m->value), $terms);
                }
            }
        }

        return null;
    }

    /** Extract ~$len chars of context around the first matched term. */
    private function contextAround(string $text, array $terms, int $len): ?string
    {
        $lower = Str::lower($text);
        $pos = false;
        foreach ($terms as $t) {
            $p = mb_strpos($lower, Str::lower($t));
            if ($p !== false) {
                $pos = $p;
                break;
            }
        }
        if ($pos === false) {
            return null;
        }
        $start = max(0, $pos - (int) ($len / 2));
        $snippet = mb_substr($text, $start, $len);
        $snippet = preg_replace('/\s+/', ' ', trim($snippet));
        return ($start > 0 ? '… ' : '') . e($snippet) . ' …';
    }

    /** Wrap matched terms in <mark> (case-insensitive). Input must be escaped. */
    private function markTerms(string $escapedText, array $terms): string
    {
        foreach ($terms as $t) {
            $quoted = preg_quote(e($t), '/');
            $escapedText = preg_replace("/({$quoted})/iu", '<mark>$1</mark>', $escapedText);
        }
        return $escapedText;
    }
}
