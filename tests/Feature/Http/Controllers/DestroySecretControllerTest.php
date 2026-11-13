<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;

class DestroySecretControllerTest extends TestCase
{
    public function test_creator_with_the_key_destroys_the_secret(): void
    {
        $secret = $this->createSecret(selfDestruct: false);

        $this->signIn('alice@example.com')
            ->deleteJson(route('secrets.destroy', $secret->id), ['key' => $secret->key])
            ->assertNoContent();

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertNotFound();
    }

    public function test_another_user_cannot_destroy_the_secret(): void
    {
        $secret = $this->createSecret(selfDestruct: false);

        $this->signIn('bob@example.com')
            ->deleteJson(route('secrets.destroy', $secret->id), ['key' => $secret->key])
            ->assertNotFound();

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();
    }

    public function test_creator_with_a_wrong_key_cannot_destroy_the_secret(): void
    {
        $secret = $this->createSecret(selfDestruct: false);

        $this->signIn('alice@example.com')
            ->deleteJson(route('secrets.destroy', $secret->id), ['key' => $this->createSecret()->key])
            ->assertNotFound();

        $this->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])->assertOk();
    }
}
