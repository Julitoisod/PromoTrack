<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PromotionRuleModel extends Model
{
    protected $table            = 'promotion_rules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'from_rank_id',
        'to_rank_id',
        'rule_name',
        'is_active',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'from_rank_id' => 'required|is_natural_no_zero',
        'to_rank_id'   => 'required|is_natural_no_zero|differs[from_rank_id]|rank_progression[from_rank_id]',
        'rule_name'    => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'to_rank_id' => [
            'differs'          => 'The target rank must be different from the current rank.',
            'rank_progression' => 'The target rank must be a higher rank than the current rank.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Finds the single active promotion rule that governs a promotion
     * from $fromRankId to $toRankId, or null when no such rule exists.
     */
    public function findForRankTransition(int $fromRankId, int $toRankId): ?array
    {
        return $this->where('from_rank_id', $fromRankId)
            ->where('to_rank_id', $toRankId)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Returns the single active promotion rule whose from_rank_id is
     * $fromRankId (the "next rank" for a reservist currently holding
     * that rank), or null when no such rule is configured. Used to
     * auto-derive a target rank when a caller doesn't supply one
     * explicitly (e.g. `PromotionAssessmentsController::store()`).
     */
    public function findActiveRuleForFromRank(int $fromRankId): ?array
    {
        return $this->where('from_rank_id', $fromRankId)
            ->where('is_active', 1)
            ->first();
    }
}
