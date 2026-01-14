<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hytale Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Ez a konfiguráció a Hytale OAuth2 autentikációs rendszerhez.
    | A hivatalos Hytale Server Provider Authentication Guide alapján.
    |
    */

    'oauth2' => [
        // Előre konfigurált Hytale client
        'client_id' => env('HYTALE_CLIENT_ID', 'hytale-server'),
        'scope' => env('HYTALE_OAUTH_SCOPE', 'openid offline auth:server'),

        // Hivatalos Hytale OAuth2 endpoints
        'base_url' => env('HYTALE_OAUTH_BASE_URL', 'https://oauth.accounts.hytale.com'),
        'authorize_url' => env('HYTALE_AUTHORIZE_URL', 'https://oauth.accounts.hytale.com/oauth2/auth'),
        'token_url' => env('HYTALE_TOKEN_URL', 'https://oauth.accounts.hytale.com/oauth2/token'),
        'device_auth_url' => env('HYTALE_DEVICE_AUTH_URL', 'https://oauth.accounts.hytale.com/oauth2/device/auth'),

        // Stage environment (dev/test)
        'stage_domain' => env('HYTALE_STAGE_DOMAIN', 'arcanitegames.ca'),
        'use_stage' => env('HYTALE_USE_STAGE', false),
    ],

    'hytale_api' => [
        // Hytale Session & Account API endpoints
        'session_url' => env('HYTALE_SESSION_URL', 'https://sessions.hytale.com'),
        'account_url' => env('HYTALE_ACCOUNT_URL', 'https://account-data.hytale.com'),
        'jwks_url' => env('HYTALE_JWKS_URL', 'https://sessions.hytale.com/.well-known/jwks.json'),
        'device_verification_url' => env('HYTALE_DEVICE_URL', 'https://accounts.hytale.com/device'),

        // API beállítások
        'timeout' => env('HYTALE_API_TIMEOUT', 30),
        'retry_attempts' => env('HYTALE_API_RETRY_ATTEMPTS', 3),
        'verify_ssl' => env('HYTALE_VERIFY_SSL', true),
    ],

    'tokens' => [
        // Hytale OAuth2 token élettartamok (dokumentáció szerint)
        'access_token_ttl' => env('HYTALE_ACCESS_TOKEN_TTL', 3600), // 1 óra
        'refresh_token_ttl' => env('HYTALE_REFRESH_TOKEN_TTL', 2592000), // 30 nap
        'game_session_ttl' => env('HYTALE_GAME_SESSION_TTL', 3600), // 1 óra
        'device_code_ttl' => env('HYTALE_DEVICE_CODE_TTL', 900), // 15 perc

        // Device Code Flow beállítások
        'device_poll_interval' => env('HYTALE_DEVICE_POLL_INTERVAL', 5), // 5 másodperc
        'device_poll_timeout' => env('HYTALE_DEVICE_POLL_TIMEOUT', 900), // 15 perc

        // Automatikus refresh (5 perccel lejárat előtt)
        'auto_refresh_buffer' => env('HYTALE_AUTO_REFRESH_BUFFER', 300), // 5 perc

        // Token tárolás
        'store_tokens' => env('HYTALE_STORE_TOKENS', true),
        'encrypt_tokens' => env('HYTALE_ENCRYPT_TOKENS', true),
    ],

    'session' => [
        // Hytale session beállítások
        'max_sessions_per_account' => env('HYTALE_MAX_SESSIONS_PER_ACCOUNT', 100), // Standard license limit
        'session_timeout' => env('HYTALE_SESSION_TIMEOUT', 3600), // 1 óra
        'auto_refresh_sessions' => env('HYTALE_AUTO_REFRESH_SESSIONS', true),
        'track_ip_changes' => env('HYTALE_TRACK_IP_CHANGES', true),

        // Server entitlement check
        'unlimited_servers_entitlement' => env('HYTALE_UNLIMITED_SERVERS', false),
    ],

    'security' => [
        // Biztonsági beállítások
        'verify_ssl' => env('HYTALE_VERIFY_SSL', true),
        'rate_limit_enabled' => env('HYTALE_RATE_LIMIT_ENABLED', true),
        'rate_limit_max_attempts' => env('HYTALE_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'rate_limit_decay_minutes' => env('HYTALE_RATE_LIMIT_DECAY_MINUTES', 1),

        // JWT validáció
        'validate_jwt_signature' => env('HYTALE_VALIDATE_JWT_SIGNATURE', true),
        'allowed_algorithms' => ['EdDSA'], // Hytale uses EdDSA
        'verify_audience' => env('HYTALE_VERIFY_AUDIENCE', true),

        // IP whitelist (opcionális)
        'ip_whitelist' => env('HYTALE_IP_WHITELIST', null), // comma-separated
    ],

    'cache' => [
        // Redis cache beállítások
        'enabled' => env('HYTALE_CACHE_ENABLED', true),
        'ttl' => env('HYTALE_CACHE_TTL', 3600),
        'prefix' => env('HYTALE_CACHE_PREFIX', 'hytale:auth:'),

        // Cache kulcsok
        'user_key_prefix' => 'user:',
        'token_key_prefix' => 'token:',
        'session_key_prefix' => 'session:',
        'device_key_prefix' => 'device:',
        'profile_key_prefix' => 'profile:',
    ],

    'logging' => [
        // Naplózás
        'enabled' => env('HYTALE_LOGGING_ENABLED', true),
        'level' => env('HYTALE_LOG_LEVEL', 'info'),
        'log_successful_auths' => env('HYTALE_LOG_SUCCESSFUL_AUTHS', true),
        'log_failed_auths' => env('HYTALE_LOG_FAILED_AUTHS', true),
        'log_token_operations' => env('HYTALE_LOG_TOKEN_OPERATIONS', false),
        'log_device_flow' => env('HYTALE_LOG_DEVICE_FLOW', true),
    ],

    'webhooks' => [
        // Webhook események (opcionális)
        'enabled' => env('HYTALE_WEBHOOKS_ENABLED', false),
        'endpoint' => env('HYTALE_WEBHOOK_ENDPOINT'),
        'secret' => env('HYTALE_WEBHOOK_SECRET'),
        'events' => [
            'player.authenticated',
            'player.logout',
            'token.refreshed',
            'session.expired',
            'session.created',
        ],
    ],

    'features' => [
        // Funkció kapcsolók
        'auto_registration' => env('HYTALE_AUTO_REGISTRATION', true),
        'remember_me' => env('HYTALE_REMEMBER_ME', true),
        'device_code_flow' => env('HYTALE_DEVICE_CODE_FLOW', true),
        'browser_flow' => env('HYTALE_BROWSER_FLOW', false), // Desktop only
        'credential_store_api' => env('HYTALE_CREDENTIAL_STORE_API', false), // In development
        'profile_management' => env('HYTALE_PROFILE_MANAGEMENT', true),
    ],

    // Hytale-specifikus scope-ok
    'scopes' => [
        'openid' => 'OpenID Connect azonosítás',
        'offline' => 'Refresh token (offline access)',
        'auth:server' => 'Szerver autentikáció',
    ],
];
