<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class WaiverRequestModel extends Model
{
    protected $table            = 'waiver_requests';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'package_document_id',
        'requested_by',
        'justification',
        'approved_by',
        'decision',
        'decided_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'package_document_id' => 'required|is_natural_no_zero',
        'justification'       => 'required',
        'decision'            => 'permit_empty|in_list[pending,approved,denied]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
