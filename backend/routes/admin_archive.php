<?php

use App\Http\Controllers\Web\Admin\AdminArchiveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin-panel'])
    ->prefix('admin/archive')
    ->name('admin.archive.')
    ->group(function () {
        Route::get('/', [AdminArchiveController::class, 'index'])->name('index');
        Route::post('/topics', [AdminArchiveController::class, 'store'])->name('topics.store');
        Route::put('/topics/{topic}', [AdminArchiveController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [AdminArchiveController::class, 'destroy'])->name('topics.destroy');
    });
