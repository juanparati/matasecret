<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;

class RevealSecretControllerTest extends TestCase
{
    private const string UNAVAILABLE = 'This secret does not exist or is no longer available.';

    public function test_guest_opening_a_link_is_redirected_to_login(): void
    {
        $secret = $this->createSecret();

        $this->get(route('secrets.show', $secret->id))->assertRedirect(route('login'));
    }

    public function test_reveal_page_shows_metadata_without_the_content(): void
    {
        $this->freezeTime();
        $secret = $this->createSecret(content: 'hunter2');

        $this->signIn('bob@example.com')
            ->get(route('secrets.show', $secret->id))
            ->assertOk()
            ->assertDontSee('hunter2')
            ->assertInertia(fn ($page) => $page
                ->component('Secrets/Reveal')
                ->where('secret.sharedBy', 'alice@example.com')
                ->where('secret.expiresAt', $secret->expiresAt->toIso8601String())
                ->where('secret.selfDestruct', true)
                ->where('visibleSeconds', 60));
    }

    public function test_reveal_page_for_a_missing_secret_has_no_metadata(): void
    {
        $this->signIn()
            ->get(route('secrets.show', 'AAAAAAAAAAAAAAAAAAAAAA'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('secret', null));
    }

    public function test_correct_key_returns_the_content(): void
    {
        $secret = $this->createSecret(content: 'hunter2');

        $this->signIn('bob@example.com')
            ->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])
            ->assertOk()
            ->assertExactJson(['content' => 'hunter2'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_self_destructing_secret_returns_404_on_the_second_reveal(): void
    {
        $secret = $this->createSecret(selfDestruct: true);

        $this->signIn('bob@example.com');
        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])
            ->assertNotFound()
            ->assertExactJson(['message' => self::UNAVAILABLE]);
    }

    public function test_secret_without_self_destruct_can_be_revealed_repeatedly(): void
    {
        $secret = $this->createSecret(content: 'hunter2', selfDestruct: false);

        $this->signIn('bob@example.com');
        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])
            ->assertExactJson(['content' => 'hunter2']);
    }

    public function test_wrong_key_returns_404_and_does_not_destroy_the_secret(): void
    {
        $secret = $this->createSecret(selfDestruct: true);
        $wrongKey = $this->createSecret()->key;

        $this->signIn('bob@example.com');
        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $wrongKey])
            ->assertNotFound()
            ->assertExactJson(['message' => self::UNAVAILABLE]);

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();
    }

    public function test_expired_secret_returns_404(): void
    {
        $secret = $this->createSecret(selfDestruct: false);

        $this->travel(61)->minutes();

        $this->signIn('bob@example.com')
            ->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])
            ->assertNotFound()
            ->assertExactJson(['message' => self::UNAVAILABLE]);
    }

    public function test_missing_key_returns_422(): void
    {
        $secret = $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.reveal', $secret->id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_returns_429_after_10_reveal_attempts_per_minute(): void
    {
        $secret = $this->createSecret(selfDestruct: false);

        $this->signIn('bob@example.com');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();
        }

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertTooManyRequests();
    }
}
