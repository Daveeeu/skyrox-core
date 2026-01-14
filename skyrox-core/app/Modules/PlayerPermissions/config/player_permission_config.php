<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Player Permission Configuration
    |--------------------------------------------------------------------------
    |
    | Hytale szerver játékos jogosultság kezelés konfigurációs beállításai
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Redis Cache Settings
    |--------------------------------------------------------------------------
    |
    | Redis cache beállítások a gyors adateléréshez
    |
    */
    'redis' => [
        'enabled' => env('PLAYER_PERMISSION_REDIS_ENABLED', true),
        'ttl' => env('PLAYER_PERMISSION_REDIS_TTL', 3600), // 1 óra
        'prefix' => env('PLAYER_PERMISSION_REDIS_PREFIX', 'hytale:player:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    |
    | Játékos session kezelés beállításai
    |
    */
    'session' => [
        'auto_logout_inactive' => env('PLAYER_SESSION_AUTO_LOGOUT', true),
        'inactive_timeout' => env('PLAYER_SESSION_TIMEOUT', 1800), // 30 perc
        'max_sessions_per_user' => env('PLAYER_MAX_SESSIONS', 1),
        'track_ip_address' => env('PLAYER_TRACK_IP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Settings
    |--------------------------------------------------------------------------
    |
    | Jogosultság kezelés beállításai
    |
    */
    'permissions' => [
        'default_role' => env('PLAYER_DEFAULT_ROLE', 'guest'),
        'cache_permissions' => env('PLAYER_CACHE_PERMISSIONS', true),
        'case_sensitive' => env('PLAYER_PERMISSION_CASE_SENSITIVE', false),
        'enable_wildcard' => env('PLAYER_PERMISSION_WILDCARD', true), // pl: admin.*
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | API specifikus beállítások
    |
    */
    'api' => [
        'rate_limit' => env('PLAYER_API_RATE_LIMIT', '60,1'), // 60 kérés / perc
        'require_authentication' => env('PLAYER_API_AUTH_REQUIRED', false),
        'allowed_ips' => env('PLAYER_API_ALLOWED_IPS', null), // comma separated IPs
        'api_key' => env('PLAYER_API_KEY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Naplózás beállításai
    |
    */
    'logging' => [
        'enabled' => env('PLAYER_LOGGING_ENABLED', true),
        'log_logins' => env('PLAYER_LOG_LOGINS', true),
        'log_logouts' => env('PLAYER_LOG_LOGOUTS', true),
        'log_permission_checks' => env('PLAYER_LOG_PERMISSION_CHECKS', false),
        'log_role_changes' => env('PLAYER_LOG_ROLE_CHANGES', true),
        'log_channel' => env('PLAYER_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Biztonsági beállítások
    |
    */
    'security' => [
        'encrypt_sessions' => env('PLAYER_ENCRYPT_SESSIONS', true),
        'validate_user_agent' => env('PLAYER_VALIDATE_USER_AGENT', false),
        'validate_ip_change' => env('PLAYER_VALIDATE_IP_CHANGE', false),
        'max_login_attempts' => env('PLAYER_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('PLAYER_LOCKOUT_DURATION', 900), // 15 perc
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Teljesítmény optimalizálási beállítások
    |
    */
    'performance' => [
        'eager_load_permissions' => env('PLAYER_EAGER_LOAD_PERMISSIONS', true),
        'cache_user_roles' => env('PLAYER_CACHE_USER_ROLES', true),
        'batch_permission_checks' => env('PLAYER_BATCH_PERMISSION_CHECKS', true),
        'use_database_cache' => env('PLAYER_USE_DB_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Értesítési beállítások
    |
    */
    'notifications' => [
        'enabled' => env('PLAYER_NOTIFICATIONS_ENABLED', false),
        'notify_login' => env('PLAYER_NOTIFY_LOGIN', false),
        'notify_logout' => env('PLAYER_NOTIFY_LOGOUT', false),
        'notify_role_change' => env('PLAYER_NOTIFY_ROLE_CHANGE', true),
        'notification_channels' => ['mail', 'database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatikus adattisztítási beállítások
    |
    */
    'cleanup' => [
        'enabled' => env('PLAYER_CLEANUP_ENABLED', true),
        'old_sessions_days' => env('PLAYER_CLEANUP_SESSIONS_DAYS', 30),
        'inactive_users_days' => env('PLAYER_CLEANUP_INACTIVE_USERS_DAYS', 90),
        'run_cleanup_daily' => env('PLAYER_CLEANUP_DAILY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Külső integrációk beállításai
    |
    */
    'integrations' => [
        'discord' => [
            'enabled' => env('PLAYER_DISCORD_INTEGRATION', false),
            'webhook_url' => env('PLAYER_DISCORD_WEBHOOK', null),
            'notify_events' => ['login', 'logout', 'role_change'],
        ],
        'webhooks' => [
            'enabled' => env('PLAYER_WEBHOOKS_ENABLED', false),
            'endpoints' => [
                'login' => env('PLAYER_WEBHOOK_LOGIN', null),
                'logout' => env('PLAYER_WEBHOOK_LOGOUT', null),
            ],
        ],
    ],
];
