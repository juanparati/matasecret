<?php

namespace Tests\Unit\Enums;

use App\Enums\AuthProvider;
use PHPUnit\Framework\TestCase;

class AuthProviderTest extends TestCase
{
    public function test_setup_does_not_offer_the_test_provider(): void
    {
        $options = AuthProvider::options();

        $this->assertArrayNotHasKey('test', $options);
        $this->assertSame('Google Workspace', $options['google']);
    }

    public function test_test_provider_adds_no_package(): void
    {
        $this->assertNotContains('socialiteproviders/test', AuthProvider::catalog());
    }
}
