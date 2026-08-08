<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ExternalForwardingRecordModel extends Model
{
    protected $table            = 'external_forwarding_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'forwarded_by',
        'external_authority_name',
        'forwarding_reference_number',
        'forwarded_date',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id'        => 'required|is_natural_no_zero',
        'external_authority_name'     => 'required|max_length[150]',
        'forwarding_reference_number' => 'required|max_length[100]',
        'forwarded_date'              => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
