<?php

namespace App\Enums;

enum WebServer: string
{
    case FrankenPhp = 'frankenphp';
    case Traditional = 'traditional';

    public function label(): string
    {
        return match ($this) {
            self::FrankenPhp => 'Laravel Octane with FrankenPHP',
            self::Traditional => 'Apache or Nginx with PHP-FPM',
        };
    }

    /**
     * The Composer package this server needs, or null when it needs none.
     */
    public function package(): ?string
    {
        return match ($this) {
            self::FrankenPhp => 'laravel/octane',
            self::Traditional => null,
        };
    }

    /**
     * Every package any server can add, used to remove the ones no longer needed.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return array_values(array_filter(array_map(
            fn (self $server): ?string => $server->package(),
            self::cases(),
        )));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(
            fn (self $server): array => ['value' => $server->value, 'label' => $server->label()],
            self::cases(),
        ), 'label', 'value');
    }
}
