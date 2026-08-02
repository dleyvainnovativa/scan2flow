<?php

namespace App\Providers;

use App\Contracts\SearchEngine;
use App\Search\MysqlFulltextEngine;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding for a MeilisearchEngine later — callers use the
        // SearchEngine interface, so nothing else changes.
        $this->app->bind(SearchEngine::class, MysqlFulltextEngine::class);
    }
}
