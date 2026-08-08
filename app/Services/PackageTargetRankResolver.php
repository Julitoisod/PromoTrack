<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PromotionAssessmentModel;
use App\Models\PromotionRuleModel;
use App\Models\ReservistModel;

/**
 * Resolves the target (to) rank id for a promotion package: from its
 * linked assessment when one exists, otherwise from the single active
 * promotion rule configured for the reservist's current rank. Shared by
 * `RCDG\ReviewController` (grade-range routing) and
 * `PromotionOrdersController` (the new rank to record).
 */
class PackageTargetRankResolver
{
    public function __construct(
        private readonly PromotionAssessmentModel $promotionAssessmentModel,
        private readonly ReservistModel $reservistModel,
        private readonly PromotionRuleModel $promotionRuleModel,
    ) {
    }

    public function resolve(array $package): ?int
    {
        if ($package['promotion_assessment_id'] !== null) {
            $assessment = $this->promotionAssessmentModel->find($package['promotion_assessment_id']);

            if (isset($assessment['to_rank_id'])) {
                return (int) $assessment['to_rank_id'];
            }
        }

        $reservist = $this->reservistModel->find($package['reservist_id']);

        if ($reservist === null || $reservist->current_rank_id === null) {
            return null;
        }

        $rule = $this->promotionRuleModel->findActiveRuleForFromRank((int) $reservist->current_rank_id);

        return $rule !== null ? (int) $rule['to_rank_id'] : null;
    }
}
