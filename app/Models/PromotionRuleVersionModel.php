<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PromotionRuleVersionModel extends Model
{
    protected $table            = 'promotion_rule_versions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_rule_id',
        'education_track_id',
        'version_number',
        'minimum_time_in_grade_months',
        'requires_mles',
        'requires_physical_fitness_test',
        'additional_prerequisites',
        'effective_date',
        'expiry_date',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'promotion_rule_id' => 'required|is_natural_no_zero',
        'version_number'    => 'required|is_natural_no_zero',
        'effective_date'    => 'required|valid_date',
        'expiry_date'       => 'permit_empty|valid_date',
        'minimum_time_in_grade_months' => 'permit_empty|is_natural',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Resolves the version of $promotionRuleId that is effective as of
     * $asOfDate (defaults to today): the latest version whose
     * effective_date is on/before $asOfDate and whose expiry_date is
     * null or after $asOfDate, restricted to active versions only.
     *
     * When $educationTrackId is given, a version scoped to that exact
     * track is preferred; a track-agnostic version (`education_track_id
     * IS NULL`) is used as the fallback for rank transitions whose
     * threshold does not vary by track. When $educationTrackId is null,
     * only track-agnostic versions are considered.
     */
    public function resolveEffectiveVersion(int $promotionRuleId, ?string $asOfDate = null, ?int $educationTrackId = null): ?array
    {
        $asOfDate ??= date('Y-m-d');

        if ($educationTrackId !== null) {
            $trackSpecific = $this->effectiveVersionQuery($promotionRuleId, $asOfDate)
                ->where('education_track_id', $educationTrackId)
                ->first();

            if ($trackSpecific !== null) {
                return $trackSpecific;
            }
        }

        return $this->effectiveVersionQuery($promotionRuleId, $asOfDate)
            ->where('education_track_id', null)
            ->first();
    }

    /**
     * Builds a fresh query for the rows of $promotionRuleId that are
     * effective as of $asOfDate, ordered most-recent-first. A fresh
     * `Model` instance is used per call so the two lookups in
     * `resolveEffectiveVersion()` never share builder state.
     */
    private function effectiveVersionQuery(int $promotionRuleId, string $asOfDate): self
    {
        return (new self())
            ->where('promotion_rule_id', $promotionRuleId)
            ->where('is_active', 1)
            ->where('effective_date <=', $asOfDate)
            ->groupStart()
                ->where('expiry_date', null)
                ->orWhere('expiry_date >=', $asOfDate)
            ->groupEnd()
            ->orderBy('effective_date', 'DESC')
            ->orderBy('version_number', 'DESC');
    }
}
