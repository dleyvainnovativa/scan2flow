<?php

namespace App\Http\Controllers;

use App\Contracts\SearchEngine;
use App\Models\Area;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchEngine $engine) {}

    /** Global search page (metadata + OCR), scoped to the user's areas. */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $filters = array_filter([
            'area_id'     => $request->integer('area_id') ?: null,
            'template_id' => $request->integer('template_id') ?: null,
        ]);

        $results = $q !== ''
            ? $this->engine->search($q, $request->user(), $filters)
            : collect();

        // Areas the user can pick from as a filter.
        $areas = $request->user()->isAdmin()
            ? Area::orderBy('name')->get()
            : $request->user()->areas()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('search.index', compact('q', 'results', 'areas', 'filters'));
    }
}
