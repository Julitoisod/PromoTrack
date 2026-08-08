<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;

/**
 * Domain entity for a single reservist profile record.
 *
 * Provides computed convenience properties (full name, age, current
 * time-in-grade) on top of the raw `reservists` table columns. Precise,
 * authoritative time-in-grade calculations used for promotion
 * eligibility decisions are performed by
 * `App\Libraries\DateDurationCalculator` and
 * `App\Services\EligibilityService`, not by this entity.
 */
class Reservist extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'date_of_birth',
        'date_of_enlistment',
        'date_of_last_promotion',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'                      => 'integer',
        'current_rank_id'         => '?integer',
        'education_track_id'      => '?integer',
        'organizational_unit_id'  => '?integer',
        'created_by'              => '?integer',
    ];

    /**
     * Builds the reservist's display name from the individual name
     * columns, skipping any that are empty.
     */
    public function getFullName(): string
    {
        $parts = array_filter([
            $this->attributes['last_name'] ?? null,
            $this->attributes['first_name'] ?? null,
            $this->attributes['middle_name'] ?? null,
            $this->attributes['suffix'] ?? null,
        ], static fn ($part): bool => $part !== null && $part !== '');

        if ($parts === []) {
            return '';
        }

        [$last, $first] = [
            $this->attributes['last_name'] ?? '',
            $this->attributes['first_name'] ?? '',
        ];

        $name = trim($last . ', ' . $first);

        if (! empty($this->attributes['middle_name'])) {
            $name .= ' ' . mb_substr($this->attributes['middle_name'], 0, 1) . '.';
        }

        if (! empty($this->attributes['suffix'])) {
            $name .= ' ' . $this->attributes['suffix'];
        }

        return trim($name);
    }

    /**
     * Calculates the reservist's current age in whole years from
     * `date_of_birth`, or null when the date of birth is not set.
     */
    public function getAge(): ?int
    {
        if ($this->attributes['date_of_birth'] === null) {
            return null;
        }

        $dob = $this->mutateDate($this->attributes['date_of_birth']);

        return (int) $dob->diff(Time::now())->y;
    }

    /**
     * Calculates whole months elapsed since `date_of_last_promotion`.
     * Falls back to `date_of_enlistment` when no promotion date is on
     * file yet (i.e. the reservist has never been promoted within
     * PromoTrack). Returns null when neither date is available.
     */
    public function getTimeInGradeMonths(): ?int
    {
        $anchor = $this->attributes['date_of_last_promotion'] ?? $this->attributes['date_of_enlistment'] ?? null;

        if ($anchor === null) {
            return null;
        }

        $anchorDate = $this->mutateDate($anchor);
        $diff       = $anchorDate->diff(Time::now());

        return ($diff->y * 12) + $diff->m;
    }

    public function isActive(): bool
    {
        return ($this->attributes['status'] ?? null) === 'active';
    }
}
