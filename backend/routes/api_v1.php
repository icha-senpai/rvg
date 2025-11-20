<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\RSIVerificationController;
use App\Http\Controllers\Api\V1\VerificationCodeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DiscordController;
use App\Http\Controllers\Api\V1\DiscordAuthController;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\AuthController;

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
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/auth/verify-discord', [AuthController::class, 'verifyDiscord'])
    ->middleware('throttle:10,1');



Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// Generate verification code
Route::post('/generate-code', [VerificationCodeController::class, 'generate']);

// Verify RSI handle + org membership + code
Route::post('/verify-rsi', [RSIVerificationController::class, 'verify'])
    ->middleware('throttle:10,1');

// Issue token after successful Discord login
Route::post('/auth/token', function (Request $request) {
    $request->validate([
        'discord_id' => 'required|string',
    ]);

    $user = \App\Models\User::where('discord_id', $request->discord_id)->first();

    if (!$user) {
        return ApiResponse::error('User not found', [], 404);
    }

    // delete old tokens
    $user->tokens()->delete();

    // create token
    $token = $user->createToken('api')->plainTextToken;

    return ApiResponse::success('Token created successfully', [
        'token' => $token,
        'user'  => $user,
    ]);
});



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

Route::middleware(['auth:sanctum'])->group(function () {

    // Rank 1+
    Route::middleware(['rank:1'])->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
    });

    // Rank 2+
    Route::middleware(['rank:2'])->group(function () {
        Route::post('/assign-mission', function () {
            return ['message' => 'Mission assigned'];
        });
    });

    // Rank 3+
    Route::middleware(['rank:3'])->group(function () {

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/verified', [UserController::class, 'verified']);
        Route::get('/users/unverified', [UserController::class, 'unverified']);

        Route::get('/users/{discord_id}', [UserController::class, 'show']);

        Route::delete('/users/{discord_id}', [UserController::class, 'destroy']);

        Route::post('/approve-members', function () {
            return ['message' => 'Member approved'];
        });
    });

    // Rank 4+
    Route::middleware(['rank:4'])->group(function () {
        Route::post('/create-events', function () {
            return ['message' => 'Event created'];
        });
    });

    // Rank 5+
    Route::middleware(['rank:5'])->group(function () {
        Route::post('/internal-directives', function () {
            return ['message' => 'Directive submitted'];
        });
    });

    // Rank 6+
    Route::middleware(['rank:6'])->group(function () {
        Route::post('/manage-branches', function () {
            return ['message' => 'Branch updated'];
        });

        Route::delete('/nuke-system', function () {
            return ['warning' => 'System obliterated (simulation only)'];
        });
    });
});
