<?php

namespace Tests\Unit\Secrets;

use App\Secrets\SecretCipher;
use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;

class SecretCipherTest extends TestCase
{
    public function test_encrypted_secret_decrypts_with_its_key(): void
    {
        $cipher = $this->cipher();

        ['payload' => $payload, 'key' => $key] = $cipher->encrypt("p4ss\nwörd 🔑");

        $this->assertSame("p4ss\nwörd 🔑", $cipher->decrypt($payload, $key));
        $this->assertStringNotContainsString('p4ss', $payload);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $key);
    }

    public function test_wrong_key_returns_null(): void
    {
        $cipher = $this->cipher();

        ['payload' => $payload] = $cipher->encrypt('secret');
        ['key' => $otherKey] = $cipher->encrypt('other');

        $this->assertNull($cipher->decrypt($payload, $otherKey));
    }

    public function test_malformed_key_returns_null(): void
    {
        $cipher = $this->cipher();

        ['payload' => $payload] = $cipher->encrypt('secret');

        $this->assertNull($cipher->decrypt($payload, 'not a key!'));
        $this->assertNull($cipher->decrypt($payload, 'c2hvcnQ'));
    }

    public function test_tampered_payload_returns_null(): void
    {
        $cipher = $this->cipher();

        ['payload' => $payload, 'key' => $key] = $cipher->encrypt('secret');

        $this->assertNull($cipher->decrypt(strrev($payload), $key));
    }

    public function test_payload_cannot_be_decrypted_with_a_different_application_key(): void
    {
        ['payload' => $payload, 'key' => $key] = $this->cipher()->encrypt('secret');

        $this->assertNull($this->cipher()->decrypt($payload, $key));
    }

    public function test_application_key_alone_does_not_reveal_the_secret(): void
    {
        $encrypter = new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC');

        ['payload' => $payload] = (new SecretCipher($encrypter))->encrypt('secret');

        $this->assertStringNotContainsString('secret', base64_decode($encrypter->decryptString($payload)));
    }

    private function cipher(): SecretCipher
    {
        return new SecretCipher(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));
    }
}
