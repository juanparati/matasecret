<?php

namespace Tests\Unit\Setup;

use App\Setup\SetupField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SetupFieldTest extends TestCase
{
    #[DataProvider('acceptedAnswers')]
    public function test_accepts_valid_answers(string $format, string $value): void
    {
        $field = new SetupField('KEY', 'Value', format: $format);

        $this->assertNull($field->validate($value));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function acceptedAnswers(): array
    {
        return [
            'https url' => ['url', 'https://acme.okta.com'],
            'host name' => ['host', 'secrets.example.com'],
            'ip address as host' => ['host', '127.0.0.1'],
            'highest port' => ['port', '65535'],
            'ipv6 address' => ['ip', '::1'],
            'single proxy' => ['proxies', '127.0.0.1'],
            'proxy list with ranges' => ['proxies', '10.0.0.0/8, 192.168.1.1, fd00::/8'],
            'any proxy' => ['proxies', '*'],
            'tenant id' => ['tenant', 'contoso.onmicrosoft.com'],
            'zendesk subdomain' => ['subdomain', 'acme-support'],
        ];
    }

    #[DataProvider('rejectedAnswers')]
    public function test_rejects_invalid_answers(string $format, string $value, string $message): void
    {
        $field = new SetupField('KEY', 'Value', format: $format);

        $this->assertSame($message, $field->validate($value));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function rejectedAnswers(): array
    {
        return [
            'plain http url' => ['url', 'http://acme.okta.com', 'Enter a full URL starting with https://.'],
            'url without scheme' => ['url', 'acme.okta.com', 'Enter a full URL starting with https://.'],
            'host with a scheme' => ['host', 'https://secrets.example.com', 'Enter a host name like example.com.'],
            'port zero' => ['port', '0', 'Enter a port between 1 and 65535.'],
            'port too high' => ['port', '65536', 'Enter a port between 1 and 65535.'],
            'negative integer' => ['integer', '-1', 'Enter a whole number.'],
            'host name as ip' => ['ip', 'localhost', 'Enter an IP address such as 127.0.0.1.'],
            'proxy range too wide' => ['proxies', '10.0.0.0/33', 'Enter IP addresses or CIDR ranges separated by commas, or * to trust any proxy.'],
            'proxy host name' => ['proxies', '127.0.0.1, proxy.local', 'Enter IP addresses or CIDR ranges separated by commas, or * to trust any proxy.'],
            'common tenant' => ['tenant', 'common', 'Use your own tenant ID or domain: "common" lets accounts from other tenants sign in.'],
            'organizations tenant' => ['tenant', 'Organizations', 'Use your own tenant ID or domain: "Organizations" lets accounts from other tenants sign in.'],
            'consumers tenant' => ['tenant', 'consumers', 'Use your own tenant ID or domain: "consumers" lets accounts from other tenants sign in.'],
            'full zendesk host' => ['subdomain', 'acme.zendesk.com', 'Enter only the subdomain, such as "acme" for acme.zendesk.com.'],
            'missing file' => ['file', '/does/not/exist.p8', 'The file does not exist or cannot be read.'],
            'invalid email' => ['email', 'no-reply', 'Enter a valid email address.'],
        ];
    }

    public function test_required_field_rejects_an_empty_answer(): void
    {
        $this->assertSame('The Okta client ID is required.', (new SetupField('AUTH_CLIENT_ID', 'Okta client ID'))->validate(''));
    }

    public function test_optional_field_accepts_an_empty_answer(): void
    {
        $this->assertNull((new SetupField('host', 'GitLab URL', required: false, format: 'url'))->validate(''));
    }

    #[DataProvider('normalizedAnswers')]
    public function test_normalizes_accepted_answers(string $format, string $value, string $normalized): void
    {
        $this->assertSame($normalized, (new SetupField('KEY', 'Value', format: $format))->normalize($value));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function normalizedAnswers(): array
    {
        return [
            'url loses its trailing slash' => ['url', ' https://acme.okta.com/ ', 'https://acme.okta.com'],
            'host is lowercased' => ['host', 'Secrets.Example.com', 'secrets.example.com'],
            'proxy list loses its spaces' => ['proxies', '10.0.0.0/8, 127.0.0.1', '10.0.0.0/8,127.0.0.1'],
            'text keeps its case' => ['text', 'MataSecret Team', 'MataSecret Team'],
        ];
    }
}
