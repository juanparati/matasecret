<?php

namespace App\Secrets;

interface SecretStore
{
    /**
     * Store a secret, returning false when it could not be stored.
     */
    public function put(string $id, StoredSecret $secret): bool;

    /**
     * Find a secret that has not expired yet.
     */
    public function find(string $id): ?StoredSecret;

    /**
     * Atomically remove and return a secret. When several requests race for
     * the same secret only one of them receives it.
     */
    public function pull(string $id): ?StoredSecret;

    public function forget(string $id): void;

    /**
     * Number of secrets currently stored.
     */
    public function count(): int;
}
