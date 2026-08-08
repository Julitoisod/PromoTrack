<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\PromotionRuleModel;
use App\Models\RankModel;

/**
 * Reservist- and promotion-rule-specific validation rules.
 *
 * These rules enforce structural/format correctness only. They never
 * encode an official AFP eligibility threshold -- those live in
 * `promotion_rule_versions` (data) and are evaluated by
 * `App\Services\EligibilityService` (logic), never hard-coded here.
 */
class ReservistRules
{
    /**
     * A reservist number must be 3-30 characters of uppercase letters,
     * digits, and hyphens. Exact AFP numbering format
     * REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION; this is a
     * conservative structural placeholder.
     */
    public function valid_reservist_number(int|string|null $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true;
        }

        if (preg_match('/^[A-Z0-9][A-Z0-9\-]{2,29}$/', (string) $str) !== 1) {
            $error = 'The {field} field must be 3-30 characters of uppercase letters, digits, and hyphens.';

            return false;
        }

        return true;
    }

    /**
     * The AFP serial-number format itself
     * REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION. This placeholder
     * only rejects obviously malformed values (whitespace-only,
     * disallowed characters).
     */
    public function valid_afp_serial_number(int|string|null $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9\-]{3,30}$/', (string) $str) !== 1) {
            $error = 'The {field} field must be 3-30 characters of letters, digits, and hyphens.';

            return false;
        }

        return true;
    }

    /**
     * Confirms that the target rank ($str, a rank id) is strictly higher
     * than the "from" rank held in $fields (another form field name,
     * typically `from_rank_id` or `current_rank_id`).
     *
     * Usage: `rank_progression[from_rank_id]`
     */
    public function rank_progression(int|string|null $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true;
        }

        $fromRankId = $data[$fields] ?? null;

        if ($fromRankId === null || $fromRankId === '') {
            return true;
        }

        /** @var RankModel $rankModel */
        $rankModel = model(RankModel::class);

        if (! $rankModel->isHigherRank((int) $fromRankId, (int) $str)) {
            $error = 'The {field} field must represent a higher rank than the current rank.';

            return false;
        }

        return true;
    }

    /**
     * Confirms that an active promotion_rules row exists mapping the
     * "from" rank ($fields, a form field name) to the target rank
     * ($str). Used to prevent a promotion package or rule form from
     * referencing a rank pair that has no configured promotion path.
     *
     * Usage: `promotion_target_rank[from_rank_id]`
     */
    public function promotion_target_rank(int|string|null $str, ?string $fields = null, array $data = [], ?string &$error = null): bool
    {
        if ($str === null || $str === '') {
            return true;
        }

        $fromRankId = $data[$fields] ?? null;

        if ($fromRankId === null || $fromRankId === '') {
            return true;
        }

        /** @var PromotionRuleModel $ruleModel */
        $ruleModel = model(PromotionRuleModel::class);

        $exists = $ruleModel
            ->where('from_rank_id', (int) $fromRankId)
            ->where('to_rank_id', (int) $str)
            ->where('is_active', 1)
            ->first();

        if ($exists === null) {
            $error = 'There is no active promotion rule configured for this rank pair.';

            return false;
        }

        return true;
    }
}
