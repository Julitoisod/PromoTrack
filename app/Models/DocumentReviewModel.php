<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DocumentReviewModel extends Model
{
    protected $table            = 'document_reviews';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'document_version_id',
        'reviewed_by',
        'decision',
        'remarks',
        'reviewed_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'document_version_id' => 'required|is_natural_no_zero',
        'decision'            => 'required|in_list[verified,rejected]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
