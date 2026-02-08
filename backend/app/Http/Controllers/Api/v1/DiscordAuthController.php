<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use App\Services\TokenService;
use App\Services\DiscordLogger;
use Illuminate\Http\Request;

class DiscordAuthController extends Controller
{
    protected DiscordOAuthService $discord;
    protected TokenService $tokens;

    public function __construct(DiscordOAuthService $discord, TokenService $tokens)
    {
        $this->discord = $discord;
        $this->tokens  = $tokens;
    }

    public function redirect()
    {
        return $this->discord->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $discordUser = $this->discord->getUser(stateless: true);

            if (!$this->discord->checkGuildMembership($discordUser->getId())) {
                DiscordLogger::auth('login_rejected_not_in_guild', [
                    'discord_id' => $discordUser->getId(),
                    'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect('/verify?error=not_in_guild');
            }

            $user = $this->discord->syncBasicUser($discordUser);

            // Create a Laravel session for web routes (Inertia)
            \Illuminate\Support\Facades\Auth::login($user, remember: false);

            $request->session()->put('hz_auth_started_at', now()->toIso8601String());

            // Log successful auth
            DiscordLogger::auth('login_success', [
                'user_id'        => $user->id,
                'discord_id'     => $discordUser->getId(),
                'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                'user_agent'     => $request->userAgent(),
            ]);

            // Issue API tokens for your JS app
            $tokens = $this->tokens->createTokensFor($user);

            return redirect('/verify?' . http_build_query([
                'token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ]));

        } catch (\Exception $e) {

            DiscordLogger::auth('login_failed', [
                'error'      => $e->getMessage(),
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect('/verify?error=oauth');
        }
    }
}

