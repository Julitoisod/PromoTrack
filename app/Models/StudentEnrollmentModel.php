<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * A student reservist's Certificate of Registration (COR) for one semester
 * and its verification. One row per reservist per year level.
 */
class StudentEnrollmentModel extends Model
{
    public const COR_DIR = 'cor';

    /**
     * status => [badge label, Bootstrap tone]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const STATUSES = [
        'pending'  => ['Pending', 'warning'],
        'verified' => ['Verified', 'success'],
        'rejected' => ['Rejected', 'danger'],
    ];

    protected $table          = 'student_enrollments';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'reservist_id',
        'year_level',
        'cor_path',
        'cor_filename',
        'status',
        'uploaded_at',
        'verified_by',
        'verified_at',
    ];

    protected $validationRules = [
        'reservist_id' => 'required|is_natural_no_zero',
        'year_level'   => 'required|max_length[20]',
        'status'       => 'permit_empty|in_list[pending,verified,rejected]',
    ];

    public function findFor(int $reservistId, ?string $yearLevel): ?array
    {
        if ($yearLevel === null || $yearLevel === '') {
            return null;
        }

        return $this->where('reservist_id', $reservistId)->where('year_level', $yearLevel)->first();
    }

    /**
     * Rows for many reservists at once, keyed by reservist id, for the
     * year level each one is at now.
     *
     * @param list<array{id: int|string, student_year_level: ?string}> $reservists
     *
     * @return array<int, array<string, mixed>>
     */
    public function currentFor(array $reservists): array
    {
        $ids = array_map(static fn ($r) => (int) $r['id'], $reservists);

        if ($ids === []) {
            return [];
        }

        $levels = array_column($reservists, 'student_year_level', 'id');
        $rows   = [];

        foreach ($this->whereIn('reservist_id', $ids)->findAll() as $row) {
            if (($levels[$row['reservist_id']] ?? null) === $row['year_level']) {
                $rows[(int) $row['reservist_id']] = $row;
            }
        }

        return $rows;
    }
}
