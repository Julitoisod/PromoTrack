<?php

declare(strict_types=1);

namespace App\Validation;

use CodeIgniter\I18n\Time;

/**
 * Reusable date-comparison validation rules shared across reservist,
 * training, PFT, professional-eligibility, and promotion-rule forms.
 *
 * These rules operate on plain `Y-m-d` (or any CodeIgniter\I18n\Time
 * parseable) date strings submitted from HTML `<input type="date">`
 * fields. They do not encode any AFP-specific business rule -- only
 * generic chronological ordering / bounds checks.
 */
class DateRules
{
    /**
     * Fails when the given date is strictly after today.
     */
    public function not_future_date(?string $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true;
        }

        try {
            $date = Time::parse($str);
        } catch (\Throwable $exception) {
            return true; // let valid_date report the format problem
        }

        if ($date->isAfter(Time::now())) {
            $error = 'The {field} field cannot be a future date.';

            return false;
        }

        return true;
    }

    /**
     * Fails when $str is not the same date as, or after, the date held by
     * the comparison field.
     *
     * Usage: `date_on_or_after[date_of_enlistment]`
     */
    public function date_on_or_after(?string $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        return $this->compareDates($str, $fields, $data, $error, 'on_or_after');
    }

    /**
     * Fails when $str is not the same date as, or before, the date held by
     * the comparison field.
     *
     * Usage: `date_on_or_before[expiry_date]`
     */
    public function date_on_or_before(?string $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        return $this->compareDates($str, $fields, $data, $error, 'on_or_before');
    }

    /**
     * Fails when $str is not strictly after the date held by the
     * comparison field.
     *
     * Usage: `date_after_field[effective_date]`
     */
    public function date_after_field(?string $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        return $this->compareDates($str, $fields, $data, $error, 'after');
    }

    /**
     * Shared comparison logic for the date_* rules above.
     */
    private function compareDates(
        ?string $str,
        ?string $fields,
        array $data,
        ?string &$error,
        string $mode
    ): bool {
        if ($str === null || $str === '' || $fields === null || $fields === '') {
            return true;
        }

        $otherField = $fields;
        $otherValue = $data[$otherField] ?? null;

        if ($otherValue === null || $otherValue === '') {
            return true;
        }

        try {
            $current = Time::parse($str);
            $other   = Time::parse($otherValue);
        } catch (\Throwable $exception) {
            return true; // let valid_date report the format problem
        }

        $isValid = match ($mode) {
            'on_or_after'  => $current->equals($other) || $current->isAfter($other),
            'on_or_before' => $current->equals($other) || $current->isBefore($other),
            'after'        => $current->isAfter($other),
            default        => true,
        };

        if (! $isValid) {
            $error = match ($mode) {
                'on_or_after'  => "The {field} field must be on or after the {$otherField} field.",
                'on_or_before' => "The {field} field must be on or before the {$otherField} field.",
                'after'        => "The {field} field must be after the {$otherField} field.",
                default        => 'The {field} field is invalid.',
            };

            return false;
        }

        return true;
    }
}
