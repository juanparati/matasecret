<?php

namespace App\Enums;

use App\Setup\SetupField;
use RuntimeException;

/**
 * The sign-in providers an installation can choose. Each value is the Socialite driver key,
 * which is also the "services" config key the driver reads. Test has no driver: it checks
 * the credentials in the "auth.test_account" config.
 */
enum AuthProvider: string
{
    case GoogleWorkspace = 'google';
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin-openid';
    case Slack = 'slack-openid';
    case Twitch = 'twitch';
    case OpenIdConnect = 'openidconnect';
    case Amazon = 'amazon';
    case Apple = 'apple';
    case Auth0 = 'auth0';
    case Clerk = 'clerk';
    case LaravelPassport = 'laravelpassport';
    case Microsoft = 'microsoft';
    case MicrosoftAzure = 'azure';
    case Nextcloud = 'nextcloud';
    case Okta = 'okta';
    case OneLogin = 'onelogin';
    case WordPress = 'wordpress';
    case Asana = 'asana';
    case Atlassian = 'atlassian';
    case Dropbox = 'dropbox';
    case Figma = 'figma';
    case Salesforce = 'salesforce';
    case Trello = 'trello';
    case Zendesk = 'zendesk';
    case Zoom = 'zoom';
    case Test = 'test';

    /**
     * The provider configured for this installation. The test provider fails loudly in
     * production instead of letting its fixed credentials in.
     */
    public static function current(): self
    {
        $provider = self::from((string) config('auth.social_provider'));

        if (! $provider->isAvailable()) {
            throw new RuntimeException('The test sign-in provider cannot be used in production.');
        }

        return $provider;
    }

    /**
     * Whether the provider can be used in this environment. The test provider signs in
     * with fixed credentials, so it only exists outside production.
     */
    public function isAvailable(): bool
    {
        return $this !== self::Test || ! app()->isProduction();
    }

    public function label(): string
    {
        return match ($this) {
            self::GoogleWorkspace => 'Google Workspace',
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
            self::Slack => 'Slack',
            self::Twitch => 'Twitch',
            self::OpenIdConnect => 'OpenID Connect',
            self::Amazon => 'Amazon',
            self::Apple => 'Apple',
            self::Auth0 => 'Auth0',
            self::Clerk => 'Clerk',
            self::LaravelPassport => 'Laravel Passport',
            self::Microsoft => 'Microsoft',
            self::MicrosoftAzure => 'Microsoft Azure',
            self::Nextcloud => 'Nextcloud',
            self::Okta => 'Okta',
            self::OneLogin => 'OneLogin',
            self::WordPress => 'WordPress.com',
            self::Asana => 'Asana',
            self::Atlassian => 'Atlassian',
            self::Dropbox => 'Dropbox',
            self::Figma => 'Figma',
            self::Salesforce => 'Salesforce',
            self::Trello => 'Trello',
            self::Zendesk => 'Zendesk',
            self::Zoom => 'Zoom',
            self::Test => 'Test',
        };
    }

    /**
     * The SocialiteProviders package, or null when no package is needed.
     */
    public function package(): ?string
    {
        return match ($this) {
            self::GoogleWorkspace, self::GitHub, self::GitLab, self::Bitbucket,
            self::Facebook, self::LinkedIn, self::Slack, self::Twitch, self::Test => null,
            self::MicrosoftAzure => 'socialiteproviders/microsoft-azure',
            default => 'socialiteproviders/'.$this->value,
        };
    }

    /**
     * The provider class registered with Socialite, or null for built-in drivers and
     * packages that register themselves. Kept as a string: the package may not be installed.
     */
    public function providerClass(): ?string
    {
        if ($this->package() === null || $this->registersItself()) {
            return null;
        }

        return 'SocialiteProviders\\'.match ($this) {
            self::LaravelPassport => 'LaravelPassport',
            self::MicrosoftAzure => 'Azure',
            self::OneLogin => 'Onelogin',
            self::WordPress => 'WordPress',
            self::Salesforce => 'SalesForce',
            default => $this->name,
        }.'\\Provider';
    }

    /**
     * The OAuth 1 server class for providers that do not use OAuth 2.
     */
    public function oauthOneServerClass(): ?string
    {
        return $this === self::Trello ? 'SocialiteProviders\\Trello\\Server' : null;
    }

    /**
     * Whether the package registers its own Socialite listener.
     */
    public function registersItself(): bool
    {
        return $this === self::OpenIdConnect;
    }

    public function clientIdLabel(): string
    {
        return match ($this) {
            self::Apple => 'Apple Services ID',
            self::Trello => 'Trello API key',
            default => $this->label().' client ID',
        };
    }

