<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\SecretExpiration;
use App\Secrets\SecretStore;
use App\Secrets\SecretVault;
use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreSecretControllerTest extends TestCase
{
    public function test_guest_cannot_create_a_secret(): void
    {
        $this->postJson(route('secrets.store'), $this->payload())->assertUnauthorized();
    }

    public function test_create_page_renders_the_form_options(): void
    {
        $this->signIn()
            ->get(route('secrets.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Secrets/Create')
                ->where('defaultExpiration', '2h')
                ->where('maxLength', 5120)
                ->where('domains', ['example.com'])
                ->where('emailLinks', true)
                ->has('expirationOptions', count(SecretExpiration::cases())));
    }

    public function test_create_page_hides_email_links_when_they_are_turned_off(): void
    {
        config(['secrets.email_links' => false]);

        $this->signIn()
            ->get(route('secrets.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('emailLinks', false));
    }

    public function test_valid_secret_is_stored_and_returns_201_with_the_key(): void
    {
        $this->freezeTime();

        $response = $this->signIn()->postJson(route('secrets.store'), $this->payload([
            'content' => "  line one\nline two  ",
        ]));

        $response->assertCreated()
            ->assertJsonStructure(['id', 'key', 'url', 'expiresAt', 'selfDestruct'])
            ->assertJsonPath('expiresAt', CarbonImmutable::now()->addHours(2)->toIso8601String())
            ->assertJsonPath('selfDestruct', true)
            ->assertJsonPath('url', route('secrets.show', $response->json('id')));

        $vault = $this->app->make(SecretVault::class);
        $secret = $vault->findWithKey($response->json('id'), $response->json('key'));

        $this->assertNotNull($secret);
        $this->assertSame('alice@example.com', $vault->creatorEmail($secret));
        $this->assertSame("  line one\nline two  ", $vault->reveal($response->json('id'), $response->json('key'), 'bob@example.com'));
        $this->assertStringNotContainsString('line one', $response->getContent());
    }

    #[DataProvider('presetExpirations')]
    public function test_preset_expiration_sets_the_expiry(SecretExpiration $expiration, int $seconds): void
    {
        $this->freezeTime();

        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload(['expiration' => $expiration->value]))
            ->assertCreated()
            ->assertJsonPath('expiresAt', CarbonImmutable::now()->addSeconds($seconds)->toIso8601String());
    }

    /**
     * @return array<string, array{SecretExpiration, int}>
     */
    public static function presetExpirations(): array
    {
        return [
            '1 minute' => [SecretExpiration::OneMinute, 60],
            '2 hours' => [SecretExpiration::TwoHours, 7200],
            '1 week' => [SecretExpiration::OneWeek, 604800],
            '1 month' => [SecretExpiration::OneMonth, 2592000],
        ];
    }

    public function test_custom_expiration_uses_the_given_date_in_utc(): void
    {
        $this->freezeTime();

        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload([
                'expiration' => 'custom',
                'expires_at' => CarbonImmutable::now()->addDay()->setTimezone('America/Bogota')->toIso8601String(),
            ]))
            ->assertCreated()
            ->assertJsonPath('expiresAt', CarbonImmutable::now()->utc()->addDay()->toIso8601String());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_returns_422(array $overrides, string $field, string $message): void
    {
        $this->freezeTime();

        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'empty content' => [['content' => ''], 'content', 'The secret cannot be empty.'],
            'whitespace only content' => [['content' => "  \n\t "], 'content', 'The secret cannot be empty.'],
            'content over 5120 characters' => [['content' => str_repeat('ñ', 5121)], 'content', 'The secret may not be longer than 5120 characters.'],
            'unknown expiration' => [['expiration' => '3h'], 'expiration', 'The selected expiration is invalid.'],
            'custom without a date' => [['expiration' => 'custom'], 'expires_at', 'Choose when the secret should expire.'],
            'custom in the past' => [['expiration' => 'custom', 'expires_at' => '2000-01-01T00:00:00Z'], 'expires_at', 'The expiration must be at least one minute in the future.'],
            'custom under one minute' => [['expiration' => 'custom', 'expires_at' => '+30 seconds'], 'expires_at', 'The expiration must be at least one minute in the future.'],
            'custom over 30 days' => [['expiration' => 'custom', 'expires_at' => '+31 days'], 'expires_at', 'The expiration cannot be more than 30 days in the future.'],
        ];
    }

    public function test_accepts_exactly_5120_multibyte_characters(): void
    {
        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload(['content' => str_repeat('🔑', 5120)]))
            ->assertCreated();
    }

    public function test_returns_503_when_the_store_is_full(): void
    {
        config(['secrets.max_stored' => 1]);
        $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload())
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The secret storage is full. Please try again later.');
    }

    public function test_returns_503_when_shared_memory_rejects_the_write(): void
    {
        $this->mock(SecretStore::class, function (MockInterface $store): void {
            $store->shouldReceive('count')->andReturn(0);
            $store->shouldReceive('put')->andReturn(false);
        });

        $this->signIn()
            ->postJson(route('secrets.store'), $this->payload())
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The secret storage is full. Please try again later.');
    }

    public function test_returns_429_after_30_secrets_per_hour(): void
    {
        $this->signIn();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(route('secrets.store'), $this->payload())->assertCreated();
        }

        $this->postJson(route('secrets.store'), $this->payload())->assertTooManyRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        if (isset($overrides['expires_at']) && str_starts_with((string) $overrides['expires_at'], '+')) {
            $overrides['expires_at'] = CarbonImmutable::now()->modify((string) $overrides['expires_at'])->toIso8601String();
        }

        return [
            'content' => 'hunter2',
            'expiration' => '2h',
            'expires_at' => null,
            'self_destruct' => true,
            ...$overrides,
        ];
    }
}
