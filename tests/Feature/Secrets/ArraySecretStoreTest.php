<?php

namespace Tests\Feature\Secrets;

use App\Secrets\ArraySecretStore;
use App\Secrets\SecretStore;
use App\Secrets\StoredSecret;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ArraySecretStoreTest extends TestCase
{
    public function test_stored_secret_is_found_until_it_expires(): void
    {
        $store = $this->store();
        $store->put('first', $this->secret(minutes: 5));

        $this->assertNotNull($store->find('first'));
        $this->assertSame(1, $store->count());

        $this->travel(5)->minutes();

        $this->assertNull($store->find('first'));
        $this->assertSame(0, $store->count());
    }

    public function test_pull_returns_the_secret_only_once(): void
    {
        $store = $this->store();
        $store->put('first', $this->secret());

        $this->assertNotNull($store->pull('first'));
        $this->assertNull($store->pull('first'));
    }

    public function test_put_does_not_overwrite_an_existing_secret(): void
    {
        $store = $this->store();

        $this->assertTrue($store->put('first', $this->secret()));
        $this->assertFalse($store->put('first', $this->secret()));
    }

    public function test_forget_removes_the_secret(): void
    {
        $store = $this->store();
        $store->put('first', $this->secret());

        $store->forget('first');

        $this->assertNull($store->find('first'));
    }

    protected function store(): SecretStore
    {
        return new ArraySecretStore;
    }

    protected function secret(int $minutes = 60): StoredSecret
    {
        return new StoredSecret('payload', 'creator', CarbonImmutable::now()->addMinutes($minutes), true);
    }
}
