<?php

namespace App\Secrets;

use Carbon\CarbonImmutable;

/**
 * A secret as kept in shared memory. The payload is double encrypted and the
 * creator's email is encrypted with the application key only, so it can be
 * shown on the reveal page before the secret itself is decrypted.
 */
final readonly class StoredSecret
{
    public function __construct(
        public string $payload,
        public string $encryptedCreatorEmail,
        public CarbonImmutable $expiresAt,
        public bool $selfDestruct,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt->lessThanOrEqualTo(CarbonImmutable::now());
    }

    /**
     * Seconds left before the secret expires.
     */
    public function secondsUntilExpiration(): int
    {
        return max(0, (int) CarbonImmutable::now()->diffInSeconds($this->expiresAt, false));
    }

    /**
     * Encode the secret as JSON for stores that keep plain strings, so reading
     * it back never unserializes arbitrary objects.
     */
    public function encode(): string
    {
        return json_encode([
            'payload' => $this->payload,
            'creator' => $this->encryptedCreatorEmail,
            'expires_at' => $this->expiresAt->getTimestamp(),
            'self_destruct' => $this->selfDestruct,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Decode a secret produced by encode(), returning null when it is malformed.
     */
    public static function decode(string $value): ?self
    {
        $data = json_decode($value, true);

        if (! is_array($data)
            || ! is_string($data['payload'] ?? null)
            || ! is_string($data['creator'] ?? null)
            || ! is_int($data['expires_at'] ?? null)
            || ! is_bool($data['self_destruct'] ?? null)) {
            return null;
        }

        return new self(
            payload: $data['payload'],
            encryptedCreatorEmail: $data['creator'],
            expiresAt: CarbonImmutable::createFromTimestamp($data['expires_at'], 'UTC'),
            selfDestruct: $data['self_destruct'],
        );
    }
}
