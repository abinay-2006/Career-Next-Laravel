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
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],
    'freellmapi' => [
        'base_url' => env('FREELLMAPI_BASE_URL'),
        'key' => env('FREELLMAPI_API_KEY'),
        'enabled' => env('FREELLMAPI_COPILOT_ENABLED', false),

        // Local-development-only curated model chain (max three). Model IDs must be explicitly configured.
        'models' => [
            'primary' => env('FREELLMAPI_PRIMARY_MODEL', ''),
            'secondary' => env('FREELLMAPI_SECONDARY_MODEL', ''),
            'tertiary' => env('FREELLMAPI_TERTIARY_MODEL', ''),
        ],
        'model_fallback_enabled' => env('FREELLMAPI_MODEL_FALLBACK_ENABLED', true),
        'max_model_attempts' => env('FREELLMAPI_MAX_MODEL_ATTEMPTS', 3),
        // Local per-model cooldown after a model-level 429: Retry-After when valid, otherwise the
        // default; never longer than the max, after which one request may probe the model again.
        'model_cooldown_default_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_DEFAULT_SECONDS', 60),
        'model_cooldown_max_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_MAX_SECONDS', 300),
    ],
    'ai' => [
        // Production: OpenRouter, then NavyAI. Local development: the FreeLLMAPI gateway.
        // FreeLLMAPI (a local gateway) is never used when APP_ENV=production, whatever is set here.
        'provider' => env('AI_PROVIDER', env('APP_ENV') === 'production' ? 'openrouter' : 'freellmapi'),
        'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'navyai'),
        'provider_fallback_enabled' => env('AI_PROVIDER_FALLBACK_ENABLED', env('APP_ENV') === 'production'),
        'total_timeout_seconds' => env('AI_TOTAL_TIMEOUT_SECONDS', 24),
        // Hosted providers have no configured model: tool-capable models are discovered from each
        // provider's live catalogue, cached briefly, with a last-known-good copy for catalogue outages.
        'model_catalog_cache_seconds' => env('AI_MODEL_CATALOG_CACHE_SECONDS', 600),
        'model_catalog_stale_seconds' => env('AI_MODEL_CATALOG_STALE_SECONDS', 86400),
        'providers' => [
            'openrouter' => [
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'), 'key' => env('OPENROUTER_API_KEY'),
                'enabled' => env('OPENROUTER_ENABLED', false),
                'request_timeout_seconds' => env('OPENROUTER_TIMEOUT_SECONDS', 9), 'cooldown_default_seconds' => env('OPENROUTER_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('OPENROUTER_COOLDOWN_MAX_SECONDS', 300),
            ],
            'navyai' => [
                'base_url' => env('NAVYAI_BASE_URL', 'https://api.navy/v1'), 'key' => env('NAVYAI_API_KEY'),
                'enabled' => env('NAVYAI_ENABLED', false),
                // Comma-separated NavyAI plans this account holds (e.g. "Plus"); models whose catalogue
                // entry has a `required_plan` outside this list are never selected.
                'allowed_plans' => env('NAVYAI_ALLOWED_PLANS', ''),
                'check_model_availability' => env('NAVYAI_CHECK_MODEL_AVAILABILITY', true),
                'request_timeout_seconds' => env('NAVYAI_TIMEOUT_SECONDS', 9), 'cooldown_default_seconds' => env('NAVYAI_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('NAVYAI_COOLDOWN_MAX_SECONDS', 300),
            ],
            'freellmapi' => [
                'base_url' => env('FREELLMAPI_BASE_URL'), 'key' => env('FREELLMAPI_API_KEY'), 'enabled' => env('FREELLMAPI_COPILOT_ENABLED', false),
                'model' => env('FREELLMAPI_PRIMARY_MODEL', ''), 'request_timeout_seconds' => env('FREELLMAPI_TIMEOUT_SECONDS', 9),
                'cooldown_default_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_MAX_SECONDS', 300),
            ],
        ],
    ],
];
