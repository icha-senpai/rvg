<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),
        'bot_token'     => env('DISCORD_BOT_TOKEN'),
        'bot_secret' => env('DISCORD_BOT_SECRET'),
        'guild_id'      => env('DISCORD_GUILD_ID'),
        'guild_check' => filter_var(env('DISCORD_GUILD_CHECK', true), FILTER_VALIDATE_BOOLEAN),
        'frontend_redirect' => env('DISCORD_FRONTEND_REDIRECT'),
        'scopes' => ['identify', 'guilds'],
        'role_sync_cache_minutes' => (int) env('DISCORD_ROLE_SYNC_CACHE_MINUTES', 5),
        'self_assignable_roles' => [
            'branches' => [
                [
                    'id' => env('DISCORD_ROLE_BRANCH_LIFELINE_ID', '1454412925312696331'),
                    'label' => 'Lifeline',
                ],
                [
                    'id' => env('DISCORD_ROLE_BRANCH_INDUSTRIES_ID', '1454412922569621618'),
                    'label' => 'Industries',
                ],
                [
                    'id' => env('DISCORD_ROLE_BRANCH_DEFENCE_ID', '1454412920963203167'),
                    'label' => 'Defence',
                ],
                [
                    'id' => env('DISCORD_ROLE_BRANCH_FRONTIERS_ID', '1454412923790299178'),
                    'label' => 'Frontiers',
                ],
            ],
            'player_roles' => [
                [
                    'id' => env('DISCORD_ROLE_PLAYER_RESCUE_TEAM_ID', '1454412926533373994'),
                    'label' => 'Rescue Team',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_SECURITY_TEAM_ID', '1454412927758106730'),
                    'label' => 'Secrurity Team',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_NEWS_ID', '1454441953046298666'),
                    'label' => 'News',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_LEAKS_ID', '1454412930127757372'),
                    'label' => 'Leaks',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_PLAY_ID', '1454412928722931870'),
                    'label' => 'Play',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_ROLEPLAY_ID', '1454412931159822358'),
                    'label' => 'Roleplay',
                ],
                [
                    'id' => env('DISCORD_ROLE_PLAYER_CLUB_ID', '1454442919573328108'),
                    'label' => 'Club',
                ],
            ],
        ],
    ],

    'rsi' => [
        'required_org' => env('RSI_REQUIRED_ORG', 'SRN'),
        'verification_timeout' => env('RSI_VERIFICATION_TIMEOUT', 10),
    ],
    'bot' => [
    'secret' => env('DISCORD_BOT_SECRET'),
    'url' => env('BOT_WEBHOOK_URL', 'http://localhost:3001/bot'),
    ],
    'uex' => [
        'base_url' => env('UEX_API_BASE_URL', 'https://api.uexcorp.uk/2.0'),
        'token' => env('UEX_API_TOKEN'),
        'timeout' => (int) env('UEX_API_TIMEOUT', 20),
        'client_version' => env('UEX_CLIENT_VERSION'),
        'sync_memory_limit' => env('UEX_SYNC_MEMORY_LIMIT', '512M'),
    ],
    'ledger' => [
        'enabled' => filter_var(env('LEDGER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'preview_user_ids' => array_values(array_filter(array_map(
            fn ($value) => is_numeric($value) ? (int) $value : null,
            explode(',', (string) env('LEDGER_PREVIEW_USER_IDS', ''))
        ))),
    ],

];
