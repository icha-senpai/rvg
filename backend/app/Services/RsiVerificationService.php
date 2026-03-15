<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verifies that a user controls an RSI profile belonging to the required
 * organization and containing the expected short-lived verification code.
 *
 * Successful verification also activates the user account, synchronizes the
 * baseline member role, and notifies the Discord bot.
 */
class RsiVerificationService
{
    /**
     * Generate and persist a short-lived verification code for the given user.
     */
    public function generateCode(User $user): string
    {
        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $user->verification_code = $code;
        $user->verification_expires_at = now()->addMinutes(10);
        $user->save();

        return $code;
    }

    /**
     * Verify the provided RSI handle for the given user.
     *
     * The user must have a live verification code, belong to the required RSI
     * organization, and expose the generated code somewhere in the fetched profile
     * HTML before the account is activated.
     */
    public function verify(User $user, string $rsiHandle): void
    {
        if (! $user->verification_code) {
            DiscordLogger::rsiVerification('no_verification_code', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'rsi_handle' => $rsiHandle,
            ]);

            throw ValidationException::withMessages([
                'verification' => 'No active verification code found. Please generate a new code and try again.',
            ]);
        }

        if ($user->verification_expires_at === null || now()->greaterThan($user->verification_expires_at)) {
            DiscordLogger::rsiVerification('verification_code_expired', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'expires_at' => $user->verification_expires_at?->toIso8601String(),
            ]);

            throw ValidationException::withMessages([
                'verification' => 'Your verification code has expired. Please generate a new one and try again.',
            ]);
        }

        $requiredOrg = config('services.rsi.required_org', 'SRN');

        try {
            $html = $this->fetchProfileHtml($user, $rsiHandle);
            $orgCode = $this->extractOrganizationCode($html);

            if (! $orgCode) {
                DiscordLogger::rsiVerification('no_org_membership', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $rsiHandle,
                    'error' => 'No organization membership found on RSI profile',
                ]);

                throw ValidationException::withMessages([
                    'verification' => "Organization membership required: We couldn't find any organization linked to your RSI profile. Please join the required organization on RSI and try again.",
                ]);
            }

            if ($orgCode !== $requiredOrg) {
                DiscordLogger::rsiVerification('wrong_org', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $rsiHandle,
                    'found_org' => $orgCode,
                    'required_org' => $requiredOrg,
                    'error' => 'User not in required organization',
                ]);

                throw ValidationException::withMessages([
                    'verification' => 'Organization mismatch: Your RSI profile shows membership in ' . $orgCode . ', but you need to be a member of ' . $requiredOrg . ' to continue.',
                ]);
            }

            if (! str_contains($html, $user->verification_code)) {
                DiscordLogger::rsiVerification('verification_code_not_found', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $rsiHandle,
                    'verification_code' => $user->verification_code,
                    'error' => 'Verification code not found on RSI profile',
                ]);

                throw ValidationException::withMessages([
                    'verification' => "We couldn't find the verification code in your RSI profile. Please make sure to copy it exactly, paste it into the About Me section of your RSI profile, save your changes, and try again.",
                ]);
            }

            // A successful verification activates the user and clears the short-
            // lived verification state so the code cannot be replayed later.
            $user->rsi_handle = $rsiHandle;
            $user->rsi_verified_at = now();
            $user->global_status = 'active';
            $user->verification_code = null;
            $user->verification_expires_at = null;
            $user->save();

            // Verification also grants the baseline member role and informs the
            // bot integration that the user is now verified.
            $this->syncMemberRole($user);
            $this->notifyDiscordBotOfVerification($user);

            DiscordLogger::rsiVerification('verification_success', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'rsi_handle' => $user->rsi_handle,
                'org' => $requiredOrg,
                'verified_at' => $user->rsi_verified_at?->toIso8601String(),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $errorContext = [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'rsi_handle' => $rsiHandle,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('RSI verification error', $errorContext);
            unset($errorContext['trace']);
            DiscordLogger::rsiVerification('verification_error', $errorContext);

            throw ValidationException::withMessages([
                'verification' => 'We encountered an issue verifying your RSI profile. Please try again in a few minutes.',
            ]);
        }
    }

    /**
     * Fetch the RSI profile HTML for the given handle.
     */
    protected function fetchProfileHtml(User $user, string $rsiHandle): string
    {
        $url = 'https://robertsspaceindustries.com/citizens/' . urlencode($rsiHandle);

        $response = Http::timeout(10)
            ->retry(2, 1000)
            ->get($url);

        if ($response->failed()) {
            DiscordLogger::rsiVerification('rsi_profile_fetch_failed', [
                'user_id' => $user->id,
                'discord_id' => $user->discord_id,
                'rsi_handle' => $rsiHandle,
                'status_code' => $response->status(),
                'error' => 'Failed to fetch RSI profile',
            ]);

            throw ValidationException::withMessages([
                'verification' => "We're having trouble connecting to RSI right now. Please try again in a few minutes. If the issue persists, please check the RSI website status.",
            ]);
        }

        return $response->body();
    }

    /**
     * Extract the first matching organization code from the RSI profile HTML.
     */
    protected function extractOrganizationCode(string $html): ?string
    {
        if (preg_match('/href="\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            return strtoupper($orgMatch[1]);
        }

        if (preg_match('/href="\/en\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            return strtoupper($orgMatch[1]);
        }

        if (preg_match('/\/en\/orgs\/([A-Z0-9]{2,20})/i', $html, $orgMatch)) {
            return strtoupper($orgMatch[1]);
        }

        return null;
    }

    /**
     * Ensure the verified user has the baseline member application role.
     */
    protected function syncMemberRole(User $user): void
    {
        $memberRoleId = Role::where('slug', 'member')->value('id');

        if (! $memberRoleId) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$memberRoleId]);
        Cache::forget("user_roles_{$user->id}");
        Cache::forget("user_permissions_{$user->id}");
    }

    /**
     * Notify the trusted Discord bot that verification succeeded so Discord-side
     * automation can react to the newly verified user.
     */
    protected function notifyDiscordBotOfVerification(User $user): void
    {
        $botUrl = config('services.bot.url');

        if (! $botUrl || ! $user->discord_id) {
            return;
        }

        try {
            Http::withHeaders([
                'X-Bot-Secret' => config('services.bot.secret'),
            ])
                ->asJson()
                ->post($botUrl . '/verified', [
                    'discord_id' => $user->discord_id,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify Discord bot of RSI verification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
