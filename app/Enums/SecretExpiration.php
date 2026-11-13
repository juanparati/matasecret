<?php

namespace App\Enums;

enum SecretExpiration: string
{
    case OneMinute = '1m';
    case FiveMinutes = '5m';
    case TenMinutes = '10m';
    case FifteenMinutes = '15m';
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case TwoHours = '2h';
    case SixHours = '6h';
    case TwelveHours = '12h';
    case OneDay = '1d';
    case TwoDays = '2d';
    case OneWeek = '1w';
    case OneMonth = '1mo';
    case Custom = 'custom';

    /**
     * The expiration preselected in the create form.
     */
    public static function default(): self
    {
        return self::TwoHours;
    }

    /**
     * Number of seconds the secret lives, or null for a custom date.
     */
    public function seconds(): ?int
    {
        return match ($this) {
            self::OneMinute => 60,
            self::FiveMinutes => 5 * 60,
            self::TenMinutes => 10 * 60,
            self::FifteenMinutes => 15 * 60,
            self::ThirtyMinutes => 30 * 60,
            self::OneHour => 3600,
            self::TwoHours => 2 * 3600,
            self::SixHours => 6 * 3600,
            self::TwelveHours => 12 * 3600,
            self::OneDay => 86400,
            self::TwoDays => 2 * 86400,
            self::OneWeek => 7 * 86400,
            self::OneMonth => 30 * 86400,
            self::Custom => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OneMinute => '1 minute',
            self::FiveMinutes => '5 minutes',
            self::TenMinutes => '10 minutes',
            self::FifteenMinutes => '15 minutes',
            self::ThirtyMinutes => '30 minutes',
            self::OneHour => '1 hour',
            self::TwoHours => '2 hours',
            self::SixHours => '6 hours',
            self::TwelveHours => '12 hours',
            self::OneDay => '1 day',
            self::TwoDays => '2 days',
            self::OneWeek => '1 week',
            self::OneMonth => '1 month',
            self::Custom => 'Custom…',
        };
    }

    /**
     * The options rendered in the expiration dropdown.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $expiration): array => ['value' => $expiration->value, 'label' => $expiration->label()],
            self::cases(),
        );
    }
}
