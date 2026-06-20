<?php

namespace App\Http\Controllers\Web;

use App\Helpers\WebAuthRedirect;
use App\Http\Controllers\Controller;
use App\Services\DevAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevAuthController extends Controller
{
    public function __construct(
        protected DevAuthService $devAuth,
    ) {}

    public function index(Request $request)
    {
        $this->devAuth->ensureEnabled();

        $personas = collect($this->devAuth->personas())
            ->map(function (array $definition, string $slug) {
                return [
                    'slug' => $slug,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'verified' => (bool) ($definition['verified'] ?? false),
                    'login_url' => route('dev.auth.login', ['persona' => $slug]),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'enabled' => true,
            'personas' => $personas,
            'verify_urls' => [
                'discord_step' => route('verify'),
                'discord_not_in_guild' => route('verify', ['error' => 'not_in_guild']),
                'discord_check_unavailable' => route('verify', ['error' => 'discord_check_unavailable']),
                'discord_oauth_error' => route('verify', ['error' => 'oauth']),
                'rsi_step' => route('dev.auth.login', [
                    'persona' => 'verify_preview',
                    'redirect' => route('verify', absolute: false),
                ]),
            ],
            'current_user' => $request->user()?->only([
                'id',
                'name',
                'email',
                'discord_id',
                'discord_name',
                'rsi_handle',
                'rank',
                'rank_level',
                'rsi_verified_at',
            ]),
        ]);
    }

    public function login(Request $request, string $persona)
    {
        $this->devAuth->ensureEnabled();

        $redirect = trim((string) $request->query('redirect', ''));
        if ($redirect !== '') {
            WebAuthRedirect::rememberIntendedUrl($request, $redirect);
        }

        $user = $this->devAuth->ensurePersona($persona);

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $request->session()->put('hz_auth_started_at', now()->toIso8601String());

        if ($user->rsi_verified_at) {
            return WebAuthRedirect::redirectToIntendedOrFallback($request);
        }

        return redirect()->route('verify');
    }

    public function logout(Request $request)
    {
        $this->devAuth->ensureEnabled();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('verify');
    }
}
