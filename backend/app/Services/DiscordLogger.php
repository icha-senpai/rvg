<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DiscordLogger
{
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
        Log::channel('single')->info(json_encode(array_merge(
            [
                'type' => $type,
                'action' => $action,
                'timestamp' => now()->toIso8601String(),
            ],
            $context,
            ['ip' => request()?->ip() ?? 'cli']
        )));
    }
}
