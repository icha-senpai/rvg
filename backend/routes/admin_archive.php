<?php

use App\Http\Controllers\Web\Admin\AdminArchiveController;
use App\Http\Controllers\Web\Admin\AdminArchiveEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin-panel'])
    ->prefix('admin/archive')
    ->name('admin.archive.')
    ->group(function () {
        Route::get('/', [AdminArchiveController::class, 'index'])->name('index');
        Route::post('/topics', [AdminArchiveController::class, 'store'])->name('topics.store');
        Route::put('/topics/{topic}', [AdminArchiveController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [AdminArchiveController::class, 'destroy'])->name('topics.destroy');

        Route::get('/topics/{topic}/entries', [AdminArchiveEntryController::class, 'index'])->name('topics.entries.index');
        Route::post('/topics/{topic}/entries', [AdminArchiveEntryController::class, 'store'])->name('topics.entries.store');
        Route::put('/topics/{topic}/entries/{entry}', [AdminArchiveEntryController::class, 'update'])->name('topics.entries.update');
        Route::delete('/topics/{topic}/entries/{entry}', [AdminArchiveEntryController::class, 'destroy'])->name('topics.entries.destroy');
    });
