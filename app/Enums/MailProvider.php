<?php

namespace App\Enums;

use App\Setup\SetupField;

enum MailProvider: string
{
    case Smtp = 'smtp';
    case Mailgun = 'mailgun';
    case Cloudflare = 'cloudflare';
    case Postmark = 'postmark';
    case Resend = 'resend';
    case Ses = 'ses';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Mailgun => 'Mailgun',
            self::Cloudflare => 'Cloudflare',
            self::Postmark => 'Postmark',
            self::Resend => 'Resend',
            self::Ses => 'Amazon SES',
            self::None => 'None (do not send emails)',
        };
    }

    /**
     * The MAIL_MAILER value. Without a mailer, mail goes to the log and email links are turned off.
     */
    public function mailer(): string
    {
        return $this === self::None ? 'log' : $this->value;
    }

    /**
     * The Composer packages the transport needs.
     *
     * @return list<string>
     */
    public function packages(): array
    {
        return match ($this) {
            self::Mailgun => ['symfony/mailgun-mailer', 'symfony/http-client'],
            self::Postmark => ['symfony/postmark-mailer', 'symfony/http-client'],
            self::Cloudflare => ['symfony/http-client'],
            self::Resend => ['resend/resend-php'],
            self::Ses => ['aws/aws-sdk-php'],
            self::Smtp, self::None => [],
        };
    }

    /**
     * The settings asked for this transport, keyed by environment variable.
     *
     * @return list<SetupField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Smtp => [
                new SetupField('MAIL_HOST', 'SMTP host', format: 'host'),
                new SetupField('MAIL_PORT', 'SMTP port', format: 'port', default: '587'),
                new SetupField('MAIL_SCHEME', 'SMTP encryption', options: [
                    'smtp' => 'STARTTLS (usually port 587)',
                    'smtps' => 'Implicit TLS (usually port 465)',
                ], default: 'smtp'),
                new SetupField('MAIL_USERNAME', 'SMTP username', required: false),
                new SetupField('MAIL_PASSWORD', 'SMTP password', required: false, secret: true),
            ],
            self::Mailgun => [
                new SetupField('MAILGUN_DOMAIN', 'Mailgun sending domain', format: 'host'),
                new SetupField('MAILGUN_SECRET', 'Mailgun API key', secret: true),
                new SetupField('MAILGUN_ENDPOINT', 'Mailgun region', options: [
                    'api.mailgun.net' => 'US (api.mailgun.net)',
                    'api.eu.mailgun.net' => 'EU (api.eu.mailgun.net)',
                ], default: 'api.mailgun.net'),
            ],
            self::Cloudflare => [
                new SetupField('CLOUDFLARE_ACCOUNT_ID', 'Cloudflare account ID'),
                new SetupField('CLOUDFLARE_KEY', 'Cloudflare API token', secret: true),
            ],
            self::Postmark => [
                new SetupField('POSTMARK_API_KEY', 'Postmark server token', secret: true),
                new SetupField('POSTMARK_MESSAGE_STREAM_ID', 'Postmark message stream', required: false, hint: 'Leave empty to use the default transactional stream.'),
            ],
            self::Resend => [
                new SetupField('RESEND_API_KEY', 'Resend API key', secret: true),
            ],
            self::Ses => [
                new SetupField('AWS_ACCESS_KEY_ID', 'AWS access key ID'),
                new SetupField('AWS_SECRET_ACCESS_KEY', 'AWS secret access key', secret: true),
                new SetupField('AWS_DEFAULT_REGION', 'AWS region', default: 'us-east-1'),
            ],
            self::None => [],
        };
    }

    /**
     * Every package any transport can add, used to remove the ones no longer needed.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (self $provider): array => $provider->packages(),
            self::cases(),
        ))));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(
            fn (self $provider): array => ['value' => $provider->value, 'label' => $provider->label()],
            self::cases(),
        ), 'label', 'value');
    }
}
