<?php

/*
 | Comma-separated, owner-verified proxy IP addresses or CIDR ranges.
 | Leave unset for local development or until deployment ranges are verified.
 | Only literal IP addresses or bounded CIDR ranges are valid. Never use a
 | wildcard or the middleware's REMOTE_ADDR shortcut.
 */
$trustedProxies = [];
foreach (explode(',', (string) env('TRUSTED_PROXIES', '')) as $proxy) {
    $proxy = trim($proxy);
    if ($proxy === '') {
        continue;
    }

    if (strcasecmp($proxy, 'REMOTE_ADDR') === 0 || in_array($proxy, ['*', '**'], true)) {
        throw new InvalidArgumentException('TRUSTED_PROXIES must contain literal IP addresses or bounded CIDR ranges.');
    }

    if (str_contains($proxy, '/')) {
        if (substr_count($proxy, '/') !== 1) {
            throw new InvalidArgumentException('TRUSTED_PROXIES contains an invalid IP range.');
        }

        [$address, $prefix] = explode('/', $proxy, 2);
        if (filter_var($address, FILTER_VALIDATE_IP) === false
            || preg_match('/^[0-9]+$/D', $prefix) !== 1) {
            throw new InvalidArgumentException('TRUSTED_PROXIES contains an invalid IP range.');
        }

        $isIpv4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $maxPrefix = $isIpv4 ? 32 : 128;
        $prefixLength = (int) $prefix;
        if ($prefixLength === 0 || $prefixLength > $maxPrefix) {
            throw new InvalidArgumentException('TRUSTED_PROXIES CIDR prefixes must be bounded and valid.');
        }
    } elseif (filter_var($proxy, FILTER_VALIDATE_IP) === false) {
        throw new InvalidArgumentException('TRUSTED_PROXIES contains an invalid IP address.');
    }

    $trustedProxies[] = $proxy;
}

return [
    'proxies' => $trustedProxies,
];
