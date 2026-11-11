<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Reservist;
use CodeIgniter\Model;

class ReservistModel extends Model
{
    protected $table            = 'reservists';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Reservist::class;
    protected $useSoftDeletes   = true;

    /**
     * The promotion-status values a reservist listing can be filtered by.
     * `pending` is not a stored value -- it is the absence of any
     * assessment, which is what a freshly encoded reservist looks like
     * until one has been run. The other three are
     * `promotion_assessments.promotion_status` as EligibilityService
     * writes it.
     *
     * @var list<string>
     */
    public const PROMOTION_STATUSES = ['pending', 'not_yet_eligible', 'eligible', 'overdue'];

    /**
     * Year levels offered when a reservist is still studying. Per the
     * client revision of 2026-11-09 these are semesters; the 1st Year ones
     * were added on 2026-11-11 so the MS1 and MS2 enrollment folders fill.
     * Each maps to an E-level in Config\RotcProgression.
     *
     * @var list<string>
     */
    public const STUDENT_YEAR_LEVELS = [
        '1st Year 1st Sem',
        '1st Year 2nd Sem',
        '2nd Year 1st Sem',
        '2nd Year 2nd Sem',
        '3rd Year 1st Sem',
        '3rd Year 2nd Sem',
        '4th Year 1st Sem',
        '4th Year 2nd Sem',
    ];

