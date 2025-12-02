<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\RSIVerificationController;
use App\Http\Controllers\Api\V1\VerificationCodeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DiscordAuthController;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SquadronController;
use App\Http\Controllers\Api\V1\SquadronMemberController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventMemberController;
use App\Http\Controllers\Api\V1\EventRoleController;
use App\Http\Controllers\Api\V1\MissionController;
use App\Http\Controllers\Api\V1\MissionMemberController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MePreferenceController;
use App\Http\Controllers\Api\V1\RsiHandleController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\OperationController;
use App\Http\Controllers\Api\V1\OperationParticipantController;
/*
|--------------------------------------------------------------------------
| Public API Endpoints (No Auth Required)
|--------------------------------------------------------------------------
|
| These remain open so Discord bot and WordPress can use them without rank.
|
*/
/*
|--------------------------------------------------------------------------
| Bot API Routes
|--------------------------------------------------------------------------
|
| These routes are used by the Discord bot to interact with the application.
| They are protected by the 'api' and 'bot' middleware groups.
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

//Route::get('/auth/discord/redirect', [DiscordAuthController::class, 'redirect']);
//Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback']);

Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/refresh', [TokenController::class, 'refresh'])
     ->middleware('throttle:20,1');

// 🔐 These require Sanctum auth (token from Discord login)
Route::middleware(['auth:sanctum'])->group(function () {

    // Generate verification code (uses auth()->user())
    Route::post('/generate-code', [VerificationCodeController::class, 'generate'])
        ->middleware('throttle:10,1');

    // Verify RSI handle + org membership + code
    Route::post('/verify-rsi', [RSIVerificationController::class, 'verify'])
        ->middleware('throttle:10,1');
});

// Issue token after successful Discord login
//Route::post('/auth/token', function (Request $request) {
   // $request->validate([
    //    'discord_id' => 'required|string',
   // ]);

   // $user = \App\Models\User::where('discord_id', $request->discord_id)->first();

   // if (!$user) {
   //     return ApiResponse::error('User not found', [], 404);
  //  }

    // delete old tokens
   // $user->tokens()->delete();

    // create token
 //   $token = $user->createToken('api')->plainTextToken;

 //   return ApiResponse::success('Token created successfully', [
 //       'token' => $token,
 //       'user'  => $user,
 //   ]);
// });



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

    Route::middleware('auth:sanctum')->group(function () {


        // Me
        Route::get('/me', [MeController::class, 'show']);
        Route::put('/me', [MeController::class, 'update']);

        // /me/preferences endpoints
        Route::get('/me/preferences', [MePreferenceController::class, 'show']);
        Route::put('/me/preferences', [MePreferenceController::class, 'update']);
        Route::patch('/me/preferences', [MePreferenceController::class, 'update']);

        // member-only create
        Route::post('/rsi-requests', [RsiHandleController::class, 'store']);

        //officcer
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


        // New unified operations API
        Route::get('/operations', [OperationController::class, 'index']);
        Route::get('/operations/{operation}', [OperationController::class, 'show']);

        // Optionally: create under squadron
        Route::post('/squadrons/{squadron}/operations', [OperationController::class, 'store']);

        Route::put('/operations/{operation}', [OperationController::class, 'update']);
        Route::delete('/operations/{operation}', [OperationController::class, 'destroy']);
        Route::patch('/operations/{operation}/status', [OperationController::class, 'updateStatus']);

        // Participants
        Route::post('/operations/{operation}/join', [OperationParticipantController::class, 'join']);
        Route::post('/operations/{operation}/leave', [OperationParticipantController::class, 'leave']);
        Route::put('/operations/{operation}/participants/{participant}/slot', [OperationParticipantController::class, 'updateSlot']);
        Route::put('/operations/{operation}/participants/{participant}/stats', [OperationParticipantController::class, 'updateStats']);

    
    
    });

});
