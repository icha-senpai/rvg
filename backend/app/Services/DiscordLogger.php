<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordLogger
{
    protected const REDACTED_VALUE = '[REDACTED]';

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

    public static function auth(string $action, array $context = [])
    {
        self::log('auth', $action, $context);
    }

    public static function rsiVerification(string $action, array $context = [])
    {
        self::log('rsi_verification', $action, $context);
    }

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

    protected static function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = self::sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

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

        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9\-._|]+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $sanitized = preg_replace('/(access_token|refresh_token|verification_code|password|secret|authorization|cookie)=([^&\s]+)/i', '$1=[REDACTED]', $sanitized) ?? $sanitized;

        return Str::limit($sanitized, 500, '...');
    }
}
