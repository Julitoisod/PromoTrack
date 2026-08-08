<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\I18n\Time;
use InvalidArgumentException;

/**
 * Pure date/duration arithmetic used throughout PromoTrack AFP-RF for
 * time-in-grade, age, and other elapsed-time computations.
 *
 * This library performs no database access and encodes no AFP-specific
 * eligibility threshold. It is intentionally generic so that
 * `App\Services\EligibilityService` (and any future service) can rely on
 * a single, tested source of truth for date-difference math.
 *
 * Internally this relies on `CodeIgniter\I18n\Time::diff()`, which -
 * because `Time` extends `DateTimeImmutable` and does not override
 * `diff()` - resolves to PHP's native `DateTimeImmutable::diff()` and
 * returns a plain `DateInterval` (`y`, `m`, `d`, `days` properties).
 * This avoids a hard dependency on the `intl` extension.
 */
class DateDurationCalculator
{
    /**
     * Breaks down the elapsed time between $fromDate and $toDate (or now,
     * when $toDate is null) into whole calendar years, remainder months,
     * and remainder days, plus the total number of whole months and
     * whole days for convenient threshold comparisons.
     *
     * @param string      $fromDate Any CodeIgniter\I18n\Time-parseable date string.
     * @param string|null $toDate   Any CodeIgniter\I18n\Time-parseable date string, defaults to now.
     *
     * @return array{years: int, months: int, days: int, total_months: int, total_days: int}
     */
    public function breakdown(string $fromDate, ?string $toDate = null): array
    {
        $from = $this->parse($fromDate);
        $to   = $toDate === null ? Time::now() : $this->parse($toDate);

        if ($from->isAfter($to)) {
            throw new InvalidArgumentException('fromDate must not be after toDate.');
        }

        $interval = $from->diff($to);

        return [
            'years'        => $interval->y,
            'months'       => $interval->m,
            'days'         => $interval->d,
            'total_months' => ($interval->y * 12) + $interval->m,
            'total_days'   => $interval->days,
        ];
    }

    /**
     * Returns the whole number of months elapsed between $fromDate and
     * $toDate (or now, when $toDate is null).
     */
    public function totalMonthsBetween(string $fromDate, ?string $toDate = null): int
    {
        return $this->breakdown($fromDate, $toDate)['total_months'];
    }

    /**
     * Returns the whole number of days elapsed between $fromDate and
     * $toDate (or now, when $toDate is null).
     */
    public function totalDaysBetween(string $fromDate, ?string $toDate = null): int
    {
        return $this->breakdown($fromDate, $toDate)['total_days'];
    }

    /**
     * Returns the whole number of years elapsed (i.e. age in years) as of
     * $toDate (or now, when $toDate is null).
     */
    public function ageInYears(string $fromDate, ?string $toDate = null): int
    {
        return $this->breakdown($fromDate, $toDate)['years'];
    }

    /**
     * Convenience threshold check: has at least $months whole months
     * elapsed between $fromDate and $toDate (or now)?
     */
    public function hasElapsedAtLeastMonths(string $fromDate, int $months, ?string $toDate = null): bool
    {
        return $this->totalMonthsBetween($fromDate, $toDate) >= $months;
    }

    /**
     * True when $date falls strictly before $referenceDate (or now, when
     * $referenceDate is null).
     */
    public function isBefore(string $date, ?string $referenceDate = null): bool
    {
        $reference = $referenceDate === null ? Time::now() : $this->parse($referenceDate);

        return $this->parse($date)->isBefore($reference);
    }

    /**
     * True when $date falls strictly after $referenceDate (or now, when
     * $referenceDate is null).
     */
    public function isAfter(string $date, ?string $referenceDate = null): bool
    {
        $reference = $referenceDate === null ? Time::now() : $this->parse($referenceDate);

        return $this->parse($date)->isAfter($reference);
    }

    private function parse(string $date): Time
    {
        return Time::parse($date);
    }
}
