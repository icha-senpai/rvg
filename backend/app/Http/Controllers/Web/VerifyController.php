<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\DiscordLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VerifyController extends Controller
{
    protected int $maxAttempts = 5;

    protected int $decayMinutes = 1;

    public function show(Request $request)
    {
        $user = $request->user();

        if ($user?->rsi_verified_at) {
            return redirect()->to('/');
        }

        $verificationCode = null;
        $verificationExpiresAt = null;

        if ($user?->verification_code
            && $user->verification_expires_at
            && now()->lessThanOrEqualTo($user->verification_expires_at)) {
            $verificationCode = $user->verification_code;
            $verificationExpiresAt = $user->verification_expires_at->toIso8601String();
        }

        return Inertia::render('Verify', [
            'verification' => [
                'discordVerified' => (bool) $user,
                'rsiVerified' => (bool) $user?->rsi_verified_at,
                'code' => $verificationCode,
                'expiresAt' => $verificationExpiresAt,
                'rsiHandle' => old('rsi_handle', $user?->rsi_handle),
            ],
        ]);
    }

    public function generateCode(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $user->verification_code = $code;
        $user->verification_expires_at = now()->addMinutes(10);
        $user->save();

        return redirect()->route('verify')->with('success', 'Your verification code has been generated successfully. It will expire in 10 minutes.');
    }

    public function verifyRsi(Request $request)
    {
        $throttleKey = 'rsi_verify:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $message = 'Verification limit reached. Please wait ' . $seconds . ' seconds before trying again.';

            DiscordLogger::rsiVerification('rate_limit_exceeded', [
                'ip' => $request->ip(),
                'remaining' => $seconds . ' seconds',
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'verification' => $message,
            ]);
        }

        RateLimiter::hit($throttleKey, $this->decayMinutes * 60);

        try {
            $validated = $request->validate([
                'rsi_handle' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9-_]+$/'],
            ]);

            $user = $request->user();

            if (! $user) {
                DiscordLogger::rsiVerification('unauthorized_attempt', [
                    'ip' => $request->ip(),
                    'rsi_handle' => $validated['rsi_handle'],
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('login');
            }

            if (! $user->verification_code) {
                DiscordLogger::rsiVerification('no_verification_code', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $validated['rsi_handle'],
                ]);

                return back()->withErrors([
                    'verification' => 'No active verification code found. Please generate a new code and try again.',
                ])->withInput();
            }

            if ($user->verification_expires_at === null || now()->greaterThan($user->verification_expires_at)) {
                DiscordLogger::rsiVerification('verification_code_expired', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'expires_at' => $user->verification_expires_at?->toIso8601String(),
                ]);

                return back()->withErrors([
                    'verification' => 'Your verification code has expired. Please generate a new one and try again.',
                ])->withInput();
            }

            $url = 'https://robertsspaceindustries.com/citizens/' . urlencode($validated['rsi_handle']);

            try {
                $response = Http::timeout(10)
                    ->retry(2, 1000)
                    ->get($url);

                if ($response->failed()) {
                    DiscordLogger::rsiVerification('rsi_profile_fetch_failed', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'status_code' => $response->status(),
                        'error' => 'Failed to fetch RSI profile',
                    ]);

                    return back()->withErrors([
                        'verification' => "We're having trouble connecting to RSI right now. Please try again in a few minutes. If the issue persists, please check the RSI website status.",
                    ])->withInput();
                }

                $html = $response->body();
                $orgCode = null;

                if (preg_match('/href="\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                } elseif (preg_match('/href="\/en\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                } elseif (preg_match('/\/en\/orgs\/([A-Z0-9]{2,20})/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                }

                if (! $orgCode) {
                    DiscordLogger::rsiVerification('no_org_membership', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'error' => 'No organization membership found on RSI profile',
                    ]);

                    return back()->withErrors([
                        'verification' => "Organization membership required: We couldn't find any organization linked to your RSI profile. Please join the required organization on RSI and try again.",
                    ])->withInput();
                }

                $requiredOrg = config('services.rsi.required_org', 'SRN');
                if ($orgCode !== $requiredOrg) {
                    DiscordLogger::rsiVerification('wrong_org', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'found_org' => $orgCode,
                        'required_org' => $requiredOrg,
                        'error' => 'User not in required organization',
                    ]);

                    return back()->withErrors([
                        'verification' => 'Organization mismatch: Your RSI profile shows membership in ' . $orgCode . ', but you need to be a member of ' . $requiredOrg . ' to continue.',
                    ])->withInput();
                }

                if (! str_contains($html, $user->verification_code)) {
                    DiscordLogger::rsiVerification('verification_code_not_found', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'verification_code' => $user->verification_code,
                        'error' => 'Verification code not found on RSI profile',
                    ]);

                    return back()->withErrors([
                        'verification' => "We couldn't find the verification code in your RSI profile. Please make sure to copy it exactly, paste it into the About Me section of your RSI profile, save your changes, and try again.",
                    ])->withInput();
                }

                $user->rsi_handle = $validated['rsi_handle'];
                $user->rsi_verified_at = now();
                $user->global_status = 'active';
                $user->verification_code = null;
                $user->verification_expires_at = null;
                $user->save();

                $memberRoleId = Role::where('slug', 'member')->value('id');
                if ($memberRoleId) {
                    $user->roles()->syncWithoutDetaching([$memberRoleId]);
                    Cache::forget("user_roles_{$user->id}");
                    Cache::forget("user_permissions_{$user->id}");
                }

                $this->notifyDiscordBotOfVerification($user);

                DiscordLogger::rsiVerification('verification_success', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $user->rsi_handle,
                    'org' => $requiredOrg,
                    'verified_at' => $user->rsi_verified_at->toIso8601String(),
                ]);

                RateLimiter::clear($throttleKey);

                return redirect()->to('/')->with('success', 'Account verified successfully!');
            } catch (\Exception $e) {
                $errorContext = [
                    'user_id' => $user->id ?? null,
                    'discord_id' => $user->discord_id ?? null,
                    'rsi_handle' => $validated['rsi_handle'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];

                Log::error('RSI verification error', $errorContext);
                unset($errorContext['trace']);
                DiscordLogger::rsiVerification('verification_error', $errorContext);

                return back()->withErrors([
                    'verification' => 'We encountered an issue verifying your RSI profile. Please try again in a few minutes.',
                ])->withInput();
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Outer RSI verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'verification' => "We're having trouble processing your verification right now. Please try again later.",
            ])->withInput();
        }
    }

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
