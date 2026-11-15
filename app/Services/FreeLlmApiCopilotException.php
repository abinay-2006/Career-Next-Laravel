<?php

namespace App\Services;

use RuntimeException;

class FreeLlmApiCopilotException extends RuntimeException
{
    public const UNAVAILABLE = 'unavailable';
    public const QUOTA = 'quota';
    public const RATE_LIMITED = 'rate_limited';
    public const TIMEOUT = 'timeout';
    public const INVALID_RESPONSE = 'invalid_response';
    public const EMPTY_COMPLETION = 'empty_completion';
    public const CONNECTION = 'connection';
    public const INVALID_CREDENTIALS = 'invalid_credentials';
    public const ACCOUNT_PERMISSION_DENIED = 'account_permission_denied';
    public const MODEL_PERMISSION_DENIED = 'model_permission_denied';
    public const MODEL_UNAVAILABLE = 'model_unavailable';
    public const UNSUPPORTED_TOOL_CALLING = 'unsupported_tool_calling';
    public const PROVIDER_OUTAGE = 'provider_outage';
    public const PROVIDERS_REJECTED_ACCESS = 'providers_rejected_access';
    /** The provider rejected the request itself (HTTP 400/413/415/422): never retried or failed over. */
    public const INVALID_REQUEST = 'invalid_request';
    // Compatibility aliases used by existing callers/tests.
    public const AUTHENTICATION = self::INVALID_CREDENTIALS;
    public const PERMISSION = self::ACCOUNT_PERMISSION_DENIED;
    public const UPSTREAM = self::PROVIDER_OUTAGE;
    /** Every configured model was tried (or cooling down) and failed for differing reasons. */
    public const MODELS_UNAVAILABLE = 'models_unavailable';

    /**
     * @param string   $message           User-safe message (never contains provider details).
     * @param string   $reason            One of the class constants.
     * @param int|null $retryAfterSeconds Validated, bounded wait suggested by the gateway.
     * @param int|null $providerStatus    HTTP status returned by the gateway, when one was received.
     * @param bool     $modelSpecific     True when the failure is plausibly limited to the model that was
     *                                    asked (model rate limit, upstream 5xx, timeout, empty/broken
     *                                    completion), so a different configured model may succeed.
     *                                    False for gateway-wide or request-level problems (credentials,
     *                                    permissions, bad request, unreachable gateway, client limiter).
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        ?\Throwable $previous = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?int $providerStatus = null,
        public readonly bool $modelSpecific = false,
        public readonly bool $fallbackSafe = true
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRateLimit(): bool
    {
        return in_array($this->reason, [self::RATE_LIMITED, self::QUOTA], true);
    }
}
