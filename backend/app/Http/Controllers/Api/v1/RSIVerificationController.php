<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\User;
use App\Models\Role;
use App\Services\DiscordLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Response;

class RSIVerificationController extends Controller
{
    /**
     * Maximum number of attempts allowed per minute for verification
     */
    protected int $maxAttempts = 5;

    /**
     * Decay minutes for rate limiter
     */
    protected int $decayMinutes = 1;

    /**
     * Verify RSI profile with verification code
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $throttleKey = 'rsi_verify:' . $request->ip();

        // Rate limiting check
        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $message = 'Verification limit reached. Please wait ' . $seconds . ' seconds before trying again.';
            
            DiscordLogger::rsiVerification('rate_limit_exceeded', [
                'ip' => $request->ip(),
                'remaining' => $seconds . ' seconds',
                'user_agent' => $request->userAgent()
            ]);
            
            return ApiResponse::error(
                $message,
                [],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        RateLimiter::hit($throttleKey, $this->decayMinutes * 60);

        try {
            // 1) Validate inputs
            $validated = $request->validate([
                'rsi_handle' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9-_]+$/',
            ]);

            // 2) Get authenticated user
            $user = $request->user();
            if (!$user) {
                DiscordLogger::rsiVerification('unauthorized_attempt', [
                    'ip' => $request->ip(),
                    'rsi_handle' => $validated['rsi_handle'],
                    'user_agent' => $request->userAgent()
                ]);
                
                return ApiResponse::error(
                    'Please log in to verify your RSI account.',
                    [],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 3) Check verification code and expiration
            if (!$user->verification_code) {
                DiscordLogger::rsiVerification('no_verification_code', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $validated['rsi_handle']
                ]);
                
                return ApiResponse::error(
                    'No active verification code found. Please generate a new code and try again.',
                    [],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($user->verification_expires_at === null || now()->greaterThan($user->verification_expires_at)) {
                DiscordLogger::rsiVerification('verification_code_expired', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'expires_at' => $user->verification_expires_at?->toIso8601String()
                ]);
                
                return ApiResponse::error(
                    'Your verification code has expired. Please generate a new one and try again.',
                    [],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 4) Fetch RSI profile HTML with retry logic
            $url = 'https://robertsspaceindustries.com/citizens/' . urlencode($validated['rsi_handle']);
            
            try {
                $response = Http::timeout(10)
                    ->retry(2, 1000) // Retry twice with 1 second delay
                    ->get($url);

                if ($response->failed()) {
                    DiscordLogger::rsiVerification('rsi_profile_fetch_failed', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'status_code' => $response->status(),
                        'error' => 'Failed to fetch RSI profile'
                    ]);
                    
                    return ApiResponse::error(
                        "We're having trouble connecting to RSI right now. Please try again in a few minutes. If the issue persists, please check the RSI website status.",
                        [],
                        Response::HTTP_SERVICE_UNAVAILABLE
                    );
                }

                $html = $response->body();

                /*
                |--------------------------------------------------------------------------
                | ORG EXTRACTION
                |--------------------------------------------------------------------------
                */
                $orgCode = null;
                
