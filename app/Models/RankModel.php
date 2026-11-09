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

    /**
     * The "Rank Category" dropdown on the sign-up form, mapped onto
     * `ranks.rank_category` values.
     *
     * @var array<string, array{label: string, categories: list<string>}>
     */
    public const SIGNUP_CATEGORIES = [
        'enlisted' => ['label' => 'Enlisted', 'categories' => ['enlisted', 'non-commissioned']],
        'rotc'     => ['label' => 'ROTC Student', 'categories' => ['rotc']],
        'officer'  => ['label' => 'Officer', 'categories' => ['commissioned']],
    ];

    /**
     * Ranks whose promotion is processed at the RCDG level and cannot be
     * applied for through PromoTrack (client revision of 2026-11-09).
     *
     * @var list<string>
     */
    public const RCDG_LEVEL_RANK_CODES = ['MSG'];

    public function activeRanksOrdered(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Active ranks grouped by sign-up category key. A rank whose category is
     * not one of the three is left out of the sign-up form.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function ranksBySignupCategory(): array
    {
        $grouped = array_fill_keys(array_keys(self::SIGNUP_CATEGORIES), []);

        foreach ($this->activeRanksOrdered() as $rank) {
            foreach (self::SIGNUP_CATEGORIES as $key => $category) {
                if (in_array($rank['rank_category'], $category['categories'], true)) {
                    $grouped[$key][] = $rank;
                }
            }
        }

        return $grouped;
    }

    /**
     * The sign-up category key a rank falls under, or null.
     */
    public static function signupCategoryOf(?array $rank): ?string
    {
        foreach (self::SIGNUP_CATEGORIES as $key => $category) {
            if ($rank !== null && in_array($rank['rank_category'], $category['categories'], true)) {
                return $key;
            }
        }

        return null;
    }

    public static function isRcdgLevel(?array $rank): bool
    {
        return $rank !== null && in_array($rank['rank_code'], self::RCDG_LEVEL_RANK_CODES, true);
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
