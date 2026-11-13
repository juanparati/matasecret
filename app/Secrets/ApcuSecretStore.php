<?php

namespace App\Secrets;

use APCUIterator;
use RuntimeException;

/**
 * Keeps secrets in APCu shared memory, which is shared by every FrankenPHP
 * worker thread and wiped whenever the server process stops.
 */
class ApcuSecretStore implements SecretStore
{
    private const string PREFIX = 'ephesecret:secret:';

    public function __construct()
    {
        if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
            throw new RuntimeException('The APCu extension must be installed and enabled to store secrets.');
        }
    }

    public function put(string $id, StoredSecret $secret): bool
    {
        return apcu_add(self::PREFIX.$id, $secret, max(1, $secret->secondsUntilExpiration()));
    }

    public function find(string $id): ?StoredSecret
    {
        $secret = apcu_fetch(self::PREFIX.$id, $success);

        if (! $success || ! $secret instanceof StoredSecret) {
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
        $secret = $this->find($id);

        if ($secret === null || ! apcu_delete(self::PREFIX.$id)) {
            return null;
        }

        return $secret;
    }

    public function forget(string $id): void
    {
        apcu_delete(self::PREFIX.$id);
    }

    public function count(): int
    {
        return (new APCUIterator('/^'.preg_quote(self::PREFIX, '/').'/', APC_ITER_NONE))->getTotalCount();
    }
}
