<?php

namespace App\Secrets;

/**
 * In-process secret store used by the test suite. Not shared between workers.
 */
class ArraySecretStore implements SecretStore
{
    /**
     * @var array<string, StoredSecret>
     */
    private array $secrets = [];

    public function put(string $id, StoredSecret $secret): bool
    {
        if ($this->find($id) !== null) {
            return false;
        }

        $this->secrets[$id] = $secret;

        return true;
    }

    public function find(string $id): ?StoredSecret
    {
        $secret = $this->secrets[$id] ?? null;

        if ($secret?->isExpired()) {
            $this->forget($id);

            return null;
        }

        return $secret;
    }

    public function pull(string $id): ?StoredSecret
    {
        $secret = $this->find($id);

        $this->forget($id);

        return $secret;
    }

    public function forget(string $id): void
    {
        unset($this->secrets[$id]);
    }

    public function count(): int
    {
        return count(array_filter($this->secrets, fn (StoredSecret $secret): bool => ! $secret->isExpired()));
    }
}
