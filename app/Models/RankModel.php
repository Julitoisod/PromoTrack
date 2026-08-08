<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RankModel extends Model
{
    protected $table            = 'ranks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rank_code',
        'rank_name',
        'rank_category',
        'sort_order',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Not an allowed field; present only so `is_unique[...,id,{id}]`
        // below can resolve the `{id}` placeholder on updates.
        'id'        => 'permit_empty|is_natural_no_zero',
        'rank_code' => 'required|max_length[20]|is_unique[ranks.rank_code,id,{id}]',
        'rank_name' => 'required|max_length[100]',
        'sort_order' => 'required|is_natural',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function activeRanksOrdered(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Returns true when $toRankId represents a strictly higher rank
     * (higher sort_order) than $fromRankId. Used by ReservistRules to
     * block reversed or lateral "promotions".
     */
    public function isHigherRank(int $fromRankId, int $toRankId): bool
    {
        $fromRank = $this->find($fromRankId);
        $toRank   = $this->find($toRankId);

        if ($fromRank === null || $toRank === null) {
            return false;
        }

        return (int) $toRank['sort_order'] > (int) $fromRank['sort_order'];
    }
}
