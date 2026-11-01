<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TrustProxies::withHeaders(
            SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PORT
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        );
        config()->set('trustedproxy.proxies', []);

        if (!Schema::hasTable('jobseeker_reg')) {
            Schema::create('jobseeker_reg', function (Blueprint $table): void {
                $table->increments('JobSeekId');
                $table->string('Email');
                $table->string('Password');
            });
        }

        if (!Schema::hasTable('employer_reg')) {
            Schema::create('employer_reg', function (Blueprint $table): void {
                $table->increments('EmployerId');
                $table->string('Email');
                $table->string('Password');
            });
        }
    }

    public function test_untrusted_forwarded_headers_cannot_change_client_ip_host_or_scheme(): void
    {
        $request = $this->requestWithForwardedHeaders('203.0.113.10');
        $observed = $this->passThroughTrustProxies($request);

        $this->assertSame('203.0.113.10', $observed['ip']);
        $this->assertSame('internal.example.test', $observed['host']);
        $this->assertSame('http://internal.example.test', $observed['origin']);
    }

    public function test_explicitly_trusted_proxy_headers_set_ip_https_host_and_port(): void
    {
        config()->set('trustedproxy.proxies', ['203.0.113.10']);
        $request = $this->requestWithForwardedHeaders('203.0.113.10');
        $observed = $this->passThroughTrustProxies($request);

        $this->assertSame('198.51.100.25', $observed['ip']);
        $this->assertSame('app.example.test', $observed['host']);
        $this->assertSame('https://app.example.test', $observed['origin']);
        $this->assertSame(443, $observed['port']);
    }

    public function test_explicit_cidr_range_trusts_matching_proxy(): void
    {
        config()->set('trustedproxy.proxies', ['203.0.113.0/24']);
        $request = $this->requestWithForwardedHeaders('203.0.113.77');
        $observed = $this->passThroughTrustProxies($request);

        $this->assertSame('198.51.100.25', $observed['ip']);
        $this->assertSame('app.example.test', $observed['host']);
    }

    public function test_trusted_proxy_configuration_rejects_wildcards_and_catch_all_ranges(): void
    {
        $this->assertProxyValuesRejected(['*', '**', '0.0.0.0/0', '::/0']);
    }

    public function test_remote_addr_shortcut_is_rejected_case_insensitively(): void
    {
        $this->assertProxyValuesRejected(['REMOTE_ADDR', 'remote_addr', 'Remote_Addr']);
    }

    public function test_all_zero_prefix_cidrs_are_rejected_in_canonical_and_noncanonical_forms(): void
    {
        $this->assertProxyValuesRejected([
            '192.0.2.7/0',
            '2001:db8::7/0',
            '0:0:0:0:0:0:0:0/0',
            '2001:db8::7/00',
        ]);
    }

    public function test_malformed_addresses_and_invalid_cidr_prefixes_are_rejected(): void
    {
        $this->assertProxyValuesRejected([
            '999.0.0.1',
            'not-an-ip',
            '192.0.2.1/',
            '192.0.2.1/33',
            '2001:db8::1/129',
            '192.0.2.1/-1',
            '192.0.2.1/abc',
            '192.0.2.1/24/1',
        ]);
    }

    public function test_explicit_ipv4_ipv6_addresses_and_bounded_cidrs_are_accepted(): void
    {
        $this->assertSame([
            '192.0.2.10',
            '2001:db8::10',
            '192.0.2.0/24',
            '2001:db8:1234::/48',
        ], $this->loadProxyConfig('192.0.2.10,2001:db8::10,192.0.2.0/24,2001:db8:1234::/48')['proxies']);
    }

    public function test_local_request_uses_direct_connection_without_forwarded_headers(): void
    {
        $request = Request::create('http://localhost/forgot', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 80,
        ]);
        $observed = $this->passThroughTrustProxies($request);

        $this->assertSame('127.0.0.1', $observed['ip']);
        $this->assertSame('localhost', $observed['host']);
        $this->assertSame('http://localhost', $observed['origin']);
    }

    public function test_password_recovery_ip_limit_uses_the_resolved_trusted_client_ip(): void
    {
        config()->set('trustedproxy.proxies', ['203.0.113.10']);
        Mail::fake();

        foreach (range(1, 10) as $number) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
            ])->post(route('forgot.send'), [
                'email' => 'proxy-recipient'.$number.'@example.test',
                'account_type' => 'jobseeker',
            ])->assertRedirect(route('forgot.verify'));
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
        ])->post(route('forgot.send'), [
            'email' => 'proxy-recipient-eleven@example.test',
            'account_type' => 'jobseeker',
        ])->assertStatus(429);

        // A different client address reported by the same explicitly trusted proxy
        // receives a separate IP bucket.
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.26',
        ])->post(route('forgot.send'), [
            'email' => 'proxy-recipient-twelve@example.test',
            'account_type' => 'jobseeker',
        ])->assertRedirect(route('forgot.verify'));
    }

    private function requestWithForwardedHeaders(string $remoteAddress): Request
    {
        return Request::create('http://internal.example.test/forgot', 'GET', [], [], [], [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_HOST' => 'internal.example.test',
            'SERVER_PORT' => 80,
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
            'HTTP_X_FORWARDED_HOST' => 'app.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
    }

    private function passThroughTrustProxies(Request $request): array
    {
        (new TrustProxies())->handle($request, function (Request $request): \Symfony\Component\HttpFoundation\Response {
            return response()->noContent();
        });

        return [
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'origin' => $request->getSchemeAndHttpHost(),
            'port' => $request->getPort(),
        ];
    }

    /** @param list<string> $values */
    private function assertProxyValuesRejected(array $values): void
    {
        foreach ($values as $value) {
            try {
                $this->loadProxyConfig($value);
                $this->fail('Expected invalid trusted proxy configuration to be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    /** @return array{proxies: list<string>} */
    private function loadProxyConfig(string $value): array
    {
        $previous = getenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES='.$value);

        try {
            return require config_path('trustedproxy.php');
        } finally {
            if ($previous === false) {
                putenv('TRUSTED_PROXIES');
            } else {
                putenv('TRUSTED_PROXIES='.$previous);
            }
        }
    }
}
