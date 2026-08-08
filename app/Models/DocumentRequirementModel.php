<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DocumentRequirementModel extends Model
{
    protected $table            = 'document_requirements';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'document_requirement_template_id',
        'document_code',
        'document_label',
        'is_conditional',
        'condition_expression',
        'allows_commander_override',
        'display_order',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'document_requirement_template_id' => 'required|is_natural_no_zero',
        'document_code'                    => 'required|exact_length[1]|alpha',
        'document_label'                   => 'required|max_length[200]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forTemplate(int $templateId): array
    {
        return $this->where('document_requirement_template_id', $templateId)
            ->orderBy('display_order', 'ASC')
            ->orderBy('document_code', 'ASC')
            ->findAll();
    }
}
