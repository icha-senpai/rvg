<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\V1\DiscordAuthController;
use App\Http\Controllers\Web\OperationPageController;


// SPA entry points
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/verify', function () {
    return Inertia::render('Verify');
})->name('verify');

// ------------------------------------------------------------
// DISCORD OAUTH *MUST* be WEB ROUTES – NO API PREFIXES
// ------------------------------------------------------------
Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])
    ->name('discord.redirect');

Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])
    ->name('discord.callback');

Route::prefix('squadrons/{squadron}')->group(function () {
    Route::get('/operations/create', [OperationPageController::class, 'create'])
        ->name('operations.create');
});

Route::get('/operations', [OperationPageController::class, 'index'])
    ->name('operations.index');

Route::get('/operations/{operation}', [OperationPageController::class, 'show'])
    ->name('operations.show');

Route::get('/squadrons/{squadron}/operations/create', [OperationPageController::class, 'create'])
    ->name('operations.create');

Route::get('/operations/{operation}/edit', [OperationPageController::class, 'edit'])
    ->name('operations.edit');

Route::middleware(['auth'])->group(function () {
    Route::post(
        'squadrons/{squadron}/operations',
        [OperationPageController::class, 'store']
    )->name('operations.store');
});