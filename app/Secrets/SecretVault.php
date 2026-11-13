<?php

namespace App\Secrets;

use App\Exceptions\SecretStorageFullException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

/**
 * Creates, reveals and destroys secrets, writing a metadata-only audit trail.
 * Secret contents and keys are never logged.
 */
class SecretVault
{
    public function __construct(
        private SecretStore $store,
        private SecretCipher $cipher,
        private Encrypter $encrypter,
        private int $maxStored,
    ) {}

    /**
     * @throws SecretStorageFullException
     */
    public function create(string $content, CarbonImmutable $expiresAt, bool $selfDestruct, string $creatorEmail): CreatedSecret
    {
        if ($this->store->count() >= $this->maxStored) {
            $this->audit('secret.rejected', null, $creatorEmail, ['reason' => 'storage_full']);

            throw new SecretStorageFullException;
        }

        ['payload' => $payload, 'key' => $key] = $this->cipher->encrypt($content);

        $secret = new StoredSecret(
            payload: $payload,
            encryptedCreatorEmail: $this->encrypter->encryptString($creatorEmail),
            expiresAt: $expiresAt->utc(),
            selfDestruct: $selfDestruct,
        );

        $id = $this->generateId();

        // Ids are 128-bit random values, so a failed write means shared memory is full.
        if (! $this->store->put($id, $secret)) {
            $this->audit('secret.rejected', null, $creatorEmail, ['reason' => 'storage_write_failed']);

            throw new SecretStorageFullException;
        }

        $this->audit('secret.created', $id, $creatorEmail, [
            'expires_at' => $secret->expiresAt->toIso8601String(),
            'self_destruct' => $selfDestruct,
        ]);

        return new CreatedSecret($id, $key, $secret->expiresAt, $selfDestruct);
    }

    /**
     * Find a secret that is still available.
     */
    public function find(string $id): ?StoredSecret
    {
        return $this->store->find($id);
    }

    /**
     * Find a secret only when the given key decrypts it.
     */
    public function findWithKey(string $id, string $key): ?StoredSecret
    {
        $secret = $this->store->find($id);

        if ($secret === null || $this->cipher->decrypt($secret->payload, $key) === null) {
            return null;
        }

        return $secret;
    }

    /**
     * Decrypt a secret, destroying it first when it self-destructs.
     */
    public function reveal(string $id, string $key, string $viewerEmail): ?string
    {
        $secret = $this->store->find($id);
        $content = $secret === null ? null : $this->cipher->decrypt($secret->payload, $key);

        if ($secret === null || $content === null) {
            $this->audit('secret.rejected', $id, $viewerEmail, ['reason' => 'reveal_unavailable']);

            return null;
        }

        if ($secret->selfDestruct && $this->store->pull($id) === null) {
            $this->audit('secret.rejected', $id, $viewerEmail, ['reason' => 'reveal_unavailable']);

            return null;
        }

        $this->audit('secret.revealed', $id, $viewerEmail, ['self_destruct' => $secret->selfDestruct]);

        return $content;
    }

    /**
     * Destroy a secret. Only its creator, holding the key, may do so.
     */
    public function destroy(string $id, string $key, string $actorEmail): bool
    {
        $secret = $this->findWithKey($id, $key);

        if ($secret === null || $this->creatorEmail($secret) !== $actorEmail) {
            $this->audit('secret.rejected', $id, $actorEmail, ['reason' => 'destroy_denied']);

            return false;
        }

        $this->store->forget($id);

        $this->audit('secret.destroyed', $id, $actorEmail);

        return true;
    }

    public function creatorEmail(StoredSecret $secret): ?string
    {
        try {
            return $this->encrypter->decryptString($secret->encryptedCreatorEmail);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Write a metadata-only audit log entry. The secret id is hashed so log
     * readers cannot reconstruct links.
     *
     * @param  array<string, mixed>  $context
     */
    public function audit(string $event, ?string $id, ?string $actorEmail, array $context = []): void
    {
        Log::info($event, [
            'secret' => $id === null ? null : hash('sha256', $id),
            'actor' => $actorEmail,
            ...$context,
        ]);
    }

    private function generateId(): string
    {
        return sodium_bin2base64(random_bytes(16), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
