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

    protected $allowedFields = [
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
        'organizational_unit_id',
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
        'organizational_unit_id' => 'permit_empty|is_natural_no_zero',
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
        $builder = $this->select('reservists.*, ranks.rank_name AS rank_name, organizational_units.unit_name AS unit_name')
            ->join('ranks', 'ranks.id = reservists.current_rank_id', 'left')
            ->join('organizational_units', 'organizational_units.id = reservists.organizational_unit_id', 'left')
            ->where('reservists.deleted_at', null);

        if ($organizationalUnitId !== null) {
            $builder->where('reservists.organizational_unit_id', $organizationalUnitId);
        }

        return $builder->orderBy('reservists.last_name', 'ASC')->findAll();
    }
}
