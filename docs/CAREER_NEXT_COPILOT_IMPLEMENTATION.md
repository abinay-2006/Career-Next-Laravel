# Career Next Copilot

All Admin, Employer, and Job Seeker Copilot requests use the configured OpenAI-compatible chat completions provider. Production uses NavyAI first and OpenRouter as the fallback; eligible tool-capable models are discovered dynamically from provider catalogues. Local development can use the FreeLLMAPI gateway at `http://127.0.0.1:31415/v1` with its explicitly configured local model chain. FreeLLMAPI is blocked in production. See the Render variable list in the [README](../README.md#copilot-ai-providers).

The browser cannot choose a provider or function. `CopilotCapabilityRegistry` advertises a role-scoped allowlist, and `CopilotToolDispatcher` validates the function name, strict arguments, authenticated actor, role, ownership, and existing business rules before running a fixed Laravel query or service. Function calls are processed sequentially, one per round, with a three-call limit and an overall request deadline. Sensitive suspension actions retain the separate confirmation flow.

Conversation history is stored per role and account in the session and reset with the existing Copilot reset route. The JSON request and response contracts remain `{message}` and `{reply}`. Automated tests use Laravel HTTP fakes and do not require a live gateway.

Catalogue visibility does not guarantee account access or successful completion. Provider errors are classified from response status and sanitized structured information. Safe cross-provider fallback happens only before a Copilot tool has executed, preventing administrative or user operations from being replayed. Automated provider tests use mocked HTTP responses; they do not establish that current Render credentials or upstream accounts are authorized.
