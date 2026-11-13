<?php

namespace Tests\Feature\Secrets;

use App\Secrets\ApcuSecretStore;
use App\Secrets\SecretStore;

/**
 * Runs the store contract against APCu. The local PHP CLI usually lacks APCu;
 * run it with FrankenPHP's embedded PHP and an ini file enabling apc.enable_cli:
 * PHP_INI_SCAN_DIR=":/path/to/ini-dir" ./frankenphp php-cli vendor/bin/phpunit --filter=ApcuSecretStoreTest
 */
class ApcuSecretStoreTest extends ArraySecretStoreTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
            $this->markTestSkipped('APCu is not enabled for the CLI.');
        }

        apcu_clear_cache();
    }

    public function test_stored_secret_is_found_until_it_expires(): void
    {
        $store = $this->store();
        $store->put('first', $this->secret(minutes: 5));

        $this->assertNotNull($store->find('first'));
        $this->assertSame(1, $store->count());

        $this->travel(5)->minutes();

        // APCu evicts on real time; the store also checks the stored expiry.
        $this->assertNull($store->find('first'));
    }

    protected function store(): SecretStore
    {
        return new ApcuSecretStore;
    }
}
