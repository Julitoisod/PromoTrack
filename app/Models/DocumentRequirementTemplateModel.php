<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DocumentRequirementTemplateModel extends Model
{
    protected $table            = 'document_requirement_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_rule_id',
        'template_name',
        'version',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'template_name' => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Returns the active template for $promotionRuleId, falling back to
     * the active rule-agnostic template (promotion_rule_id IS NULL)
     * when no rule-specific template is configured.
     */
    public function activeTemplateFor(?int $promotionRuleId): ?array
    {
        if ($promotionRuleId !== null) {
            $specific = $this->where('promotion_rule_id', $promotionRuleId)
                ->where('is_active', 1)
                ->first();

            if ($specific !== null) {
                return $specific;
            }
        }

        return $this->where('promotion_rule_id', null)
            ->where('is_active', 1)
            ->first();
    }
}