    /**
     * Whole-year values written before the semester list replaced them.
     * Still valid on existing rows, so editing an older record does not
     * fail validation; no form offers them any more.
     *
     * @var list<string>
     */
    public const LEGACY_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];

    /**
     * Educational attainment choices on the sign-up form.
     *
     * @var array<string, string>
     */
    public const EDUCATIONAL_ATTAINMENTS = [
        'none'          => 'None',
        'undergraduate' => 'Undergraduate',
        'bachelor'      => "Bachelor's Degree",
        'masteral'      => 'Masteral Degree',
        'doctorate'     => 'Doctorate',
        'others'        => 'Others',
    ];

    /**
     * Attainments whose degree (or meaning of "others") must be written in.
     *
     * @var list<string>
     */
    public const ATTAINMENTS_NEEDING_DETAILS = ['bachelor', 'others'];

    /**
     * The reservist's most recent assessment verdict, as a correlated
     * scalar subquery so the listing stays one paginated statement.
     * Resolves to NULL -- displayed as "Pending" -- while no assessment
     * has been run for them yet.
     */
    public const LATEST_PROMOTION_STATUS_SQL =
        '(SELECT pa.promotion_status FROM promotion_assessments pa'
        . ' WHERE pa.reservist_id = reservists.id ORDER BY pa.id DESC LIMIT 1)';

    /**
     * Standby / Ready Reserve as the admin panel counts it: what an
     * administrator assigned, otherwise the latest classification the
     * student reported on their ROTC ladder (stage codes E1-E8 sort as text).
     */
    public const CLASSIFICATION_SQL =
        'COALESCE(reservists.reserve_classification,'
        . ' (SELECT rp.classification FROM rotc_progress rp WHERE rp.reservist_id = reservists.id'
        . ' AND rp.classification IS NOT NULL ORDER BY rp.stage DESC LIMIT 1))';

    /**
     * A student reservist in SQL, matching Reservist::isStudentReservist().
     */
    // COALESCE so a record with no attainment (older data) is "not a student"
    // rather than NULL, which NOT (...) would drop from both lists.
    public const STUDENT_SQL = "(reservists.is_student = 1 AND COALESCE(reservists.educational_attainment, '') = 'undergraduate')";

    protected $allowedFields = [
        'user_id',
        'reservist_number',
        'afp_serial_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'sex',
        'date_of_birth',
        'date_of_enlistment',
        'current_rank_id',
        'date_of_last_promotion',
        'education_track_id',
        'educational_attainment',
        'degree_details',
        'is_student',
        'student_year_level',
        'reserve_classification',
        'classification_updated_by',
        'classification_updated_at',
        'organizational_unit_id',
        'company_unit_id',
        'contact_number',
        'email',
        'address',
        'profile_photo',
        'status',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        // Not an allowed field; present only so `is_unique[...,id,{id}]`
        // below can resolve the `{id}` placeholder to the current row's
        // primary key on updates (excluding it from the uniqueness
        // check). Controllers must pass `id` in the update data array.
        'id'                => 'permit_empty|is_natural_no_zero',
        'reservist_number'  => 'required|valid_reservist_number|max_length[30]|is_unique[reservists.reservist_number,id,{id}]',
        'afp_serial_number' => 'permit_empty|valid_afp_serial_number|max_length[30]|is_unique[reservists.afp_serial_number,id,{id}]',
        'first_name'        => 'required|max_length[100]',
        'last_name'         => 'required|max_length[100]',
        'sex'               => 'permit_empty|in_list[male,female]',
        'date_of_birth'     => 'permit_empty|valid_date|not_future_date',
        'date_of_enlistment' => 'permit_empty|valid_date|not_future_date|date_after_field[date_of_birth]',
        'date_of_last_promotion' => 'permit_empty|valid_date|not_future_date|date_on_or_after[date_of_enlistment]',
        'current_rank_id'   => 'permit_empty|is_natural_no_zero',
        'education_track_id' => 'permit_empty|is_natural_no_zero',
        'is_student'        => 'permit_empty|in_list[0,1]',
        // Completed by initialize() from STUDENT_YEAR_LEVELS + LEGACY_YEAR_LEVELS.
        'student_year_level' => 'permit_empty',
        'educational_attainment' => 'permit_empty|in_list[none,undergraduate,bachelor,masteral,doctorate,others]',
        'degree_details'    => 'permit_empty|max_length[150]',
        'reserve_classification' => 'permit_empty|in_list[standby,ready_reserve]',
        'organizational_unit_id' => 'permit_empty|is_natural_no_zero',
        'company_unit_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[organizational_units.id]',
        'user_id'           => 'permit_empty|is_natural_no_zero|is_unique[reservists.user_id,id,{id}]',
        'email'             => 'permit_empty|valid_email|max_length[150]',
        'status'            => 'permit_empty|in_list[active,inactive,discharged,deceased]',
    ];

    protected $validationMessages = [
        'reservist_number' => [
            'required'  => 'The reservist number is required.',
            'is_unique' => 'This reservist number is already in use.',
        ],
        'afp_serial_number' => [
            'is_unique' => 'This AFP serial number is already in use.',
        ],
        'date_of_enlistment' => [
            'date_after_field' => 'The date of enlistment must be after the date of birth.',
        ],
        'date_of_last_promotion' => [
            'date_on_or_after' => 'The date of last promotion must be on or after the date of enlistment.',
        ],
    ];

    protected $skipValidation = false;

    protected function initialize(): void
    {
        $this->validationRules['student_year_level'] = 'permit_empty|in_list['
            . implode(',', array_merge(self::STUDENT_YEAR_LEVELS, self::LEGACY_YEAR_LEVELS)) . ']';
    }

    /**
     * Returns reservist rows for the listing table, joined with their
     * current rank name and organizational unit name for display,
     * scoped to $organizationalUnitId when it is not null.
     */
    public function listForDisplay(?int $organizationalUnitId): array
    {
        return $this->listQuery($organizationalUnitId)->findAll();
    }

    /**
     * Builds the reservist listing query, scoped to a unit and optionally
     * narrowed by a search term over name and reservist/serial number.
     *
     * Returned unexecuted so the caller can `paginate()` it: the listing used
     * to `findAll()` every row and let DataTables page it in the browser,
     * which ships the entire roster on every page view.
     */
    public function listQuery(?int $organizationalUnitId, string $search = '', ?string $promotionStatus = null): self
    {
        $builder = $this->select(
            'reservists.*, ranks.rank_name AS rank_name, organizational_units.unit_name AS unit_name,'
            . ' company_units.unit_name AS company_name'
        )
            ->select(self::LATEST_PROMOTION_STATUS_SQL . ' AS promotion_status', false)
            ->join('ranks', 'ranks.id = reservists.current_rank_id', 'left')
            ->join('organizational_units', 'organizational_units.id = reservists.organizational_unit_id', 'left')
            ->join('organizational_units AS company_units', 'company_units.id = reservists.company_unit_id', 'left')
            ->where('reservists.deleted_at', null);

        if ($organizationalUnitId !== null) {
            $builder->where('reservists.organizational_unit_id', $organizationalUnitId);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('reservists.last_name', $search)
                ->orLike('reservists.first_name', $search)
                ->orLike('reservists.reservist_number', $search)
                ->orLike('reservists.afp_serial_number', $search)
                ->groupEnd();
        }

        // Raw because the filter is the same correlated subquery as the
        // SELECT above: MySQL cannot reference a select alias in WHERE, and
        // repeating it here keeps the listing one statement that paginate()
        // can still count. The value is never interpolated -- only the four
        // known status names reach this branch.
        if ($promotionStatus !== null && in_array($promotionStatus, self::PROMOTION_STATUSES, true)) {
            $builder->where(
                $promotionStatus === 'pending'
                    ? self::LATEST_PROMOTION_STATUS_SQL . ' IS NULL'
                    : self::LATEST_PROMOTION_STATUS_SQL . " = '" . $promotionStatus . "'",
                null,
                false
            );
        }

        return $builder->orderBy('reservists.last_name', 'ASC');
    }

    /**
     * The admin panel's reservist lists (registrations, semester folders,
     * classification folders, regular reservists), returned unexecuted so
     * the caller can paginate() it.
     *
     * Filters (all optional): q (name / serial / reservist number), cdc,
     * company, student (true = student reservists, false = everyone else),
     * year_level, classification (standby / ready_reserve), registered
     * (true = only records an account signed up for).
     *
     * @param array<string, mixed> $filters
     */
    public function directoryQuery(array $filters = []): self
    {
        $builder = $this->select(
            'reservists.*, ranks.rank_name AS rank_name, cdc.unit_name AS cdc_name, company.unit_name AS company_name'
        )
            ->select(self::CLASSIFICATION_SQL . ' AS classification', false)
            ->join('ranks', 'ranks.id = reservists.current_rank_id', 'left')
            ->join('organizational_units AS cdc', 'cdc.id = reservists.organizational_unit_id', 'left')
            ->join('organizational_units AS company', 'company.id = reservists.company_unit_id', 'left')
            ->where('reservists.deleted_at', null);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $builder->groupStart()
                ->like('reservists.last_name', $q)
                ->orLike('reservists.first_name', $q)
                ->orLike('reservists.afp_serial_number', $q)
                ->orLike('reservists.reservist_number', $q)
                ->groupEnd();
        }

        if (! empty($filters['cdc'])) {
            $builder->where('reservists.organizational_unit_id', (int) $filters['cdc']);
        }

        if (! empty($filters['company'])) {
            $builder->where('reservists.company_unit_id', (int) $filters['company']);
        }

        if (array_key_exists('student', $filters) && $filters['student'] !== null) {
            $builder->where($filters['student'] ? self::STUDENT_SQL : 'NOT ' . self::STUDENT_SQL, null, false);
        }

        if (! empty($filters['year_level'])) {
            $builder->where('reservists.student_year_level', (string) $filters['year_level']);
        }

        // Only the two known values reach the raw SQL.
        if (in_array($filters['classification'] ?? null, ['standby', 'ready_reserve'], true)) {
            $builder->where(self::CLASSIFICATION_SQL . " = '" . $filters['classification'] . "'", null, false);
        }

        if (! empty($filters['registered'])) {
            $builder->where('reservists.user_id IS NOT NULL', null, false);
        }

        return $builder;
    }

    /**
     * Headline counts for the admin dashboard.
     *
     * @return array{registrants: int, students: int, regular: int, standby: int, ready_reserve: int}
     */
    public function adminCounts(): array
    {
        $count = fn (array $filters): int => $this->directoryQuery($filters)->countAllResults();

        return [
            'registrants'   => $count(['registered' => true]),
            'students'      => $count(['student' => true]),
            'regular'       => $count(['student' => false]),
            'standby'       => $count(['classification' => 'standby']),
            'ready_reserve' => $count(['classification' => 'ready_reserve']),
        ];
    }

    /**
     * Number of student reservists at each year level.
     *
     * @return array<string, int>
     */
    public function studentCountsByYearLevel(): array
    {
        $rows = $this->builder()
            ->select('student_year_level, COUNT(*) AS total')
            ->where('deleted_at', null)
            ->where(self::STUDENT_SQL, null, false)
            ->groupBy('student_year_level')
            ->get()
            ->getResultArray();

        return array_column($rows, 'total', 'student_year_level');
    }

    /**
     * Returns the reservist record owned by $userId, or null when the
     * account has none (every non-reservist account).
     */
    public function findByUserId(int $userId): ?Reservist
    {
        return $this->where('user_id', $userId)->first();
    }

    /**
     * Returns the reservist carrying $afpSerialNumber, used by
     * registration to claim a record an S1 clerk already encoded
     * instead of creating a duplicate.
     */
    public function findByAfpSerialNumber(string $afpSerialNumber): ?Reservist
    {
        return $this->where('afp_serial_number', $afpSerialNumber)->first();
    }
}
