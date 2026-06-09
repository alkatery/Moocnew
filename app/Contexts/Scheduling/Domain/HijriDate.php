<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Domain;

use Illuminate\Support\Carbon;
use IntlDateFormatter;

/**
 * Formats a Gregorian instant as a Hijri (Umm al-Qura) date (PRD §7), used
 * across the calendar, scheduling and reminders.
 */
final class HijriDate
{
    public static function format(Carbon $date, string $timezone = 'Asia/Riyadh'): string
    {
        $formatter = new IntlDateFormatter(
            'ar_SA@calendar=islamic-umalqura',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            $timezone,
            IntlDateFormatter::TRADITIONAL,
            'yyyy-MM-dd',
        );

        return (string) $formatter->format($date->getTimestamp());
    }
}
