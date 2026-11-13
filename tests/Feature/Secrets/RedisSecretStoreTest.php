<?php

namespace Tests\Feature\Secrets;

use App\Secrets\RedisSecretStore;
use App\Secrets\SecretStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Runs the store contract against the "secrets" Redis connection. Skipped
 * when no Redis server is reachable or the client is not phpredis. Start one with:
 * docker run --rm -p 6379:6379 redis:7 --save '' --appendonly no
 */
class RedisSecretStoreTest extends ArraySecretStoreTest
{
    private PhpRedisConnection $redis;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $connection = Redis::connection('secrets');
            $connection->ping();
        } catch (Throwable) {
            $this->markTestSkipped('Redis is not reachable on the "secrets" connection.');
        }

        if (! $connection instanceof PhpRedisConnection) {
            $this->markTestSkipped('The redis secret store requires the phpredis client.');
        }

        $this->redis = $connection;
        $this->flushTestKeys();
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            $this->flushTestKeys();
        }

        parent::tearDown();
    }

    public function test_count_ignores_expired_secrets_still_in_the_index(): void
    {
        $store = $this->store();
        $store->put('first', $this->secret(minutes: 5));
        $store->put('second', $this->secret(minutes: 10));

        $this->travel(6)->minutes();

        $this->assertSame(1, $store->count());
    }

    private function flushTestKeys(): void
    {
        $this->redis->del('ephesecret:secret:first', 'ephesecret:secret:second', 'ephesecret:secrets');
    }

    protected function store(): SecretStore
    {
        return new RedisSecretStore($this->redis);
    }
}
