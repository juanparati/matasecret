<?php

namespace Tests;

use App\Auth\SocialUser;
use App\Secrets\CreatedSecret;
use App\Secrets\SecretVault;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Sign in as a user of the configured provider by seeding the session.
     */
    protected function signIn(string $email = 'alice@example.com', string $name = 'Alice'): static
    {
        return $this->withSession([
            SocialUser::SESSION_KEY => (new SocialUser($email, $name))->toArray(),
        ]);
    }

    /**
     * Store a secret directly through the vault.
     */
    protected function createSecret(
        string $content = 'hunter2',
        bool $selfDestruct = true,
        string $creatorEmail = 'alice@example.com',
        ?CarbonImmutable $expiresAt = null,
    ): CreatedSecret {
        return $this->app->make(SecretVault::class)->create(
            content: $content,
            expiresAt: $expiresAt ?? CarbonImmutable::now()->addHour(),
            selfDestruct: $selfDestruct,
            creatorEmail: $creatorEmail,
        );
    }
}
