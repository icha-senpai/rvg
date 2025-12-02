<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
            return ApiResponse::error(
                'Too many verification attempts. Please try again in ' . $seconds . ' seconds.',
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
                Log::warning('Unauthorized RSI verification attempt', [
                    'ip' => $request->ip(),
                    'rsi_handle' => $request->rsi_handle
                ]);
                return ApiResponse::error(
                    'Authentication required',
                    [],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 3) Check verification code and expiration
            if (!$user->verification_code) {
                Log::warning('No verification code found for user', [
                    'user_id' => $user->id,
                    'rsi_handle' => $validated['rsi_handle']
                ]);
                return ApiResponse::error(
                    'No verification code found. Please generate a new one.',
                    [],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($user->verification_expires_at === null || now()->greaterThan($user->verification_expires_at)) {
                Log::warning('Verification code expired', [
                    'user_id' => $user->id,
                    'expires_at' => $user->verification_expires_at
                ]);
                return ApiResponse::error(
                    'Verification code has expired. Please generate a new one.',
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
                    Log::error('Failed to fetch RSI profile', [
                        'status' => $response->status(),
                        'url' => $url,
                        'user_id' => $user->id
                    ]);
                    return ApiResponse::error(
                        'Unable to verify RSI profile at this time. Please try again later.',
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
                    Log::warning('No org membership found on RSI profile', [
                        'user_id' => $user->id,
                        'rsi_handle' => $validated['rsi_handle']
                    ]);
                    return ApiResponse::error(
                        'No organization membership found on your RSI profile. Please join the required organization first.',
                        [],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                // Required org check
                $requiredOrg = config('services.rsi.required_org', 'SRN');
                if ($orgCode !== $requiredOrg) {
                    Log::warning('User not in required org', [
                        'user_id' => $user->id,
                        'found_org' => $orgCode,
                        'required_org' => $requiredOrg
                    ]);
                    return ApiResponse::error(
                        'You must be a member of ' . $requiredOrg . ' to verify your account.',
                        [
                            'found_org' => $orgCode,
                            'required_org' => $requiredOrg,
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
                    Log::warning('Verification code not found on profile', [
                        'user_id' => $user->id,
                        'rsi_handle' => $validated['rsi_handle']
                    ]);
                    return ApiResponse::error(
                        'Verification code not found on your RSI profile. Please make sure to add it exactly as shown.',
                        [],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VERIFIED SUCCESSFULLY
                |--------------------------------------------------------------------------
                */
                $user->rsi_handle = $validated['rsi_handle'];
                $user->rsi_verified_at = now();
                $user->global_status = 'active';  // flip from pending → active
                $user->verification_code = null;  // Clear the used code
                $user->verification_expires_at = null;
                $user->save();

                Log::info('User verified successfully', [
                    'user_id' => $user->id,
                    'rsi_handle' => $user->rsi_handle,
                    'org' => $requiredOrg
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
                Log::error('RSI verification error', [
                    'user_id' => $user->id ?? null,
                    'rsi_handle' => $validated['rsi_handle'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return ApiResponse::error(
                    'An error occurred while verifying your RSI profile. Please try again later.',
                    [],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }
}
