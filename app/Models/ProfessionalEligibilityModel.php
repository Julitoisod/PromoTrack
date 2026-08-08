<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ProfessionalEligibilityModel extends Model
{
    protected $table            = 'professional_eligibilities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'eligibility_title',
        'rating',
        'date_obtained',
        'issuing_authority',
        'remarks',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'reservist_id'      => 'required|is_natural_no_zero',
        'eligibility_title' => 'required|max_length[150]',
        'date_obtained'     => 'permit_empty|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forReservist(int $reservistId): array
    {
        return $this->where('reservist_id', $reservistId)
            ->orderBy('date_obtained', 'DESC')
            ->findAll();
    }
}
