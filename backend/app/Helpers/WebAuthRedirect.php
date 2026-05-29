<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class WebAuthRedirect
{
    public const INTENDED_URL_SESSION_KEY = 'hz_auth_intended';

    public static function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    public static function shouldIgnore(Request $request): bool
    {
        return self::pathIsIgnored($request->path());
    }

    public static function redirectToVerify(Request $request, bool $rememberIntended = true): Response
    {
        if ($rememberIntended) {
            self::rememberIntendedUrl($request);
        }

        $target = route('verify');

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return redirect()->to($target);
    }

    public static function rememberIntendedUrl(Request $request, ?string $candidate = null): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $candidate ??= self::candidateIntendedUrl($request);

        if (! is_string($candidate) || self::normalizeIntendedUrl($candidate, $request) === null) {
            return;
        }

        $request->session()->put(self::INTENDED_URL_SESSION_KEY, $candidate);
    }

    public static function redirectToIntendedOrFallback(Request $request, string $fallback = '/'): Response
    {
        return redirect()->to(self::consumeIntendedUrl($request, $fallback));
    }

    public static function consumeIntendedUrl(Request $request, string $fallback = '/'): string
    {
        if (! $request->hasSession()) {
            return $fallback;
        }

        $candidate = $request->session()->pull(self::INTENDED_URL_SESSION_KEY);
        if (! is_string($candidate)) {
            return $fallback;
        }

        return self::normalizeIntendedUrl($candidate, $request) ?? $fallback;
    }

    protected static function candidateIntendedUrl(Request $request): ?string
    {
        if (self::shouldIgnore($request)) {
            return null;
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $request->fullUrl();
        }

        $referer = $request->headers->get('referer');

        return is_string($referer) && $referer !== ''
            ? $referer
            : null;
    }

    protected static function normalizeIntendedUrl(string $candidate, Request $request): ?string
    {
        $parts = parse_url(trim($candidate));

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        if (self::pathIsIgnored($path)) {
            return null;
        }

        $candidateHost = $parts['host'] ?? null;

        if ($candidateHost !== null) {
            $rootParts = parse_url($request->root());
            $rootHost = $rootParts['host'] ?? null;
            $rootPort = $rootParts['port'] ?? null;
            $candidatePort = $parts['port'] ?? null;

            if ($rootHost === null || ! hash_equals(strtolower($rootHost), strtolower($candidateHost))) {
                return null;
            }

            if ($candidatePort !== $rootPort) {
                return null;
            }
        } elseif (! str_starts_with($candidate, '/')) {
            return null;
        }

        $normalized = $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    protected static function pathIsIgnored(string $path): bool
    {
        $normalizedPath = ltrim($path, '/');

        return Str::is([
            'verify',
            'verify/*',
            'auth/discord',
            'auth/discord/*',
            'api/*',
        ], $normalizedPath);
    }
}
