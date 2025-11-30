<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\V1\DiscordAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Frontend routes (handled by Inertia)
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// API routes (keep existing API routes as they are)
Route::prefix('api/v1')->group(function () {
    // Discord auth
    Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])->name('discord.redirect');
    Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])->name('discord.callback');
});
