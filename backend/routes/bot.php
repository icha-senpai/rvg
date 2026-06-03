<?php

use App\Http\Controllers\Api\v1\BotVerificationController;
use App\Http\Controllers\Api\v1\BotPromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('bot')
    ->middleware(['api', 'throttle:1000,1'])
    ->group(function () {

        Route::post('/verify', [BotVerificationController::class, 'verify'])
            ->middleware('verify.bot.secret');

        Route::get('/users/{discordId}', [BotVerificationController::class, 'getUser'])
            ->middleware('verify.bot.secret');

        Route::get('/guild/members/{discordId}', [BotVerificationController::class, 'checkGuildMembership'])
            ->middleware('verify.bot.secret');

        Route::post('/promotions/offers/{promotionOffer}/prepare-accept', [BotPromotionController::class, 'prepareAccept'])
            ->middleware('verify.bot.secret');

        Route::post('/promotions/offers/{promotionOffer}/finalize-accept', [BotPromotionController::class, 'finalizeAccept'])
            ->middleware('verify.bot.secret');

        Route::post('/promotions/messages/{messageId}/prepare-accept', [BotPromotionController::class, 'prepareAcceptByMessage'])
            ->middleware('verify.bot.secret');

        Route::post('/promotions/messages/{messageId}/finalize-accept', [BotPromotionController::class, 'finalizeAcceptByMessage'])
            ->middleware('verify.bot.secret');
    });
