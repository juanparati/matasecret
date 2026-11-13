<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AddSecurityHeadersTest extends TestCase
{
    public function test_responses_carry_a_content_security_policy_with_a_nonce(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $nonce = $this->nonceFrom($response);

        $response
            ->assertHeader('Content-Security-Policy')
            ->assertSee("nonce=\"{$nonce}\"", false);

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-{$nonce}'", $policy);
        $this->assertStringContainsString("style-src 'self' 'nonce-{$nonce}'", $policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function test_nonce_changes_on_every_request(): void
    {
        $first = $this->nonceFrom($this->get(route('login')));
        $second = $this->nonceFrom($this->get(route('login')));

        $this->assertNotSame($first, $second);
    }

    public function test_hardening_headers_are_present(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_json_endpoints_also_carry_the_headers(): void
    {
        $this->signIn()
            ->postJson(route('secrets.store'), [
                'content' => 'hunter2',
                'ttl' => '1h',
            ])
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * Extract the nonce from the response's Content-Security-Policy header.
     */
    private function nonceFrom(TestResponse $response): string
    {
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/'nonce-([A-Za-z0-9]{40})'/", $policy);

        preg_match("/'nonce-([A-Za-z0-9]{40})'/", $policy, $matches);

        return $matches[1];
    }
}
