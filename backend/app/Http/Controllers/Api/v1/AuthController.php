<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Domain\AccessControl\RoleHierarchy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Requests\DiscordVerifyRequest;
use App\Http\Requests\RsiVerifyRequest;
use App\Http\Requests\LoginRequest;
use App\Traits\HandlesFailedAttempts;
use App\Traits\LogsAuthEvents;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use HandlesFailedAttempts;
    use LogsAuthEvents;

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $ip = $request->ip();

        // 1. Check lockout
        if ($locked = $this->isLockedOut('login', null, $ip)) {
            $this->logAuthEvent('login.locked', null, [
                'ip' => $ip,
                'locked_until' => $locked
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Too many failed login attempts.',
                'locked_until' => $locked,
            ], 429);
        }

        // 2. Attempt login
        $user = \App\Models\User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            $this->recordFailure('login', null, $ip);
            $this->logAuthEvent('login.failed', $user?->id, [
                'email' => $data['email'],
                'ip' => $ip
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // 3. On success → clear failures
        $this->clearFailures('login', null, $ip);

        // 4. Check account status
        if ($user->global_status !== 'active') {
            $this->logAuthEvent('login.rejected_inactive', $user->id, [
                'global_status' => $user->global_status
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Account not active.',
                'payload' => null,
            ], 403);
        }

        $discord = app(\App\Services\DiscordOAuthService::class);

        if (!$discord->checkGuildMembership($user->discord_id)) {
            $this->logAuthEvent('login.rejected_not_in_guild', $user->id, [
                'ip' => $ip,
                'discord_id' => $user->discord_id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. You must be in the org Discord to log in.',
                'state' => 'NOT_IN_GUILD',
                'payload' => null,
            ], 403);
        }

        // 5. Issue token
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logAuthEvent('login.success', $user->id, [
            'ip' => $ip
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
            'token_type' => 'Bearer',
            'payload' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }


    public function verifyDiscord(DiscordVerifyRequest $request)
    {
        $data = $request->validated();
        $ip = $request->ip();
        $user = $request->user();

        if (!$user) {
            $this->logAuthEvent('discord.verify.unauthenticated', null, ['ip' => $ip]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 1. Check lockout
        if ($locked = $this->isLockedOut('discord-verify', $user->id, $ip)) {
            $this->logAuthEvent('discord.verify.locked', $user->id, [
                'ip' => $ip,
                'locked_until' => $locked
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Too many failed attempts.',
                'locked_until' => $locked,
            ], 429);
        }

        // 2. Make sure Discord ID is unique
        $existing = \App\Models\User::where('discord_id', $data['discord_id'])->first();
        if ($existing) {
            $this->recordFailure('discord-verify', $user->id, $ip);

            $this->logAuthEvent('discord.verify.failed_duplicate', $user->id, [
                'ip' => $ip,
                'discord_id' => $data['discord_id']
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Discord account already linked.',
            ], 409);
        }

        // 3. Save Discord link
        $user->discord_id = $data['discord_id'];
        $user->discord_username = $data['username'];
        $user->save();

        // 4. Success → clear failures
        $this->clearFailures('discord-verify', $user->id, $ip);

        $this->logAuthEvent('discord.verify.success', $user->id, [
            'ip' => $ip,
            'discord_id' => $data['discord_id']
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Discord verification successful.',
        ]);
    }


    public function verifyRsi(RsiVerifyRequest $request)
    {
        $data = $request->validated();
        $ip = $request->ip();
        $user = $request->user();

        if (!$user) {
            $this->logAuthEvent('rsi.verify.unauthenticated', null, ['ip' => $ip]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 1. Check lockout
        if ($locked = $this->isLockedOut('rsi-verify', $user->id, $ip)) {
            $this->logAuthEvent('rsi.verify.locked', $user->id, [
                'ip' => $ip,
                'locked_until' => $locked
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Too many failed attempts.',
                'locked_until' => $locked,
            ], 429);
        }

        // 2. Make sure RSI handle is unique
        $existing = \App\Models\User::where('rsi_handle', $data['rsi_handle'])->first();
        if ($existing) {
            $this->recordFailure('rsi-verify', $user->id, $ip);

            $this->logAuthEvent('rsi.verify.failed_duplicate', $user->id, [
                'ip' => $ip,
                'rsi_handle' => $data['rsi_handle']
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'RSI handle already linked.',
            ], 409);
        }

        // 3. Save RSI handle
        $user->rsi_handle = $data['rsi_handle'];
        $user->save();

        // 4. Success → clear failures
        $this->clearFailures('rsi-verify', $user->id, $ip);

        $this->logAuthEvent('rsi.verify.success', $user->id, [
            'ip' => $ip,
            'rsi_handle' => $data['rsi_handle']
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'RSI verification successful.',
        ]);
    }


    public function logout(Request $request)
    {
        $rawToken = $request->bearerToken();

        if (!$rawToken) {
            $this->logAuthEvent('logout.no_token', null);
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $pat = PersonalAccessToken::findToken($rawToken);

        if (!$pat) {
            $this->logAuthEvent('logout.invalid_token', null);
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $user = $pat->tokenable;
        $userId = $user?->id;

        $pat->delete();

        $this->logAuthEvent('logout.success', $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }


    // NOTE: unused — TokenController handles refresh.
    public function refresh(Request $request)
    {
        $rawToken = $request->bearerToken();

        if (!$rawToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated (no token).'
            ], 401);
        }

        // Find the refresh token model
        $pat = PersonalAccessToken::findToken($rawToken);

        if (!$pat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated (invalid token).'
            ], 401);
        }

    // Must be a refresh token
        if ($pat->name !== 'refresh_token') {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token type. Must use refresh token.',
                'state'   => 'WRONG_TOKEN_TYPE'
            ], 401);
        }

    // Check expiration
        if ($pat->expires_at && $pat->expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Refresh token expired.',
                'state'   => 'EXPIRED_REFRESH'
            ], 401);
        }

    // Identify the user
        $user = $pat->tokenable;

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated (no user).'
            ], 401);
        }

    // -----------------------------------------------------
    // 🔥 DISCORD GUILD CHECK
    // -----------------------------------------------------

        $discord = app(\App\Services\DiscordOAuthService::class);
        $guildId = config('services.discord.guild_id');

        if (!$guildId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access token refresh unavailable (missing Discord guild id).',
                'state'   => 'MISSING_GUILD_ID'
            ], 500);
        }

        $stillInGuild = $discord->isMemberOfGuild($user->discord_id, $guildId);

        if (!$stillInGuild) {

        // Revoke ALL user tokens immediately
        $user->tokens()->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Access revoked. You are no longer in the org Discord.',
                'state'   => 'LEFT_GUILD'
            ], 403);
        }

    // -----------------------------------------------------
    // 🔥 ROTATE ACCESS TOKEN ONLY
    // -----------------------------------------------------

        // Delete old access tokens only
        $user->tokens()
            ->where('name', 'access_token')
            ->delete();

        // Determine expiration based on rank
        $user->loadMissing('roles:id,slug');
        $isLeadership = RoleHierarchy::userAtLeast($user, 'lieutenant');
        $accessExpires = now()->addDay();

        // Issue new access token
        $newAccess = $user->createToken(
            'access_token',
        ['access'],
        $accessExpires
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message'       => 'Access token refreshed.',
            'access_token'  => $newAccess,
            'expires_in'    => 86400,
            'rank'          => $user->rank,
            'leadership'    => $isLeadership
        ]);
    }


}
