<?php

use App\Http\Controllers\Web\Admin\AdminArchiveAuditController;
use App\Http\Controllers\Web\Admin\AdminArchiveController;
use App\Http\Controllers\Web\Admin\AdminArchiveEntryController;
use App\Http\Controllers\Web\Admin\AdminArchiveTaxonomyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin-panel'])
    ->prefix('admin/archive')
    ->name('admin.archive.')
    ->group(function () {
        Route::get('/', [AdminArchiveController::class, 'index'])->name('index');
        Route::get('/audit', [AdminArchiveAuditController::class, 'index'])->name('audit.index');

        Route::get('/taxonomy', [AdminArchiveTaxonomyController::class, 'index'])->name('taxonomy.index');
        Route::post('/taxonomy/categories', [AdminArchiveTaxonomyController::class, 'storeCategory'])->name('taxonomy.categories.store');
        Route::put('/taxonomy/categories/{category}', [AdminArchiveTaxonomyController::class, 'updateCategory'])->name('taxonomy.categories.update');
        Route::delete('/taxonomy/categories/{category}', [AdminArchiveTaxonomyController::class, 'destroyCategory'])->name('taxonomy.categories.destroy');
        Route::post('/taxonomy/tags', [AdminArchiveTaxonomyController::class, 'storeTag'])->name('taxonomy.tags.store');
        Route::put('/taxonomy/tags/{tag}', [AdminArchiveTaxonomyController::class, 'updateTag'])->name('taxonomy.tags.update');
        Route::delete('/taxonomy/tags/{tag}', [AdminArchiveTaxonomyController::class, 'destroyTag'])->name('taxonomy.tags.destroy');

        Route::post('/topics', [AdminArchiveController::class, 'store'])->name('topics.store');
        Route::put('/topics/{topic}', [AdminArchiveController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [AdminArchiveController::class, 'destroy'])->name('topics.destroy');

        Route::get('/topics/{topic}/entries', [AdminArchiveEntryController::class, 'index'])->name('topics.entries.index');
        Route::post('/topics/{topic}/entries', [AdminArchiveEntryController::class, 'store'])->name('topics.entries.store');
        Route::put('/topics/{topic}/entries/{entry}', [AdminArchiveEntryController::class, 'update'])->name('topics.entries.update');
        Route::delete('/topics/{topic}/entries/{entry}', [AdminArchiveEntryController::class, 'destroy'])->name('topics.entries.destroy');
    });
