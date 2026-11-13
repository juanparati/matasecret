<?php

namespace Tests\Feature\Secrets;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SecretVaultTest extends TestCase
{
    public function test_audit_log_never_contains_the_secret_id_content_or_key(): void
    {
        Log::spy();

        $secret = $this->createSecret(content: 'hunter2', selfDestruct: false);

        $this->signIn('bob@example.com')
            ->postJson(route('secrets.reveal', $secret->id), ['key' => $secret->key])
            ->assertOk();

        foreach (['secret.created', 'secret.revealed'] as $event) {
            Log::shouldHaveReceived('info')->with($event, Mockery::on(function (array $context) use ($secret): bool {
                $logged = json_encode($context, JSON_THROW_ON_ERROR);

                return $context['secret'] === hash('sha256', $secret->id)
                    && ! str_contains($logged, 'hunter2')
                    && ! str_contains($logged, $secret->key)
                    && ! str_contains($logged, $secret->id);
            }))->once();
        }
    }
}
