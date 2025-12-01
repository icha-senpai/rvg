<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Services\DiscordOAuthService;
use App\Models\User;
use App\Services\TokenService;

class DiscordAuthController extends Controller
{
    protected DiscordOAuthService $discord;
    protected TokenService $tokens;

    public function __construct(DiscordOAuthService $discord, TokenService $tokens)
    {
        $this->discord = $discord;
        $this->tokens = $tokens;
    }

    /**
     * Redirect user to Discord for OAuth login.
     */
    public function redirect()
    {
        return $this->discord->redirect();
    }

    /**
     * Handle the Discord OAuth callback.
     *
     * This unified flow:
     * - Fetches the Discord user
     * - Syncs them to the local User model
     * - Checks RSI verification status
     * - If NOT RSI verified → generates a verification code + returns PENDING_RSI state
     * - If RSI verified → returns VERIFIED state
     *
     * Token issuing is still handled elsewhere (AuthController or /auth/token)
     * so we don't break your current setup in this step.
     */
    public function callback()
    {
        try {
            // Use stateless mode for safety with frontends / SPAs
            $discordUser = $this->discord->getUser(stateless: true);
        } catch (\Exception $e) {
            return ApiResponse::error('OAuth failed', [
                'details' => $e->getMessage(),
            ], 400);
        }

        // Sync basic Discord info (id, name, avatar...)
        $user = $this->discord->syncBasicUser($discordUser);
        $tokens = $this->tokens->createTokensFor($user);
        // Check if RSI is already verified (via your existing flows)
        $isRsiVerified = !is_null($user->rsi_verified_at);

        if ($isRsiVerified) {
            // ✅ User is fully verified (Discord + RSI/org)
            // Frontend can skip onboarding and go straight to dashboard.
            return ApiResponse::success('User authenticated (RSI already verified)', [
                'state' => 'VERIFIED',
                'user'  => $user,
                'tokens' => $tokens,
            ]);
        }

        // ❌ Not RSI verified yet → generate / refresh verification code.
        // This reuses your existing code-issuing logic from DiscordController + VerificationCodeController style.
        [$user, $code] = $this->discord->syncWithVerification($discordUser);

        return ApiResponse::success('Discord linked. RSI verification required.', [
            'state'             => 'PENDING_RSI',
            'user'              => $user,
            'verification_code' => $code,
            'instructions'      => 'Paste this code into your RSI short bio, then call /api/verify-rsi to complete verification.',
            'tokens' => $tokens,
        ]);
    }
}
