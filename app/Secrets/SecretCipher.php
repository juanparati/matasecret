<?php

namespace App\Secrets;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use SodiumException;

/**
 * Encrypts secrets in two layers: an inner libsodium secretbox keyed with a
 * random per-secret key that is never stored, and an outer layer using the
 * application key. Reading a secret requires both the link and APP_KEY.
 */
class SecretCipher
{
    public function __construct(private Encrypter $encrypter) {}

    /**
     * @return array{payload: string, key: string}
     */
    public function encrypt(string $plaintext): array
    {
        $key = sodium_crypto_secretbox_keygen();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $inner = sodium_bin2base64(
            $nonce.sodium_crypto_secretbox($plaintext, $nonce, $key),
            SODIUM_BASE64_VARIANT_ORIGINAL,
        );

        $encodedKey = sodium_bin2base64($key, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        sodium_memzero($key);

        return [
            'payload' => $this->encrypter->encryptString($inner),
            'key' => $encodedKey,
        ];
    }

    /**
     * Decrypt a payload, returning null when the key is wrong or the payload was tampered with.
     */
    public function decrypt(string $payload, string $encodedKey): ?string
    {
        try {
            $key = sodium_base642bin($encodedKey, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $inner = sodium_base642bin($this->encrypter->decryptString($payload), SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (DecryptException|SodiumException) {
            return null;
        }

        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || strlen($inner) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($inner, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($inner, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $key,
        );

        sodium_memzero($key);

        return $plaintext === false ? null : $plaintext;
    }
}
