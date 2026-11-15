# Career Next Copilot

All Admin, Employer, and Job Seeker Copilot requests use the configured FreeLLMAPI OpenAI-compatible chat completions endpoint. The configured model is `gemini-3.5-flash`; the application does not select or silently substitute another model. Configure `FREELLMAPI_BASE_URL`, `FREELLMAPI_API_KEY`, and set `FREELLMAPI_COPILOT_ENABLED=true` to enable requests.

The browser cannot choose a provider or function. `CopilotCapabilityRegistry` advertises a role-scoped allowlist, and `CopilotToolDispatcher` validates the function name, strict arguments, authenticated actor, role, ownership, and existing business rules before running a fixed Laravel query or service. Function calls are processed sequentially, one per round, with a three-call limit and an overall request deadline. Sensitive suspension actions retain the separate confirmation flow.

Conversation history is stored per role and account in the session and reset with the existing Copilot reset route. The JSON request and response contracts remain `{message}` and `{reply}`. Automated tests use Laravel HTTP fakes and do not require a live gateway.

The FreeLLMAPI model catalog is not evidence that a model can complete requests. A prior local normal completion returned HTTP 502 `empty_completion` from the upstream Gemini 3.5 Flash route. A local check on November 15, 2026 reached the gateway but returned HTTP 429. Normal completion, tool round trips, and authorization behavior are therefore not confirmed against the live gateway.
