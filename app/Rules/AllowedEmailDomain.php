<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Ensures an email address belongs to one of the domains allowed to use MataSecret.
 */
class AllowedEmailDomain implements ValidationRule
{
    /**
     * @return list<string>
     */
    public static function domains(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $domain): string => Str::lower(trim((string) $domain)),
            (array) config('secrets.allowed_domains'),
        )));
    }

    public static function isAllowed(string $email): bool
    {
        return str_contains($email, '@')
            && in_array(Str::lower(Str::afterLast($email, '@')), self::domains(), true);
    }

    /**
     * The allowed domains as written in messages, such as "@example.com or @example.org".
     */
    public static function describe(): string
    {
        return Arr::join(array_map(fn (string $domain): string => '@'.$domain, self::domains()), ', ', ' or ');
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isAllowed($value)) {
            $fail('Only '.self::describe().' addresses can receive secret links.');
        }
    }
}
