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
