<?php

namespace Tests\Unit\Secrets;

use App\Secrets\StoredSecret;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoredSecretTest extends TestCase
{
    public function test_encoded_secret_decodes_to_the_same_secret(): void
    {
        $secret = new StoredSecret('payload', 'creator', CarbonImmutable::parse('2026-11-10 12:30:00', 'UTC'), true);

        $decoded = StoredSecret::decode($secret->encode());

        $this->assertNotNull($decoded);
        $this->assertSame('payload', $decoded->payload);
        $this->assertSame('creator', $decoded->encryptedCreatorEmail);
        $this->assertTrue($decoded->expiresAt->equalTo($secret->expiresAt));
        $this->assertTrue($decoded->selfDestruct);
    }

    #[DataProvider('malformedValues')]
    public function test_malformed_value_decodes_to_null(string $value): void
    {
        $this->assertNull(StoredSecret::decode($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedValues(): array
    {
        return [
            'not json' => ['not json'],
            'serialized object' => [serialize(new StoredSecret('payload', 'creator', CarbonImmutable::now(), true))],
            'missing fields' => ['{"payload":"payload"}'],
            'wrong types' => ['{"payload":"payload","creator":"creator","expires_at":"soon","self_destruct":1}'],
        ];
    }
}
