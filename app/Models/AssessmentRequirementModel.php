<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AssessmentRequirementModel extends Model
{
    protected $table            = 'assessment_requirements';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_assessment_id',
        'requirement_code',
        'requirement_label',
        'is_satisfied',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_assessment_id' => 'required|is_natural_no_zero',
        'requirement_code'        => 'required|max_length[60]',
        'requirement_label'       => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forAssessment(int $promotionAssessmentId): array
    {
        return $this->where('promotion_assessment_id', $promotionAssessmentId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
