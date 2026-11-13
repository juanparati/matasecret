<?php

namespace App\Setup;

use App\Enums\AuthProvider;
use App\Enums\EmailVerification;
use App\Enums\MailProvider;
use App\Enums\SecretStoreDriver;
use App\Enums\WebServer;

/**
 * Everything the setup command asked, and what it turns into: environment variables,
 * Composer packages and the summary shown before anything is written.
 */
final readonly class SetupAnswers
{
    /**
     * @param  int  $port  The public HTTPS port, or the port FrankenPHP listens on behind a proxy.
     * @param  array{host: string, port: string, username: string, password: string, database: string}|null  $redis
     * @param  array<string, string>  $authSettings  Extra provider settings keyed by "services" config key.
     * @param  list<string>  $allowedDomains
     * @param  array<string, string>  $mailSettings  Transport settings keyed by environment variable.
     */
    public function __construct(
        public WebServer $webServer,
        public string $domain,
        public int $port,
        public bool $behindProxy,
        public string $listenAddress,
        public string $trustedProxies,
        public SecretStoreDriver $secretStore,
        public ?array $redis,
        public AuthProvider $authProvider,
        public string $clientId,
        public string $clientSecret,
        public array $authSettings,
        public array $allowedDomains,
        public MailProvider $mailProvider,
        public array $mailSettings,
        public string $mailFromAddress,
        public string $mailFromName,
    ) {}

    public function appUrl(): string
    {
        $publicPort = $this->behindProxy ? 443 : $this->port;

        return 'https://'.$this->domain.($publicPort === 443 ? '' : ':'.$publicPort);
    }

    public function redirectUri(): string
    {
        return $this->appUrl().'/auth/callback';
    }

    /**
     * The variables written to .env, apart from APP_KEY.
     *
     * @return array<string, string>
     */
    public function environment(): array
    {
        $environment = [
            'APP_NAME' => 'MataSecret',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $this->appUrl(),
            'LOG_LEVEL' => 'warning',
            'SESSION_SECURE_COOKIE' => 'true',
        ];

        if ($this->webServer === WebServer::FrankenPhp) {
            $environment['OCTANE_SERVER'] = 'frankenphp';
            $environment['OCTANE_HOST'] = $this->behindProxy ? $this->listenAddress : $this->domain;
            $environment['OCTANE_PORT'] = (string) $this->port;
            $environment['OCTANE_HTTPS'] = 'true';

            if ($this->behindProxy) {
                $environment['TRUSTED_PROXIES'] = $this->trustedProxies;
            }
        }

        $environment['CACHE_STORE'] = $this->secretStore->cacheStore();
        $environment['SECRETS_STORE'] = $this->secretStore->value;

        if ($this->redis !== null) {
            $environment['REDIS_HOST'] = $this->redis['host'];
            $environment['REDIS_PORT'] = $this->redis['port'];
            $environment['REDIS_USERNAME'] = $this->redis['username'];
            $environment['REDIS_PASSWORD'] = $this->redis['password'] === '' ? 'null' : $this->redis['password'];
            $environment['REDIS_SECRETS_DB'] = $this->redis['database'];
        }

        $environment['AUTH_PROVIDER'] = $this->authProvider->value;
        $environment['AUTH_CLIENT_ID'] = $this->clientId;

        if ($this->authProvider->needsClientSecret()) {
            $environment['AUTH_CLIENT_SECRET'] = $this->clientSecret;
        }

        foreach ($this->authSettings as $key => $value) {
            $environment[AuthProvider::environmentKey($key)] = $value;
        }

        $environment['AUTH_ALLOWED_DOMAINS'] = implode(',', $this->allowedDomains);

        $environment['MAIL_MAILER'] = $this->mailProvider->mailer();
        $environment = [...$environment, ...$this->mailSettings];

        if ($this->mailProvider !== MailProvider::None) {
            $environment['MAIL_FROM_ADDRESS'] = $this->mailFromAddress;
            $environment['MAIL_FROM_NAME'] = $this->mailFromName;
        }

        $environment['SECRETS_EMAIL_LINKS'] = $this->mailProvider === MailProvider::None ? 'false' : 'true';

        return array_filter($environment, fn (string $value): bool => $value !== '');
    }

    /**
     * Prefixes of template variables that do not apply to these answers.
     *
     * @return list<string>
     */
    public function obsoleteVariables(): array
    {
        return array_values(array_filter([
            'GOOGLE_',
            $this->webServer === WebServer::Traditional ? 'OCTANE_' : null,
            $this->behindProxy ? null : 'TRUSTED_PROXIES',
            $this->authProvider->needsClientSecret() ? null : 'AUTH_CLIENT_SECRET',
        ]));
    }

    /**
     * The Composer packages the chosen provider and mailer need.
     *
     * @return list<string>
     */
    public function packages(): array
    {
        return array_values(array_unique(array_filter([
            $this->authProvider->package(),
            ...$this->mailProvider->packages(),
        ])));
    }

    /**
     * Every package setup manages, so it can remove the ones no longer selected.
     *
     * @return list<string>
     */
    public static function managedPackages(): array
    {
        return array_values(array_unique([...AuthProvider::catalog(), ...MailProvider::catalog()]));
    }

    /**
     * The PHP extensions the web server will need.
     *
     * @return list<string>
     */
    public function requiredExtensions(): array
    {
        return array_values(array_unique(array_filter([
            $this->secretStore->extension(),
            $this->secretStore->cacheStore() === 'apc' ? 'apcu' : null,
        ])));
    }

    /**
     * The answers grouped by section, with secrets masked.
     *
     * @return array<string, list<array{string, string}>>
     */
    public function summary(): array
    {
        $webServer = [['Server', $this->webServer->label()], ['Domain', $this->domain]];

        if ($this->webServer === WebServer::FrankenPhp && $this->behindProxy) {
            $webServer[] = ['TLS certificate', 'Handled by the reverse proxy'];
            $webServer[] = ['Listen address', $this->listenAddress];
            $webServer[] = ['Listen port', (string) $this->port];
            $webServer[] = ['Trusted proxies', $this->trustedProxies];
        } else {
            $webServer[] = ['TLS certificate', $this->webServer === WebServer::FrankenPhp ? 'Issued by FrankenPHP' : 'Handled by the web server'];
            $webServer[] = ['Port', (string) $this->port];
        }

        $webServer[] = ['Application URL', $this->appUrl()];

        $secretStore = [['Store', $this->secretStore->label()]];

        if ($this->redis !== null) {
            $secretStore[] = ['Redis host', $this->redis['host']];
            $secretStore[] = ['Redis port', $this->redis['port']];
            $secretStore[] = ['Redis username', self::display($this->redis['username'])];
            $secretStore[] = ['Redis password', self::mask($this->redis['password'])];
            $secretStore[] = ['Redis database', $this->redis['database']];
        }

        $signIn = [
            ['Provider', $this->authProvider->label()],
            [$this->authProvider->clientIdLabel(), $this->clientId],
        ];

        if ($this->authProvider->needsClientSecret()) {
            $signIn[] = [$this->authProvider->clientSecretLabel(), self::mask($this->clientSecret)];
        }

        foreach ($this->authProvider->extraFields() as $field) {
            $signIn[] = [$field->label, self::display($this->authSettings[$field->key] ?? '')];
        }

        $signIn[] = ['Allowed email domains', implode(', ', $this->allowedDomains)];
        $signIn[] = ['Redirect URI', $this->redirectUri()];

        $mail = [['Mailer', $this->mailProvider->label()]];

        foreach ($this->mailProvider->fields() as $field) {
            $value = $this->mailSettings[$field->key] ?? '';
            $mail[] = [$field->label, $field->secret ? self::mask($value) : self::display($field->options[$value] ?? $value)];
        }

        if ($this->mailProvider !== MailProvider::None) {
            $mail[] = ['From address', $this->mailFromAddress];
            $mail[] = ['From name', $this->mailFromName];
        }

        return [
            'Web server' => $webServer,
            'Secret store' => $secretStore,
            'Sign-in' => $signIn,
            'Mail' => $mail,
        ];
    }

    /**
     * Risks worth reading before confirming.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->authProvider->emailVerification() === EmailVerification::Unverified) {
            $warnings[] = "{$this->authProvider->label()} does not say whether an email address is verified, so the allowed-domain check trusts the address {$this->authProvider->label()} returns.";
        }

        if ($this->webServer === WebServer::FrankenPhp && ! $this->behindProxy && $this->port !== 443) {
            $warnings[] = "FrankenPHP can only obtain a certificate automatically when {$this->domain} is reachable on ports 80 and 443.";
        }

        if ($this->mailProvider === MailProvider::None) {
            $warnings[] = 'Without a mailer, secret links cannot be sent by email.';
        }

        return $warnings;
    }

    /**
     * What is left to do once setup has finished.
     *
     * @return list<string>
     */
    public function nextSteps(string $publicPath): array
    {
        $steps = [
            "Register this redirect URI with {$this->authProvider->label()}: {$this->redirectUri()}",
            'Build the frontend assets: pnpm install && pnpm run build',
        ];

        if ($this->webServer === WebServer::Traditional) {
            $steps[] = "Point the document root of {$this->domain} at {$publicPath} and reload PHP-FPM.";
        } elseif ($this->behindProxy) {
            $steps[] = 'Start the server: php artisan octane:start --server=frankenphp';
            $steps[] = "Point your reverse proxy at http://{$this->listenAddress}:{$this->port}.";
        } else {
            $steps[] = 'Start the server: php artisan octane:start --server=frankenphp --https'.($this->port === 443 ? ' --http-redirect' : '');
        }

        return $steps;
    }

    /**
     * Show the first characters of a secret only, never its length.
     */
    public static function mask(string $secret): string
    {
        if ($secret === '') {
            return '(not set)';
        }

        return mb_strlen($secret) < 8 ? '••••••' : mb_substr($secret, 0, 4).'••••••';
    }

    private static function display(string $value): string
    {
        return $value === '' ? '(not set)' : $value;
    }
}
