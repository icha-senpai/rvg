<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DiscordLogger;
use App\Services\DiscordOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscordAuthController extends Controller
{
    public function __construct(
        protected DiscordOAuthService $discord,
    ) {}

    public function redirect()
    {
        return $this->discord->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $discordUser = $this->discord->getUser(stateless: true);
            $inGuild = $this->discord->checkGuildMembership($discordUser->getId());

            if ($inGuild === null) {
                DiscordLogger::auth('login_guild_check_unavailable', [
                    'discord_id' => $discordUser->getId(),
                    'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('verify', [
                    'error' => 'discord_check_unavailable',
                ]);
            }

            if ($inGuild === false) {
                DiscordLogger::auth('login_rejected_not_in_guild', [
                    'discord_id' => $discordUser->getId(),
                    'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('verify', [
                    'error' => 'not_in_guild',
                ]);
            }

            $user = $this->discord->syncBasicUser($discordUser);

            Auth::login($user, remember: false);
            $request->session()->regenerate();
            $request->session()->put('hz_auth_started_at', now()->toIso8601String());

            DiscordLogger::auth('login_success', [
                'user_id' => $user->id,
                'discord_id' => $discordUser->getId(),
                'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('verify');
        } catch (\Exception $e) {
            DiscordLogger::auth('login_failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('verify', [
                'error' => 'oauth',
            ]);
        }
    }
}