    public function clientSecretLabel(): string
    {
        return match ($this) {
            self::Trello => 'Trello API secret',
            default => $this->label().' client secret',
        };
    }

    /**
     * Apple signs its own client secret from the key ID, team ID and private key.
     */
    public function needsClientSecret(): bool
    {
        return $this !== self::Apple;
    }

    /**
     * Settings beyond the client ID and secret, keyed by their "services" config key.
     *
     * @return list<SetupField>
     */
    public function extraFields(): array
    {
        return match ($this) {
            self::GitLab => [
                new SetupField('host', 'GitLab URL', required: false, format: 'url', hint: 'Leave empty for gitlab.com.'),
            ],
            self::OpenIdConnect => [
                new SetupField('base_url', 'OpenID Connect issuer URL', format: 'url', hint: 'The URL that serves /.well-known/openid-configuration.'),
            ],
            self::Apple => [
                new SetupField('key_id', 'Apple key ID'),
                new SetupField('team_id', 'Apple team ID'),
                new SetupField('private_key', 'Path to the Apple private key (.p8)', format: 'file'),
            ],
            self::Auth0 => [
                new SetupField('base_url', 'Auth0 domain URL', format: 'url', hint: 'For example https://your-tenant.eu.auth0.com.'),
            ],
            self::Clerk => [
                new SetupField('base_url', 'Clerk Frontend API URL', format: 'url'),
            ],
            self::LaravelPassport => [
                new SetupField('host', 'Laravel Passport server URL', format: 'url'),
            ],
            self::Microsoft, self::MicrosoftAzure => [
                new SetupField('tenant', 'Microsoft tenant ID or domain', format: 'tenant'),
            ],
            self::Nextcloud => [
                new SetupField('instance_uri', 'Nextcloud URL', format: 'url'),
            ],
            self::Okta => [
                new SetupField('base_url', 'Okta organization URL', format: 'url', hint: 'For example https://your-org.okta.com.'),
                new SetupField('auth_server_id', 'Okta authorization server ID', required: false, hint: 'Leave empty for the org authorization server, or use "default".'),
            ],
            self::OneLogin => [
                new SetupField('base_url', 'OneLogin URL', format: 'url', hint: 'For example https://your-company.onelogin.com.'),
            ],
            self::Salesforce => [
                new SetupField('instance_url', 'Salesforce login URL', required: false, format: 'url', hint: 'Leave empty for https://login.salesforce.com.'),
            ],
            self::Zendesk => [
                new SetupField('subdomain', 'Zendesk subdomain', format: 'subdomain'),
            ],
            default => [],
        };
    }

    public function emailVerification(): EmailVerification
    {
        return match ($this) {
            self::GoogleWorkspace, self::LinkedIn, self::Slack, self::Apple, self::Auth0, self::Clerk,
            self::Okta, self::OneLogin, self::WordPress, self::Dropbox, self::Salesforce, self::Zoom => EmailVerification::Claim,
            self::OpenIdConnect => EmailVerification::ClaimIfPresent,
            self::GitHub, self::Bitbucket, self::Twitch, self::Microsoft, self::MicrosoftAzure => EmailVerification::Guaranteed,
            default => EmailVerification::Unverified,
        };
    }

    /**
     * The claim that says whether the email address is verified.
     */
    public function verifiedEmailClaim(): ?string
    {
        return match ($this->emailVerification()) {
            EmailVerification::Claim, EmailVerification::ClaimIfPresent => $this === self::Zoom ? 'verified' : 'email_verified',
            default => null,
        };
    }

    /**
     * Extra parameters sent with the authorization redirect.
     *
     * @param  list<string>  $allowedDomains
     * @return array<string, string>
     */
    public function redirectParameters(array $allowedDomains): array
    {
        if ($this !== self::GoogleWorkspace) {
            return [];
        }

        return [
            'hd' => count($allowedDomains) === 1 ? $allowedDomains[0] : '*',
            'prompt' => 'select_account',
        ];
    }

    /**
     * The environment variable that stores a "services" config key of the provider.
     */
    public static function environmentKey(string $configKey): string
    {
        return 'AUTH_'.strtoupper($configKey);
    }

    /**
     * Every package any provider can add, used to remove the ones no longer needed.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return array_values(array_filter(array_map(
            fn (self $provider): ?string => $provider->package(),
            self::cases(),
        )));
    }

    /**
     * The providers setup offers. The test provider is left out: setup configures production.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(
            fn (self $provider): array => ['value' => $provider->value, 'label' => $provider->label()],
            array_filter(self::cases(), fn (self $provider): bool => $provider !== self::Test),
        ), 'label', 'value');
    }
}
