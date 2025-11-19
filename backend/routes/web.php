<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DiscordAuthController;

Route::get('/', function () {
    return response()->json(['status' => 'Backend API Online']);
});

// Discord auth
Route::get('/auth/discord', [DiscordAuthController::class, 'redirect'])->name('discord.redirect');
Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])->name('discord.callback');
