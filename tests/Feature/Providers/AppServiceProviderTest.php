<?php

namespace Tests\Feature\Providers;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const array FORWARDED_HEADERS = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'secrets.example.com',
        'X-Forwarded-Port' => '443',
    ];

    public function test_requests_forwarded_by_a_trusted_proxy_use_the_public_https_url(): void
    {
        config(['app.trusted_proxies' => '10.0.0.0/8, 127.0.0.1']);
        $this->bootAppServiceProvider();

        $this->get('/', self::FORWARDED_HEADERS)
            ->assertRedirect('https://secrets.example.com/login');
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        $this->bootAppServiceProvider();

        $this->get('/', self::FORWARDED_HEADERS)
            ->assertRedirect(route('login'));
    }

    private function bootAppServiceProvider(): void
    {
        $provider = $this->app->getProvider(AppServiceProvider::class);
        $this->assertInstanceOf(AppServiceProvider::class, $provider);

        $provider->boot();
    }
}
