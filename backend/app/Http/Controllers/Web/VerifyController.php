<?php

namespace App\Http\Controllers\Web;

use App\Helpers\WebAuthRedirect;
use App\Http\Controllers\Controller;
use App\Services\DiscordLogger;
use App\Services\RsiVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Handles the post-Discord verification screen and the manual RSI verification
 * flow.
 *
 * This controller keeps rate limiting, redirects, and user-facing response
 * behavior in one place while the RSI verification rules live in the service.
 */
class VerifyController extends Controller
{
    protected int $maxAttempts = 5;

    protected int $decayMinutes = 1;

    public function __construct(
        protected RsiVerificationService $verification,
    ) {}

    /**
     * Render the verification screen, including any currently active verification
     * code for the logged-in user.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user?->rsi_verified_at) {
            return WebAuthRedirect::redirectToIntendedOrFallback($request);
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

    /**
     * Generate a fresh RSI verification code for the current user.
     */
    public function generateCode(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return WebAuthRedirect::redirectToVerify($request);
        }

        $this->verification->generateCode($user);

        return redirect()->route('verify')->with('success', 'Your verification code has been generated successfully. It will expire in 10 minutes.');
    }

    /**
     * Verify the current user's RSI account after applying a short IP-based rate
     * limit to slow abusive retry behavior.
     */
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

        // Every attempt increments the limiter before the external RSI lookup so
        // repeated failures are still counted against the short cooldown window.
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

                return WebAuthRedirect::redirectToVerify($request);
            }

            // The domain service performs the actual RSI profile fetch, org check,
            // verification-code lookup, role sync, and bot notification.
            $this->verification->verify($user, $validated['rsi_handle']);

            RateLimiter::clear($throttleKey);

            return WebAuthRedirect::redirectToIntendedOrFallback($request)
                ->with('success', 'Account verified successfully!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            // Unexpected failures are logged server-side and converted into a safe
            // generic message for the user.
            Log::error('Outer RSI verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'verification' => "We're having trouble processing your verification right now. Please try again later.",
            ])->withInput();
        }
    }
}
