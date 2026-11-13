<?php

namespace App\Mail;

use App\Auth\SocialUser;
use App\Enums\AuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Emails a link to a secret. The secret itself is never part of the email.
 */
class SecretLinkMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SocialUser $sender,
        public string $link,
        public CarbonImmutable $expiresAt,
        public bool $selfDestruct,
        public ?string $personalMessage = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->sender->email, $this->sender->name)],
            subject: $this->sender->name.' shared a secret with you',
        );
    }

    /**
     * Get the message content definition. The recipient signs in with the provider
     * configured for this installation before the secret can be viewed.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.secret-link',
            with: ['provider' => AuthProvider::current()->label()],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
