<?php

namespace App\Enums;

/**
 * The secret stores an installation can choose. Mirrors the "secrets.store" config values.
 */
enum SecretStoreDriver: string
{
    case Apcu = 'apcu';
    case SharedMemory = 'shmop';
    case Redis = 'redis';

    public function label(): string
    {
        return match ($this) {
            self::Apcu => 'APCu (Memory do not survive to PHP-FPM restarts)',
            self::SharedMemory => 'Shared memory (Memory survive to PHP-FPM restarts)',
            self::Redis => 'Redis (Memory can be persistent and it can be shared across hosts)',
        };
    }

    /**
     * The PHP extension the store needs at runtime.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Apcu => 'apcu',
            self::SharedMemory => 'shmop',
            self::Redis => 'redis',
        };
    }

    /**
     * The cache store that backs the rate limiters for this secret store.
     */
    public function cacheStore(): string
    {
        return $this === self::Redis ? 'redis' : 'apc';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(array_map(
            fn (self $store): array => ['value' => $store->value, 'label' => $store->label()],
            self::cases(),
        ), 'label', 'value');
    }
}
