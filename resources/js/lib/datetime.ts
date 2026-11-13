const dateTimeFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const relativeTimeFormat = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

const relativeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
    ['second', 1_000],
];

/**
 * Format an ISO date in the viewer's timezone, e.g. "10 Nov 2026, 14:30".
 */
export function formatDateTime(iso: string): string {
    return dateTimeFormat.format(new Date(iso));
}

/**
 * Format an ISO date relative to now, e.g. "in 2 hours".
 */
export function formatRelative(iso: string, now: number = Date.now()): string {
    const difference = new Date(iso).getTime() - now;

    for (const [unit, milliseconds] of relativeUnits) {
        if (Math.abs(difference) >= milliseconds || unit === 'second') {
            return relativeTimeFormat.format(
                Math.round(difference / milliseconds),
                unit,
            );
        }
    }

    return '';
}

/**
 * Convert a date to the value format of an <input type="datetime-local">.
 */
export function toDateTimeLocalValue(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
