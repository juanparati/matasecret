<?php

namespace Tests\Feature\Http\Controllers;

use App\Auth\SocialUser;
use App\Mail\SecretLinkMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecretLinkControllerTest extends TestCase
{
    public function test_link_is_emailed_to_each_recipient_with_reply_to_the_sender(): void
    {
        Mail::fake();
        $secret = $this->createSecret(content: 'hunter2');

        $this->signIn('alice@example.com', 'Alice')
            ->postJson(route('secrets.email', $secret->id), [
                'key' => $secret->key,
                'recipients' => ['bob@example.com', 'Carol@Example.com'],
                'message' => 'The staging DB password',
            ])
            ->assertOk()
            ->assertExactJson(['sent' => ['bob@example.com', 'carol@example.com']]);

        Mail::assertSentCount(2);
        Mail::assertSent(SecretLinkMail::class, function (SecretLinkMail $mail) use ($secret): bool {
            return $mail->hasTo('bob@example.com')
                && $mail->hasReplyTo('alice@example.com')
                && $mail->link === route('secrets.show', $secret->id).'#'.$secret->key
                && $mail->personalMessage === 'The staging DB password';
        });
        Mail::assertSent(SecretLinkMail::class, fn (SecretLinkMail $mail): bool => $mail->hasTo('carol@example.com'));
    }

    public function test_recipients_from_any_allowed_domain_receive_the_link(): void
    {
        Mail::fake();
        config(['secrets.allowed_domains' => ['example.com', 'example.org']]);
        $secret = $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.email', $secret->id), [
                'key' => $secret->key,
                'recipients' => ['bob@example.com', 'carol@example.org'],
            ])
            ->assertOk()
            ->assertExactJson(['sent' => ['bob@example.com', 'carol@example.org']]);

        Mail::assertSentCount(2);
    }

    public function test_returns_404_and_sends_nothing_when_email_links_are_turned_off(): void
    {
        Mail::fake();
        config(['secrets.email_links' => false]);
        $secret = $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.email', $secret->id), [
                'key' => $secret->key,
                'recipients' => ['bob@example.com'],
            ])
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_email_contains_the_link_but_never_the_secret(): void
    {
        $secret = $this->createSecret(content: 'hunter2');
        $sender = new SocialUser('alice@example.com', 'Alice');

        $mail = new SecretLinkMail($sender, 'https://secrets.test/s/abc#key', $secret->expiresAt, true);

        $mail->assertSeeInHtml('https://secrets.test/s/abc#key', false)
            ->assertSeeInText('only be viewed')
            ->assertDontSeeInHtml('hunter2');
    }

    public function test_email_names_the_provider_the_recipient_signs_in_with(): void
    {
        config(['auth.social_provider' => 'okta']);
        $secret = $this->createSecret();

        $mail = new SecretLinkMail(new SocialUser('alice@example.com', 'Alice'), 'https://secrets.test/s/abc#key', $secret->expiresAt, true);

        $mail->assertSeeInText('You will be asked to sign in with your Okta account.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_returns_422_and_sends_nothing(array $payload, string $field, string $message): void
    {
        Mail::fake();
        $secret = $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.email', $secret->id), ['key' => $secret->key, ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);

        Mail::assertNothingSent();
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no recipients' => [['recipients' => []], 'recipients', 'Add at least one recipient.'],
            'recipient outside the domain' => [['recipients' => ['eve@evil.com']], 'recipients.0', 'Only @example.com addresses can receive secret links.'],
            'invalid email' => [['recipients' => ['not-an-email']], 'recipients.0', 'not-an-email is not a valid email address.'],
            'more than 10 recipients' => [['recipients' => array_map(fn (int $i): string => "user{$i}@example.com", range(1, 11))], 'recipients', 'You can send the link to at most 10 recipients at once.'],
            'message over 500 characters' => [['recipients' => ['bob@example.com'], 'message' => str_repeat('a', 501)], 'message', 'The message field must not be greater than 500 characters.'],
        ];
    }

    public function test_wrong_key_returns_404_and_sends_nothing(): void
    {
        Mail::fake();
        $secret = $this->createSecret();

        $this->signIn()
            ->postJson(route('secrets.email', $secret->id), [
                'key' => $this->createSecret()->key,
                'recipients' => ['bob@example.com'],
            ])
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_returns_429_when_more_than_20_recipients_per_hour(): void
    {
        Mail::fake();
        $secret = $this->createSecret();
        $recipients = fn (int $from): array => array_map(fn (int $i): string => "user{$i}@example.com", range($from, $from + 9));

        $this->signIn();
        $this->postJson(route('secrets.email', $secret->id), ['key' => $secret->key, 'recipients' => $recipients(1)])->assertOk();
        $this->postJson(route('secrets.email', $secret->id), ['key' => $secret->key, 'recipients' => $recipients(11)])->assertOk();

        $this->postJson(route('secrets.email', $secret->id), ['key' => $secret->key, 'recipients' => ['late@example.com']])
            ->assertTooManyRequests();

        Mail::assertSentCount(20);
    }

    public function test_guest_cannot_send_a_link(): void
    {
        Mail::fake();
        $secret = $this->createSecret();

        $this->postJson(route('secrets.email', $secret->id), ['key' => $secret->key, 'recipients' => ['bob@example.com']])
            ->assertUnauthorized();

        Mail::assertNothingSent();
    }
}
