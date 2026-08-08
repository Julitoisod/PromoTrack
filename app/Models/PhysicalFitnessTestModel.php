<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PhysicalFitnessTestModel extends Model
{
    protected $table            = 'physical_fitness_tests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'test_date',
        'result',
        'score',
        'remarks',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'reservist_id' => 'required|is_natural_no_zero',
        'test_date'    => 'required|valid_date',
        'result'       => 'required|in_list[pass,fail]',
        'score'        => 'permit_empty|decimal',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Returns the most recent physical fitness test on file for the
     * reservist, or null when none exists.
     */
    public function mostRecentForReservist(int $reservistId): ?array
    {
        return $this->where('reservist_id', $reservistId)
            ->orderBy('test_date', 'DESC')
            ->first();
    }
}
