<?php

namespace App\Setup;

/**
 * A single value the setup command asks for, such as a client ID or an SMTP host.
 */
final readonly class SetupField
{
    private const array SHARED_MICROSOFT_TENANTS = ['common', 'organizations', 'consumers'];

    /**
     * @param  string  $key  The environment variable or config key that stores the answer.
     * @param  'text'|'url'|'host'|'port'|'integer'|'ip'|'proxies'|'email'|'file'|'tenant'|'subdomain'  $format
     * @param  array<string, string>  $options  When given, the answer is chosen from these values and labels.
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $required = true,
        public bool $secret = false,
        public string $format = 'text',
        public string $default = '',
        public string $hint = '',
        public array $options = [],
    ) {}

    /**
     * Return an error message for an invalid answer, or null when it is acceptable.
     */
    public function validate(string $value): ?string
    {
        if ($value === '') {
            return $this->required ? "The {$this->label} is required." : null;
        }

        return match ($this->format) {
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($value), 'https://')
                ? null
                : 'Enter a full URL starting with https://.',
            'host' => self::isHostname($value) ? null : 'Enter a host name like example.com.',
            'port' => self::isPort($value) ? null : 'Enter a port between 1 and 65535.',
            'integer' => ctype_digit($value) ? null : 'Enter a whole number.',
            'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false ? null : 'Enter an IP address such as 127.0.0.1.',
            'proxies' => self::isProxyList($value) ? null : 'Enter IP addresses or CIDR ranges separated by commas, or * to trust any proxy.',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter a valid email address.',
            'file' => is_file($value) && is_readable($value) ? null : 'The file does not exist or cannot be read.',
            'tenant' => in_array(strtolower($value), self::SHARED_MICROSOFT_TENANTS, true)
                ? "Use your own tenant ID or domain: \"{$value}\" lets accounts from other tenants sign in."
                : null,
            'subdomain' => preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/i', $value) === 1
                ? null
                : 'Enter only the subdomain, such as "acme" for acme.zendesk.com.',
            default => null,
        };
    }

    /**
     * Normalize an accepted answer before it is stored.
     */
    public function normalize(string $value): string
    {
        $value = trim($value);

        return match ($this->format) {
            'url' => rtrim($value, '/'),
            'host', 'subdomain' => strtolower($value),
            'proxies' => implode(',', array_map(trim(...), explode(',', $value))),
            default => $value,
        };
    }

    public static function isHostname(string $value): bool
    {
        return preg_match('/\A(?=.{1,253}\z)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/i', $value) === 1;
    }

    public static function isPort(string $value): bool
    {
        return ctype_digit($value) && (int) $value >= 1 && (int) $value <= 65535;
    }

    private static function isProxyList(string $value): bool
    {
        if (trim($value) === '*') {
            return true;
        }

        foreach (explode(',', $value) as $proxy) {
            [$address, $prefix] = array_pad(explode('/', trim($proxy), 2), 2, null);
            $maxPrefix = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

            if (filter_var($address, FILTER_VALIDATE_IP) === false
                || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix > $maxPrefix))) {
                return false;
            }
        }

        return true;
    }
}
