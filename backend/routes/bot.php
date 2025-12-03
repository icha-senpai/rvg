<?php

use App\Http\Controllers\Api\V1\BotVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('Api/bot')->middleware(['Api', 'throttle:60,1'])->group(function () {
    Route::post('/verify', [BotVerificationController::class, 'verify'])
        ->middleware('verify.bot.secret');

    Route::get('/users/{discordId}', [BotVerificationController::class, 'getUser'])
        ->middleware('verify.bot.secret');
        
    Route::get('/guild/members/{discordId}', [BotVerificationController::class, 'checkGuildMembership'])
        ->middleware('verify.bot.secret');
});