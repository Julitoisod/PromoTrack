<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ReservistTrainingModel extends Model
{
    protected $table            = 'reservist_trainings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'training_type_id',
        'training_title',
        'date_completed',
        'issuing_authority',
        'certificate_number',
        'remarks',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'reservist_id'     => 'required|is_natural_no_zero',
        'training_type_id' => 'required|is_natural_no_zero',
        'date_completed'   => 'permit_empty|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forReservist(int $reservistId): array
    {
        return $this->where('reservist_id', $reservistId)
            ->orderBy('date_completed', 'DESC')
            ->findAll();
    }
}