                // Pattern A: sidebar org link
                if (preg_match('/href="\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                }
                // Pattern B: /en/orgs/XYZ
                elseif (preg_match('/href="\/en\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                }
                // Pattern C: generic /en/orgs/XXX somewhere
                elseif (preg_match('/\/en\/orgs\/([A-Z0-9]{2,20})/i', $html, $orgMatch)) {
                    $orgCode = strtoupper($orgMatch[1]);
                }

                if (!$orgCode) {
                    DiscordLogger::rsiVerification('no_org_membership', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'error' => 'No organization membership found on RSI profile'
                    ]);
                    
                    return ApiResponse::error(
                        "Organization membership required: We couldn't find any organization linked to your RSI profile. Please join the required organization on RSI and try again.",
                        [
                            'help_link' => 'https://robertsspaceindustries.com/orgs/' . config('services.rsi.required_org', 'SRN')
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                // Required org check
                $requiredOrg = config('services.rsi.required_org', 'SRN');
                if ($orgCode !== $requiredOrg) {
                    DiscordLogger::rsiVerification('wrong_org', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'found_org' => $orgCode,
                        'required_org' => $requiredOrg,
                        'error' => 'User not in required organization'
                    ]);
                    
                    return ApiResponse::error(
                        'Organization mismatch: Your RSI profile shows membership in ' . $orgCode . ', but you need to be a member of ' . $requiredOrg . ' to continue.',
                        [
                            'found_org' => $orgCode,
                            'required_org' => $requiredOrg,
                            'help_link' => 'https://robertsspaceindustries.com/orgs/' . $requiredOrg
                        ],
                        Response::HTTP_FORBIDDEN
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CODE CHECK: verify the code is on the profile
                |--------------------------------------------------------------------------
                */
                if (!str_contains($html, $user->verification_code)) {
                    DiscordLogger::rsiVerification('verification_code_not_found', [
                        'user_id' => $user->id,
                        'discord_id' => $user->discord_id,
                        'rsi_handle' => $validated['rsi_handle'],
                        'verification_code' => $user->verification_code,
                        'error' => 'Verification code not found on RSI profile'
                    ]);
                    
                    return ApiResponse::error(
                        "We couldn't find the verification code in your RSI profile. Please make sure to:"
                        . "\n1. Copy the code exactly as shown"
                        . "\n2. Paste it into the 'About Me' section of your RSI profile"
                        . "\n3. Click 'Save Changes'"
                        . "\n4. Try verifying again",
                        [
                            'verification_code' => $user->verification_code,
                            'help_link' => 'https://robertsspaceindustries.com/account/profile'
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VERIFIED SUCCESSFULLY
                |--------------------------------------------------------------------------
                */
                // Update user verification status
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

                // Log successful verification
                DiscordLogger::rsiVerification('verification_success', [
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $user->rsi_handle,
                    'org' => $requiredOrg,
                    'verified_at' => $user->rsi_verified_at->toIso8601String()
                ]);

                // Clear rate limiter on successful verification
                RateLimiter::clear($throttleKey);

                return ApiResponse::success('Account verified successfully!', [
                    'discord_id' => $user->discord_id,
                    'rsi_handle' => $user->rsi_handle,
                    'org' => $requiredOrg,
                    'verified_at' => $user->rsi_verified_at->toIso8601String(),
                ]);

            } catch (\Exception $e) {
                // Log the error to both Laravel log and Discord
                $errorContext = [
                    'user_id' => $user->id ?? null,
                    'discord_id' => $user->discord_id ?? null,
                    'rsi_handle' => $validated['rsi_handle'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
                
                Log::error('RSI verification error', $errorContext);
                
                // Send to Discord without the full trace to avoid hitting character limits
                unset($errorContext['trace']);
                DiscordLogger::rsiVerification('verification_error', $errorContext);

                return ApiResponse::error(
                    "We encountered an issue verifying your RSI profile. Our team has been notified. Please try again in a few minutes.",
                    [
                        'support_contact' => 'support@yourdomain.com',
                        'error_reference' => 'ERR-' . time()
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        } catch (\Exception $e) {
            // This handles any exceptions in the outer try block
            Log::error('Outer RSI verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ApiResponse::error(
                "We're having trouble processing your verification right now. Please try again later or contact support if the issue persists.",
                [
                    'support_contact' => 'support@yourdomain.com',
                    'error_reference' => 'SYS-ERR-' . time()
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Notify the Discord bot about a successful verification
     *
     * @param \App\Models\User $user
     * @return void
     */
    protected function notifyDiscordBotOfVerification(User $user): void
    {
        $botUrl = config('services.bot.url');

        if (!$botUrl || !$user->discord_id) {
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
            // Log, but do not break verification
            Log::warning('Failed to notify Discord bot of RSI verification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
