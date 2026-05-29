<?php

namespace App\Http\Controllers\Web;

use App\Helpers\WebAuthRedirect;
use App\Http\Controllers\Controller;
use App\Services\DiscordGuildMembershipService;
use App\Services\DiscordLogger;
use App\Services\DiscordOAuthService;
use App\Services\DiscordUserSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Coordinates the Discord login flow for the web app.
 *
 * The controller keeps the transport and redirect logic readable while the
 * actual guild-check and user-sync behavior lives in dedicated services.
 */
class DiscordAuthController extends Controller
{
    public function __construct(
        protected DiscordOAuthService $discord,
        protected DiscordGuildMembershipService $guilds,
        protected DiscordUserSyncService $users,
    ) {}

    /**
     * Redirect the browser to Discord's OAuth consent flow.
     */
    public function redirect()
    {
        return $this->discord->redirect();
    }

    /**
     * Handle the Discord OAuth callback.
     *
     * Login succeeds only when the guild membership check returns true. A null
     * result means Discord could not be verified safely, so the user is sent back
     * to the verification screen with a recoverable error state instead of being
     * logged in incorrectly.
     */
    public function callback(Request $request)
    {
        try {
            $discordUser = $this->discord->getUser(stateless: true);
            $inGuild = $this->guilds->checkMembership($discordUser->getId());

            if ($inGuild === null) {
                // Discord may be temporarily unavailable. We log it and bounce the
                // user back to the verify screen instead of guessing.
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

            $user = $this->users->syncBasicUser($discordUser);

            // Only after guild membership is confirmed do we create the session and
            // mark the login flow as started in the session.
            Auth::login($user, remember: false);
            $request->session()->regenerate();
            $request->session()->put('hz_auth_started_at', now()->toIso8601String());

            DiscordLogger::auth('login_success', [
                'user_id' => $user->id,
                'discord_id' => $discordUser->getId(),
                'discord_username' => $discordUser->getNickname() ?? $discordUser->getName(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($user->rsi_verified_at) {
                return WebAuthRedirect::redirectToIntendedOrFallback($request);
            }

            return redirect()->route('verify');
        } catch (\Exception $e) {
            // OAuth failures stay on the verification screen so the frontend can
            // show a safe, user-facing error without exposing the raw exception.
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
