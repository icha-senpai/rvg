<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\V1\DiscordAuthController;

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
