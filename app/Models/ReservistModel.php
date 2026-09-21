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
     * Year levels offered when a reservist is still studying.
     *
     * @var list<string>
     */
    public const STUDENT_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];

    /**
     * The reservist's most recent assessment verdict, as a correlated
     * scalar subquery so the listing stays one paginated statement.
     * Resolves to NULL -- displayed as "Pending" -- while no assessment
     * has been run for them yet.
     */
    private const LATEST_PROMOTION_STATUS_SQL =
        '(SELECT pa.promotion_status FROM promotion_assessments pa'
        . ' WHERE pa.reservist_id = reservists.id ORDER BY pa.id DESC LIMIT 1)';

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
        'is_student',
        'student_year_level',
        'organizational_unit_id',
        'company_unit_id',
        'contact_number',
        'email',
        'address',
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
        'student_year_level' => 'permit_empty|in_list[1st Year,2nd Year,3rd Year,4th Year,5th Year]',
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
