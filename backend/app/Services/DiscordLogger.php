<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes structured auth and RSI verification events to the Laravel log while
 * redacting sensitive values.
 */
class DiscordLogger
{
    protected const REDACTED_VALUE = '[REDACTED]';

    /**
     * Keys that should never be written to logs in plaintext.
     */
    protected const SENSITIVE_KEYS = [
        'token',
        'access_token',
        'refresh_token',
        'verification_code',
        'password',
        'secret',
        'authorization',
        'cookie',
        'set_cookie',
    ];

    /**
     * Record an authentication-related event.
     */
    public static function auth(string $action, array $context = [])
    {
        self::log('auth', $action, $context);
    }

    /**
     * Record an RSI verification-related event.
     */
    public static function rsiVerification(string $action, array $context = [])
    {
        self::log('rsi_verification', $action, $context);
    }

    /**
     * Build the final log payload, sanitize it, and write it as structured JSON.
     */
    protected static function log(string $type, string $action, array $context)
    {
        $payload = self::sanitizeContext(array_merge(
            [
                'type' => $type,
                'action' => $action,
                'timestamp' => now()->toIso8601String(),
            ],
            $context,
            ['ip' => request()?->ip() ?? 'cli']
        ));

        Log::channel('single')->info(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Recursively sanitize every value in the log context array.
     */
    protected static function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = self::sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    /**
     * Redact sensitive keys, sanitize nested arrays, and trim long free-form
     * strings so logs remain safe and readable.
     */
    protected static function sanitizeValue(string $key, mixed $value): mixed
    {
        $normalizedKey = Str::lower($key);

        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return self::REDACTED_VALUE;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $childKey = is_string($nestedKey) ? $nestedKey : $key;
                $sanitized[$nestedKey] = self::sanitizeValue($childKey, $nestedValue);
            }

            return $sanitized;
        }

        if (! is_scalar($value) && $value !== null) {
            return '[OMITTED]';
        }

        if (! is_string($value)) {
            return $value;
        }

        // Catch bearer tokens and common key=value secrets embedded inside free
        // text strings before the payload is written to disk.
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9\-._|]+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $sanitized = preg_replace('/(access_token|refresh_token|verification_code|password|secret|authorization|cookie)=([^&\s]+)/i', '$1=[REDACTED]', $sanitized) ?? $sanitized;

        return Str::limit($sanitized, 500, '...');
    }
}
