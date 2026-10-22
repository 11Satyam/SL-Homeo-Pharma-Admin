<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Today" for field staff is the business-timezone day (config fieldapp.timezone),
 * while timestamps are stored in the app timezone. This converts between them.
 */
final class BusinessDay
{
    public static function tz(): string
    {
        return config('fieldapp.timezone') ?: config('app.timezone');
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::tz())->toDateString();
    }

    /** [start, end] of a business date, expressed in the app timezone for created_at queries. */
    public static function range(?string $date = null): array
    {
        $start = CarbonImmutable::parse($date ?? self::today(), self::tz())->startOfDay();

        return [$start->setTimezone(config('app.timezone')), $start->endOfDay()->setTimezone(config('app.timezone'))];
    }

    /** ISO-8601 in the business timezone, for API output. */
    public static function iso($dt): ?string
    {
        return $dt ? CarbonImmutable::parse($dt)->setTimezone(self::tz())->toIso8601String() : null;
    }
}
