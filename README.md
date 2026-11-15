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
APP_DEBUG=false
LOG_CHANNEL=stderr
AI_PROVIDER=openrouter
AI_FALLBACK_PROVIDER=navyai
AI_PROVIDER_FALLBACK_ENABLED=true
AI_TOTAL_TIMEOUT_SECONDS=24
AI_MODEL_CATALOG_CACHE_SECONDS=600
AI_MODEL_CATALOG_STALE_SECONDS=86400
OPENROUTER_API_KEY=
OPENROUTER_ENABLED=true
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_TIMEOUT_SECONDS=9
NAVYAI_API_KEY=
NAVYAI_ENABLED=true
NAVYAI_BASE_URL=https://api.navy/v1
NAVYAI_ALLOWED_PLANS=
NAVYAI_CHECK_MODEL_AVAILABILITY=true
NAVYAI_TIMEOUT_SECONDS=9
FREELLMAPI_COPILOT_ENABLED=false
```

No model ID is hardcoded. For every request the app resolves a chain of up to three models from current provider metadata, cached for `AI_MODEL_CATALOG_CACHE_SECONDS` (a last-known-good copy is kept for `AI_MODEL_CATALOG_STALE_SECONDS` and used only when a refresh fails):

- **OpenRouter**: uses `GET /models/user` to honor available account preferences and guardrails, then checks explicit tool, text modality, chat endpoint and expiry metadata. The filtered catalog still cannot prove that a completion will be authorized.
- **NavyAI**: checks `/v1/chat/completions` support, known tool capability metadata, text output, `required_plan` against `NAVYAI_ALLOWED_PLANS`, and the public `/v1/models/status` health data when available. Premium flags and pricing do not determine selection.

The first completion is the definitive account-access check. Invalid credentials and generic account permission denials are not retried against more models; the configured OpenRouter → NavyAI provider fallback is used when safe. Explicit model-scoped permission/availability or tool incompatibility errors temporarily exclude that model and try another discovered candidate within the overall timeout. If both providers reject authentication or account access, the user receives an aggregate safe access error and the logs retain each provider's individual classification. Provider failover only happens before any Copilot tool has executed, so an operation is never replayed. FreeLLMAPI is local-development-only and requires explicitly configured model IDs.

Logs are written per provider (`OpenRouter Copilot ...`, `NavyAI Copilot ...`, `Copilot provider ...`) with provider, operation (`model_discovery` / `model_availability` / `completion`), HTTP status, selected model ID, sanitized provider error code, failure type and timing. Keys, authorization headers, prompts and response bodies are never logged. Failure types distinguish invalid credentials, account permission denial, model permission denial, model unavailability, unsupported tool calling and provider outage. A failure type of `edge_blocked` means a CDN or bot-protection layer in front of the provider refused traffic from the hosting network.
