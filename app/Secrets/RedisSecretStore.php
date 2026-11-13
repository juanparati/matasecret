<?php

namespace App\Secrets;

use Carbon\CarbonImmutable;
use Illuminate\Redis\Connections\PhpRedisConnection;

/**
 * Keeps secrets in Redis, which can be shared by several PHP pools and hosts.
 * Point it at a Redis instance with persistence disabled (save "" and
 * appendonly no) so secrets stay in memory only. Requires Redis 6.2+ and the
 * phpredis client, whose connection accepts the EX/NX options on set().
 */
class RedisSecretStore implements SecretStore
{
    private const string PREFIX = 'ephesecret:secret:';

    /**
     * Sorted set of stored ids scored by expiry, used to count live secrets
     * without scanning the keyspace.
     */
    private const string INDEX = 'ephesecret:secrets';

    public function __construct(private PhpRedisConnection $redis) {}

    public function put(string $id, StoredSecret $secret): bool
    {
        $stored = $this->redis->set(
            self::PREFIX.$id,
            $secret->encode(),
            'EX',
            max(1, $secret->secondsUntilExpiration()),
            'NX',
        );

        if (! $stored) {
            return false;
        }

        $this->redis->zadd(self::INDEX, $secret->expiresAt->getTimestamp(), $id);

        return true;
    }

    public function find(string $id): ?StoredSecret
    {
        $value = $this->redis->get(self::PREFIX.$id);
        $secret = is_string($value) ? StoredSecret::decode($value) : null;

        if ($secret === null) {
            return null;
        }

        if ($secret->isExpired()) {
            $this->forget($id);

            return null;
        }

        return $secret;
    }

    public function pull(string $id): ?StoredSecret
    {
        if ($this->find($id) === null) {
            return null;
        }

        $value = $this->redis->getdel(self::PREFIX.$id);
        $this->redis->zrem(self::INDEX, $id);

        return is_string($value) ? StoredSecret::decode($value) : null;
    }

    public function forget(string $id): void
    {
        $this->redis->del(self::PREFIX.$id);
        $this->redis->zrem(self::INDEX, $id);
    }

    public function count(): int
    {
        $this->redis->zremrangebyscore(self::INDEX, '-inf', (string) CarbonImmutable::now()->getTimestamp());

        return (int) $this->redis->zcard(self::INDEX);
    }
}
