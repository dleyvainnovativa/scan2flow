<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\SessionLoginController;
use App\Http\Controllers\IngestionController;
use App\Http\Controllers\DocumentReviewController;
/*
|--------------------------------------------------------------------------
| Web Routes — Phase 5 (Polish: audit, errors, flash)
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionLoginController::class, 'show'])->name('login');
    Route::post('/login', [SessionLoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionLoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    // Areas
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::get('/areas/{area}', [AreaController::class, 'show'])->name('areas.show');
    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');
    Route::post('/areas/{area}/permissions', [AreaController::class, 'setPermission'])->name('areas.permissions');
    Route::get('/areas/{area}/documents', [DocumentController::class, 'index'])->name('documents.index');

    // Templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
    Route::get('/templates/{template}/json', [TemplateController::class, 'json'])->name('templates.json');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('/templates/{template}/documents', [DocumentController::class, 'store'])->name('documents.store');

    // Documents
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/stream', [DocumentController::class, 'stream'])->name('documents.stream');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/approve', [DocumentReviewController::class, 'approve'])
        ->name('documents.approve');
    Route::post('/documents/{document}/reject', [DocumentReviewController::class, 'reject'])
        ->name('documents.reject');


    // Admin-only
    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

        Route::get('/ingestion', [IngestionController::class, 'index'])->name('ingestion.index');
        Route::post('/ingestion/{template}/run', [IngestionController::class, 'run'])->name('ingestion.run');
        Route::post('/ingestion/{template}/retry', [IngestionController::class, 'retryFailed'])->name('ingestion.retry');
    });
});
