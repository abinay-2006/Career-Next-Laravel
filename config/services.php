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

        // Curated Copilot model chain (max three), tried in order. Defaults were chosen from the local
        // gateway catalog and validated with a live tool-call round trip; set a slot to an empty value
        // to disable it. Every model must support OpenAI-style tool calling.
        'models' => [
            'primary' => env('FREELLMAPI_PRIMARY_MODEL', 'gemini-3.5-flash'),
            'secondary' => env('FREELLMAPI_SECONDARY_MODEL', 'nemotron-3-ultra-550b'),
            'tertiary' => env('FREELLMAPI_TERTIARY_MODEL', 'gpt-oss-120b'),
        ],
        'model_fallback_enabled' => env('FREELLMAPI_MODEL_FALLBACK_ENABLED', true),
        'max_model_attempts' => env('FREELLMAPI_MAX_MODEL_ATTEMPTS', 3),
        // Local per-model cooldown after a model-level 429: Retry-After when valid, otherwise the
        // default; never longer than the max, after which one request may probe the model again.
        'model_cooldown_default_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_DEFAULT_SECONDS', 60),
        'model_cooldown_max_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_MAX_SECONDS', 300),
    ],
    'ai' => [
        'provider' => env('AI_PROVIDER', env('APP_ENV') === 'production' ? 'openrouter' : 'freellmapi'),
        'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'navyai'),
        'provider_fallback_enabled' => env('AI_PROVIDER_FALLBACK_ENABLED', env('APP_ENV') === 'production'),
        'total_timeout_seconds' => env('AI_TOTAL_TIMEOUT_SECONDS', 24),
        'providers' => [
            'openrouter' => [
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'), 'key' => env('OPENROUTER_API_KEY'),
                'enabled' => env('OPENROUTER_ENABLED', false), 'model' => env('OPENROUTER_MODEL', 'google/gemini-4-carbon'),
                'request_timeout_seconds' => env('OPENROUTER_TIMEOUT_SECONDS', 9), 'cooldown_default_seconds' => env('OPENROUTER_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('OPENROUTER_COOLDOWN_MAX_SECONDS', 300),
            ],
            'navyai' => [
                'base_url' => env('NAVYAI_BASE_URL', 'https://api.navy/v1'), 'key' => env('NAVYAI_API_KEY'),
                'enabled' => env('NAVYAI_ENABLED', false), 'model' => env('NAVYAI_MODEL', 'gpt-6-astra'),
                'request_timeout_seconds' => env('NAVYAI_TIMEOUT_SECONDS', 9), 'cooldown_default_seconds' => env('NAVYAI_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('NAVYAI_COOLDOWN_MAX_SECONDS', 300),
            ],
            'freellmapi' => [
                'base_url' => env('FREELLMAPI_BASE_URL'), 'key' => env('FREELLMAPI_API_KEY'), 'enabled' => env('FREELLMAPI_COPILOT_ENABLED', false),
                'model' => env('FREELLMAPI_PRIMARY_MODEL', 'gemini-3.5-flash'), 'request_timeout_seconds' => env('FREELLMAPI_TIMEOUT_SECONDS', 9),
                'cooldown_default_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_DEFAULT_SECONDS', 60), 'cooldown_max_seconds' => env('FREELLMAPI_MODEL_COOLDOWN_MAX_SECONDS', 300),
            ],
        ],
    ],
];
