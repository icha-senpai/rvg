<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Requests\DiscordVerifyRequest;
use App\Http\Requests\RsiVerifyRequest;


class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
    $data = $request->validated();

    $user = \App\Models\User::where('email', $data['email'])->first();

    if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
        return response()->json([
            'message' => 'Invalid credentials.',
        ], 401);
    }

    // Optional: ensure user is globally active
    if ($user->global_status !== 'active') {
        return response()->json([
            'message' => 'Account not active.',
        ], 403);
    }

    // Create new Sanctum token for this login session (48h expiry auto-applies)
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful.',
        'user' => $user,
        'token' => $token,
        'token_type' => 'Bearer',
    ]);
    }

    public function verifyDiscord(DiscordVerifyRequest $request)
    {
    $data = $request->validated();

    // Make sure this Discord ID is not already linked
    $existing = \App\Models\User::where('discord_id', $data['discord_id'])->first();
    if ($existing) {
        return response()->json([
            'message' => 'Discord account already linked.',
        ], 409);
    }

    $user = auth()->user(); // Whoever is authenticated

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    // Save Discord info to the user record
    $user->discord_id = $data['discord_id'];
    $user->discord_username = $data['username'];
    $user->save();

    return response()->json([
        'message' => 'Discord verified successfully.',
    ]);
    }

    public function verifyRsi(RsiVerifyRequest $request)
    {
    $data = $request->validated();

    // Ensure handle not used by another user
    $existing = \App\Models\User::where('rsi_handle', $data['rsi_handle'])->first();
    if ($existing) {
        return response()->json([
            'message' => 'RSI handle already linked.',
        ], 409);
    }

    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    // Save RSI info
    $user->rsi_handle = $data['rsi_handle'];
    $user->save();

    return response()->json([
        'message' => 'RSI verification successful.',
    ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // If no user is authenticated (invalid/expired token)
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $currentToken = $user->currentAccessToken();

        // Token exists but isn't the one used by this request or is already deleted
        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }


    public function refresh(Request $request)
    {
        // Extract the Bearer token string
        $rawToken = $request->bearerToken();

        if (!$rawToken) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Look up the token in the Sanctum personal_access_tokens table
        $pat = PersonalAccessToken::findToken($rawToken);

        // If no token row found, or it's expired, reject it
        if (!$pat || ($pat->expires_at && $pat->expires_at->isPast())) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Get the user associated with this token
        $user = $pat->tokenable;

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Delete the old token used in this request
        $pat->delete();

        // Issue a fresh 48-hour token
        $newToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully.',
            'token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }
}
