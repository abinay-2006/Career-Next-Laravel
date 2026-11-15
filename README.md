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

Local development uses the FreeLLMAPI desktop gateway (`http://127.0.0.1:31415/v1`) with its curated `FREELLMAPI_*_MODEL` chain. Production uses NaraRouter, with OpenRouter as the fallback provider. FreeLLMAPI is never contacted when `APP_ENV=production` (even if its variables are present), and hosted providers must use public HTTPS base URLs. Set these environment variables in Render and enter keys through Render's secret fields; do not commit credentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
AI_PROVIDER=nararouter
AI_FALLBACK_PROVIDER=openrouter
AI_PROVIDER_FALLBACK_ENABLED=true
AI_TOTAL_TIMEOUT_SECONDS=24
AI_MODEL_CATALOG_CACHE_SECONDS=600
AI_MODEL_CATALOG_STALE_SECONDS=86400
NARAROUTER_ENABLED=true
NARAROUTER_BASE_URL=https://router.bynara.id/v1
NARAROUTER_API_KEY=
NARAROUTER_REQUEST_TIMEOUT_SECONDS=9
OPENROUTER_ENABLED=true
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_API_KEY=
OPENROUTER_TIMEOUT_SECONDS=9
FREELLMAPI_COPILOT_ENABLED=false
```

Optional NaraRouter tuning: `NARAROUTER_COOLDOWN_DEFAULT_SECONDS` (60), `NARAROUTER_COOLDOWN_MAX_SECONDS` (300), `NARAROUTER_TOOL_VERIFICATION_CACHE_SECONDS` (21600), `NARAROUTER_TOOL_VERIFICATION_FAILURE_CACHE_SECONDS` (3600). The `NAVYAI_*` variables are no longer needed in production; NavyAI remains selectable with `AI_PROVIDER=navyai`.

No model ID is hardcoded. For every request the app resolves a chain of up to three models from current provider metadata, cached for `AI_MODEL_CATALOG_CACHE_SECONDS` (a last-known-good copy is kept for `AI_MODEL_CATALOG_STALE_SECONDS` and used only when a refresh fails):

- **NaraRouter**: calls the authenticated `GET /v1/models` with `NARAROUTER_API_KEY`, which lists only the models the key's plan entitles. NaraRouter documents no tool-capability metadata, so entries explicitly marked as non-chat, non-text, unavailable or tool-incapable are dropped, routing aliases (`auto`, `auto/...`) and `:online` variants are skipped, and every remaining model must pass a two-step tool-calling probe before it is used: a synthetic no-op function (never part of the Copilot registry, never dispatched) must come back as a well-formed `tool_call`, and the model must then answer after receiving the tool result. Verdicts are cached; on a cold cache at most three models are probed per request, stopping at the first success. Models flagged `reasoning: true` are sent `reasoning_effort: "low"` (the probe uses the same request shape).
- **OpenRouter**: uses `GET /models?supported_parameters=tools` to honor available account preferences and guardrails, then checks explicit tool, text modality, chat endpoint and expiry metadata. The filtered catalog still cannot prove that a completion will be authorized.
- **NavyAI**: checks `/v1/chat/completions` support, explicit tool capability metadata, text output, `required_plan` against `NAVYAI_ALLOWED_PLANS`, and the public `/v1/models/status` health data when available. Premium flags and pricing do not determine selection.

The first completion is the definitive account-access check. Invalid credentials and generic account permission denials are not retried against more models; the configured NaraRouter to OpenRouter provider fallback is used when safe. A NaraRouter 403 is treated as a model entitlement refusal only when its message names a plan/entitlement restriction (never as an invalid key); NaraRouter 429s apply to the whole key, so they fail over to OpenRouter instead of rotating models. A request the provider rejects as invalid (HTTP 400/413/415/422, including safety-screen rejections) is not re-sent to another model or provider. Explicit model-scoped permission/availability or tool incompatibility errors temporarily exclude that model and try another discovered candidate within the overall timeout. If both providers reject authentication or account access, the user receives an aggregate safe access error and the logs retain each provider's individual classification. Provider failover only happens before any Copilot tool has executed, so an operation is never replayed. FreeLLMAPI is local-development-only and requires explicitly configured model IDs.

Logs are written per provider (`NaraRouter Copilot ...`, `OpenRouter Copilot ...`, `NavyAI Copilot ...`, `Copilot provider ...`) with provider, operation (`model_discovery` / `tool_verification` / `model_availability` / `completion`), HTTP status, selected model ID, sanitized provider error code, provider request ID, failure type and timing. Keys, authorization headers, prompts and response bodies are never logged. Failure types distinguish invalid credentials, account permission denial, model permission denial, model unavailability, unsupported tool calling and provider outage. A failure type of `edge_blocked` means a CDN or bot-protection layer in front of the provider refused traffic from the hosting network.
