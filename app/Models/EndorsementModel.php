<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EndorsementModel extends Model
{
    protected $table            = 'endorsements';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'endorsed_by',
        'endorsement_reference_number',
        'endorsement_date',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id'         => 'required|is_natural_no_zero',
        'endorsement_reference_number' => 'required|max_length[100]',
        'endorsement_date'             => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
