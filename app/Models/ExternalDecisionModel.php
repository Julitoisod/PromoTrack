<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ExternalDecisionModel extends Model
{
    protected $table            = 'external_decisions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'recorded_by',
        'decision',
        'decision_reference_number',
        'decision_date',
        'deciding_authority_name',
        'remarks',
        'supporting_document_path',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id'    => 'required|is_natural_no_zero',
        'decision'                => 'required|in_list[approved,disapproved,deferred]',
        'decision_date'           => 'required|valid_date',
        'deciding_authority_name' => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
