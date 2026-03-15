<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\v1\UserController;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\v1\SquadronController;
use App\Http\Controllers\Api\v1\SquadronMemberController;
use App\Http\Controllers\Api\v1\MeController;
use App\Http\Controllers\Api\v1\MePreferenceController;
use App\Http\Controllers\Api\v1\RsiHandleController;
use App\Http\Controllers\Api\v1\OperationController;
use App\Http\Controllers\Api\v1\OperationParticipantController;
use App\Http\Controllers\Api\v1\DiscordIdentityController;
use App\Http\Controllers\Api\v1\MediaApiController;
use App\Http\Controllers\Api\v1\OperationTemplateController;

/*
|--------------------------------------------------------------------------
| Public API Endpoints (No Auth Required)
|--------------------------------------------------------------------------
|
| These remain open so Discord bot and WordPress can use them without rank.
|
*/

// Health check
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'msg' => 'Organization Platform is online Commander'
    ]);
});

 Route::get('/discord/identity/{discordId}', [DiscordIdentityController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected API Endpoints By Rank (Chain of Command)
|--------------------------------------------------------------------------
|
| The higher the rank number, the more authority the endpoint requires.
|
| 1 Member
| 2 Lieutenant
| 3 Commander
| 4 Wing Commander
| 5 Admiral
| 6 Grand Admiral
|
*/

Route::middleware(['auth:sanctum',])->group(function () {
 
    // Legacy authenticated profile endpoint.
    Route::get('/profile', [UserController::class, 'profile']);

    // Legacy user-management endpoints now authorize inside the controller.
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/verified', [UserController::class, 'verified']);
    Route::get('/users/unverified', [UserController::class, 'unverified']);
    Route::get('/users/{discord_id}', [UserController::class, 'show']);
    Route::delete('/users/{discord_id}', [UserController::class, 'destroy']);

    Route::delete('/nuke-system', function () {
        return ['warning' => 'System obliterated (simulation only)'];
    })->middleware(['rank:6']);

    // Me
    Route::get('/me', [MeController::class, 'show']);
    Route::put('/me', [MeController::class, 'update']);

    // /me/preferences endpoints
    Route::get('/me/preferences', [MePreferenceController::class, 'show']);
    Route::put('/me/preferences', [MePreferenceController::class, 'update']);
    Route::patch('/me/preferences', [MePreferenceController::class, 'update']);

    // member-only create
    Route::post('/rsi-requests', [RsiHandleController::class, 'store']);

    // officer
    Route::get('/rsi-requests', [RsiHandleController::class, 'index']);
    Route::post('/rsi-requests/{change}/approve', [RsiHandleController::class, 'approve']);
    Route::post('/rsi-requests/{change}/reject', [RsiHandleController::class, 'reject']);

    // Squadron CRUD
    Route::get('/squadrons', [SquadronController::class, 'index']);
    Route::post('/squadrons', [SquadronController::class, 'store']);
    Route::get('/squadrons/{squadron}', [SquadronController::class, 'show']);
    Route::put('/squadrons/{squadron}', [SquadronController::class, 'update']);
    Route::delete('/squadrons/{squadron}', [SquadronController::class, 'destroy']);

    // Squadron members
    Route::get('/squadrons/{squadron}/members', [SquadronController::class, 'members']);

    // User join/leave
    Route::post('/squadrons/{squadron}/join', [SquadronMemberController::class, 'join']);
    Route::post('/squadrons/{squadron}/leave', [SquadronMemberController::class, 'leave']);

    // Admin manage members
    Route::post('/squadrons/{squadron}/members', [SquadronMemberController::class, 'store']);
    Route::put('/squadrons/{squadron}/members/{member}', [SquadronMemberController::class, 'update']);
    Route::delete('/squadrons/{squadron}/members/{member}', [SquadronMemberController::class, 'destroy']);
    Route::post('/squadrons/{squadron}/members/{user}/promote-lieutenant', [SquadronMemberController::class, 'promoteLieutenant']);
    Route::post('/squadrons/{squadron}/members/{user}/demote-lieutenant', [SquadronMemberController::class, 'demoteLieutenant']);

    // Operations API
    Route::get('/operations', [OperationController::class, 'index']);
    Route::get('/operations/{operation}', [OperationController::class, 'show']);
    Route::post('/operations', [OperationController::class, 'storeGlobal']);
    Route::post('/squadrons/{squadron}/operations', [OperationController::class, 'store']);
    Route::put('/operations/{operation}', [OperationController::class, 'update']);
    Route::delete('/operations/{operation}', [OperationController::class, 'destroy']);
    Route::patch('/operations/{operation}/status', [OperationController::class, 'updateStatus']);
    Route::post('/operations/{operation}/start', [OperationController::class, 'start']);
    Route::post('/operations/{operation}/complete', [OperationController::class, 'complete']);
    Route::post('/operations/{operation}/cancel', [OperationController::class, 'cancel']);

    // Operation Templates
    Route::get('/operation-templates', [OperationTemplateController::class, 'index']);
    Route::post('/operation-templates', [OperationTemplateController::class, 'store']);
    Route::put('/operation-templates/{template}', [OperationTemplateController::class, 'update']);
    Route::delete('/operation-templates/{template}', [OperationTemplateController::class, 'destroy']);

    // Operation Participants
    Route::post('/operations/{operation}/join', [OperationParticipantController::class, 'join'])
        ->name('api.operations.join');

    Route::post('/operations/{operation}/leave', [OperationParticipantController::class, 'leave'])
        ->name('api.operations.leave');

    Route::put('/operations/{operation}/participants/{participant}/slot', [OperationParticipantController::class, 'updateSlot'])
        ->name('api.operations.updateSlot');
    Route::put('/operations/{operation}/participants/{participant}/stats', [OperationParticipantController::class, 'updateStats']);

    // Media
    Route::get('/media', [MediaApiController::class, 'index']);
    Route::get('/media/{media}', [MediaApiController::class, 'show']);
    Route::post('/media/upload', [MediaApiController::class, 'upload']);
    Route::delete('/media/{media}', [MediaApiController::class, 'destroy']);
});
