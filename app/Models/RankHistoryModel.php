<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RankHistoryModel extends Model
{
    protected $table            = 'reservist_rank_histories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'rank_id',
        'effective_date',
        'order_reference',
        'remarks',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'reservist_id'   => 'required|is_natural_no_zero',
        'rank_id'        => 'required|is_natural_no_zero',
        'effective_date' => 'required|valid_date',
        'order_reference' => 'permit_empty|max_length[100]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Returns the rank history rows for a reservist, most recent first.
     */
    public function forReservist(int $reservistId): array
    {
        return $this->where('reservist_id', $reservistId)
            ->orderBy('effective_date', 'DESC')
            ->findAll();
    }
}
