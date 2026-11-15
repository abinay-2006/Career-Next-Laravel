<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Copilot AI providers

Local development uses the FreeLLMAPI desktop gateway (`http://127.0.0.1:31415/v1`) with its curated `FREELLMAPI_*_MODEL` chain. Production uses OpenRouter, with NavyAI as the fallback provider. FreeLLMAPI is never contacted when `APP_ENV=production`, and hosted providers must use public HTTPS base URLs. Set these environment variables in Render and enter keys through Render's secret fields; do not commit credentials:

```dotenv
APP_ENV=production
LOG_CHANNEL=stderr
AI_PROVIDER=openrouter
AI_FALLBACK_PROVIDER=navyai
AI_PROVIDER_FALLBACK_ENABLED=true
AI_TOTAL_TIMEOUT_SECONDS=24
OPENROUTER_API_KEY=
OPENROUTER_ENABLED=true
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_TIMEOUT_SECONDS=9
NAVYAI_API_KEY=
NAVYAI_ENABLED=true
NAVYAI_BASE_URL=https://api.navy/v1
NAVYAI_TIMEOUT_SECONDS=9
FREELLMAPI_COPILOT_ENABLED=false
```

No hosted model ID is configured. For every request the app resolves a ranked chain of up to three models from the provider's live catalogue (`GET /models`), cached for `AI_MODEL_CATALOG_CACHE_SECONDS` (a last-known-good copy is kept for `AI_MODEL_CATALOG_STALE_SECONDS` and used only when a refresh fails):

- **OpenRouter**: models that publish both `tools` and `tool_choice` in `supported_parameters`, take text and return only text, serve `/api/v1/chat/completions`, are not routers/aliases or about to expire, and cost no more than `OPENROUTER_MAX_PROMPT_PRICE_PER_MILLION` / `OPENROUTER_MAX_COMPLETION_PRICE_PER_MILLION` (defaults 1 and 5 USD). Ranked by OpenRouter's published Artificial Analysis agentic and intelligence indices, then price.
- **NavyAI**: `/v1/chat/completions` models with `supports_tools` or `supports_function_calling`, `metadata_status: known` and text-only output. `premium` is not required. Models with a `required_plan` are used only when that plan is listed in `NAVYAI_ALLOWED_PLANS`. Ranked by reasoning support and published capacity, then lower `token_multiplier`; a premium first choice is always followed by a non-premium model.

A model the provider refuses (403/404) is skipped for a few minutes and the next discovered model is tried. Provider failover happens only before any Copilot tool has executed, so an operation is never replayed. `OPENROUTER_MODEL`, `NAVYAI_MODEL` and `OPENROUTER_AUTO_ROUTER_COST_TIER` are obsolete and ignored.

Logs are written per provider (`OpenRouter Copilot ...`, `NavyAI Copilot ...`, `Copilot provider ...`) with provider, operation (`model_discovery` / `completion`), HTTP status, failure type, model ID and timing, never keys, prompts or response bodies. A `failure_type` of `edge_blocked` means a CDN or bot-protection layer in front of the provider refused traffic from the hosting network.
