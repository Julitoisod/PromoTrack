<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\DateDurationCalculator;
use App\Models\AssessmentRequirementModel;
use App\Models\PhysicalFitnessTestModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use App\Models\TrainingTypeModel;
use RuntimeException;

/**
 * Evaluates whether a reservist meets the promotion-eligibility criteria
 * for a target rank, using the versioned promotion rule that is
 * effective as of a given date.
 *
 * IMPORTANT: This service never invents an AFP/Philippine Army
 * eligibility threshold. Every numeric threshold and prerequisite it
 * checks comes exclusively from `promotion_rule_versions` rows that an
 * authorized administrator configured. When no rule/version has been
 * configured, or when a threshold column is null, the corresponding
 * criterion is reported as `unverified` rather than assumed to pass or
 * fail, and the overall result is flagged `requires_manual_review`.
 *
 * A reservist is never marked fully eligible on time-in-grade alone -
 * every configured criterion (MLES, physical fitness, additional
 * prerequisites) must also be satisfied or explicitly not required by
 * the effective rule version.
 */
class EligibilityService
{
    public function __construct(
        private readonly ReservistModel $reservistModel,
        private readonly PromotionRuleModel $promotionRuleModel,
        private readonly PromotionRuleVersionModel $promotionRuleVersionModel,
        private readonly ReservistTrainingModel $reservistTrainingModel,
        private readonly TrainingTypeModel $trainingTypeModel,
        private readonly PhysicalFitnessTestModel $physicalFitnessTestModel,
        private readonly DateDurationCalculator $dateDurationCalculator,
        private readonly ?PromotionAssessmentModel $promotionAssessmentModel = null,
        private readonly ?AssessmentRequirementModel $assessmentRequirementModel = null,
    ) {
    }

    /**
     * Evaluates $reservistId's eligibility for promotion to $toRankId as
     * of $asOfDate (defaults to today).
     *
     * @return array{
     *     reservist_id: int,
     *     from_rank_id: int|null,
     *     to_rank_id: int,
     *     education_track_id: int|null,
     *     as_of_date: string,
     *     promotion_rule_id: int|null,
     *     promotion_rule_version_id: int|null,
     *     version_number: int|null,
     *     time_in_grade_months: int|null,
     *     criteria: list<array{key: string, label: string, met: bool|null, detail: string, promotion_status?: string|null, remaining_months?: int|null, overdue_months?: int|null}>,
     *     is_eligible: bool,
     *     requires_manual_review: bool,
     *     blocking_reasons: list<string>,
     *     promotion_status: string|null
     * }
     */
    public function evaluate(int $reservistId, int $toRankId, ?string $asOfDate = null): array
    {
        $asOfDate ??= date('Y-m-d');

        $reservist = $this->reservistModel->find($reservistId);

        if ($reservist === null) {
            throw new RuntimeException("Reservist #{$reservistId} was not found.");
        }

        $fromRankId        = $reservist->current_rank_id !== null ? (int) $reservist->current_rank_id : null;
        $educationTrackId  = $reservist->education_track_id !== null ? (int) $reservist->education_track_id : null;

        $criteria         = [];
        $blockingReasons  = [];
        $requiresReview   = false;
        $rule             = null;
        $version          = null;
        $timeInGradeMonths = null;

        if ($fromRankId === null) {
            $blockingReasons[] = 'The reservist has no current rank on file.';
        } else {
            $rule = $this->promotionRuleModel->findForRankTransition($fromRankId, $toRankId);
        }

        if ($fromRankId !== null && $rule === null) {
            $blockingReasons[] = 'No active promotion rule is configured for this rank transition.';
        }

        if ($rule !== null) {
            $version = $this->promotionRuleVersionModel->resolveEffectiveVersion((int) $rule['id'], $asOfDate, $educationTrackId);

            if ($version === null) {
                $blockingReasons[] = 'No promotion rule version is effective as of the evaluation date.';
            }
        }

        $promotionStatus = null;

        if ($version !== null) {
            $anchor     = $reservist->date_of_last_promotion ?? $reservist->date_of_enlistment ?? null;
            $anchorDate = $anchor !== null ? $anchor->toDateString() : null;

            if ($anchorDate !== null) {
                $timeInGradeMonths = $this->dateDurationCalculator->totalMonthsBetween($anchorDate, $asOfDate);
            }

            $timeInGradeCriterion = $this->evaluateTimeInGrade($version, $timeInGradeMonths, $requiresReview);
            $criteria[]           = $timeInGradeCriterion;
            $promotionStatus      = $timeInGradeCriterion['promotion_status'];

            $criteria[] = $this->evaluateMles($reservistId, $version, $asOfDate, $requiresReview);
            $criteria[] = $this->evaluatePhysicalFitness($reservistId, $version, $requiresReview);

            foreach ($this->evaluateAdditionalPrerequisites($version, $requiresReview) as $criterion) {
                $criteria[] = $criterion;
            }
        }

        foreach ($criteria as $criterion) {
            if ($criterion['met'] === false) {
                $blockingReasons[] = "Criterion not met: {$criterion['label']}.";
            }
        }

        $isEligible = $blockingReasons === [] && $criteria !== [];

        return [
            'reservist_id'              => $reservistId,
            'from_rank_id'              => $fromRankId,
            'to_rank_id'                => $toRankId,
            'education_track_id'        => $educationTrackId,
            'as_of_date'                => $asOfDate,
            'promotion_rule_id'         => $rule['id'] ?? null,
            'promotion_rule_version_id' => $version['id'] ?? null,
            'version_number'            => $version['version_number'] ?? null,
            'time_in_grade_months'      => $timeInGradeMonths,
            'criteria'                  => $criteria,
            'is_eligible'               => $isEligible,
            'requires_manual_review'    => $requiresReview,
            'blocking_reasons'          => $blockingReasons,
            // The thesis's three-way MLES classification (Algorithm 1),
            // derived purely from time-in-grade vs. the configured
            // threshold: 'not_yet_eligible' (below), 'eligible' (meets
            // exactly), or 'overdue' (exceeds with no promotion order
            // recorded yet -- order tracking is out of this pure
            // function's scope, see PackageWorkflowService). Null when
            // the threshold or anchor date isn't resolvable.
            'promotion_status'          => $promotionStatus,
        ];
    }

    /**
     * Runs `evaluate()` and persists the result as an immutable
     * `promotion_assessments` row (plus one `assessment_requirements`
     * row per criterion), so the assessment that justified creating a
     * promotion package remains reproducible even if the underlying
     * promotion rule is revised later. Requires the assessment models to
     * have been supplied to the constructor.
     *
     * @return array `evaluate()`'s result, plus `assessment_id`.
     */
    public function assessAndSave(int $reservistId, int $toRankId, int $assessedByUserId, ?string $asOfDate = null): array
    {
        if ($this->promotionAssessmentModel === null || $this->assessmentRequirementModel === null) {
            throw new RuntimeException('EligibilityService was constructed without assessment persistence models.');
        }

        $result = $this->evaluate($reservistId, $toRankId, $asOfDate);

        $db = db_connect();
        $db->transStart();

        $this->promotionAssessmentModel->insert([
            'reservist_id'              => $result['reservist_id'],
            'from_rank_id'              => $result['from_rank_id'],
            'to_rank_id'                => $result['to_rank_id'],
            'education_track_id'        => $result['education_track_id'],
            'promotion_rule_id'         => $result['promotion_rule_id'],
            'promotion_rule_version_id' => $result['promotion_rule_version_id'],
            'as_of_date'                => $result['as_of_date'],
            'time_in_grade_months'      => $result['time_in_grade_months'],
            'promotion_status'          => $result['promotion_status'],
            'is_eligible'               => $result['is_eligible'] ? 1 : 0,
            'requires_manual_review'    => $result['requires_manual_review'] ? 1 : 0,
            'snapshot_json'             => json_encode($result, JSON_UNESCAPED_SLASHES),
            'assessed_by'               => $assessedByUserId,
        ]);

        $assessmentId = $this->promotionAssessmentModel->getInsertID();

        foreach ($result['criteria'] as $criterion) {
            $this->assessmentRequirementModel->insert([
                'promotion_assessment_id' => $assessmentId,
                'requirement_code'        => $criterion['key'],
                'requirement_label'       => $criterion['label'],
                'is_satisfied'            => $criterion['met'] === null ? null : ($criterion['met'] ? 1 : 0),
                'remarks'                 => $criterion['detail'],
            ]);
        }

        $db->transComplete();

        $result['assessment_id'] = $assessmentId;

        return $result;
    }

    /**
     * @return array{key: string, label: string, met: bool|null, detail: string, promotion_status: string|null, remaining_months: int|null, overdue_months: int|null}
     */
    private function evaluateTimeInGrade(array $version, ?int $timeInGradeMonths, bool &$requiresReview): array
    {
        $minimumMonths = $version['minimum_time_in_grade_months'] ?? null;

        if ($minimumMonths === null) {
            $requiresReview = true;

            return [
                'key'              => 'time_in_grade',
                'label'            => 'Minimum time in grade',
                'met'              => null,
                'detail'           => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION: no minimum time-in-grade threshold is configured for this rule version.',
                'promotion_status' => null,
                'remaining_months' => null,
                'overdue_months'   => null,
            ];
        }

        if ($timeInGradeMonths === null) {
            $requiresReview = true;

            return [
                'key'              => 'time_in_grade',
                'label'            => 'Minimum time in grade',
                'met'              => null,
                'detail'           => 'The reservist has no date of enlistment or date of last promotion on file to compute time in grade.',
                'promotion_status' => null,
                'remaining_months' => null,
                'overdue_months'   => null,
            ];
        }

        $minimumMonths    = (int) $minimumMonths;
        $met              = $timeInGradeMonths >= $minimumMonths;
        $remainingMonths  = null;
        $overdueMonths    = null;

        if ($timeInGradeMonths < $minimumMonths) {
            $promotionStatus = 'not_yet_eligible';
            $remainingMonths = $minimumMonths - $timeInGradeMonths;
            $detail          = "Not yet eligible: {$timeInGradeMonths} of {$minimumMonths} required month(s) in grade ({$remainingMonths} month(s) remaining).";
        } elseif ($timeInGradeMonths === $minimumMonths) {
            $promotionStatus = 'eligible';
            $detail          = "Eligible: reservist has met the {$minimumMonths}-month minimum time in grade.";
        } else {
            $promotionStatus = 'overdue';
            $overdueMonths   = $timeInGradeMonths - $minimumMonths;
            $detail          = "Overdue: reservist has exceeded the {$minimumMonths}-month minimum time in grade by {$overdueMonths} month(s) with no promotion order recorded.";
        }

        return [
            'key'              => 'time_in_grade',
            'label'            => 'Minimum time in grade',
            'met'              => $met,
            'detail'           => $detail,
            'promotion_status' => $promotionStatus,
            'remaining_months' => $remainingMonths,
            'overdue_months'   => $overdueMonths,
        ];
    }

    /**
     * @return array{key: string, label: string, met: bool|null, detail: string}
     */
    private function evaluateMles(int $reservistId, array $version, string $asOfDate, bool &$requiresReview): array
    {
        if ((int) ($version['requires_mles'] ?? 0) !== 1) {
            return [
                'key'    => 'mles',
                'label'  => 'MLES qualification',
                'met'    => true,
                'detail' => 'Not required by the effective promotion rule version.',
            ];
        }

        $trainings = $this->reservistTrainingModel->forReservist($reservistId);
        $mlesTypes = array_map(
            'intval',
            $this->trainingTypeModel->where('is_mles', 1)->findColumn('id') ?? []
        );

        $hasMles = false;

        foreach ($trainings as $training) {
            $completedOnOrBeforeAsOf = $training['date_completed'] === null
                || $training['date_completed'] <= $asOfDate;

            if (in_array((int) $training['training_type_id'], $mlesTypes, true) && $completedOnOrBeforeAsOf) {
                $hasMles = true;
                break;
            }
        }

        if ($mlesTypes === []) {
            $requiresReview = true;

            return [
                'key'    => 'mles',
                'label'  => 'MLES qualification',
                'met'    => null,
                'detail' => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION: no training type is marked as MLES-qualifying yet.',
            ];
        }

        return [
            'key'    => 'mles',
            'label'  => 'MLES qualification',
            'met'    => $hasMles,
            'detail' => $hasMles
                ? 'An MLES-qualifying training is on file as of the evaluation date.'
                : 'No MLES-qualifying training is on file as of the evaluation date.',
        ];
    }

    /**
     * @return array{key: string, label: string, met: bool|null, detail: string}
     */
    private function evaluatePhysicalFitness(int $reservistId, array $version, bool &$requiresReview): array
    {
        if ((int) ($version['requires_physical_fitness_test'] ?? 0) !== 1) {
            return [
                'key'    => 'pft',
                'label'  => 'Physical fitness test',
                'met'    => true,
                'detail' => 'Not required by the effective promotion rule version.',
            ];
        }

        $latest = $this->physicalFitnessTestModel->mostRecentForReservist($reservistId);

        if ($latest === null) {
            return [
                'key'    => 'pft',
                'label'  => 'Physical fitness test',
                'met'    => false,
                'detail' => 'No physical fitness test result is on file.',
            ];
        }

        $passed = $latest['result'] === 'pass';

        return [
            'key'    => 'pft',
            'label'  => 'Physical fitness test',
            'met'    => $passed,
            'detail' => $passed
                ? "Passed on {$latest['test_date']}."
                : "Most recent result on {$latest['test_date']} was '{$latest['result']}', not 'pass'.",
        ];
    }

    /**
     * @return list<array{key: string, label: string, met: bool|null, detail: string}>
     */
    private function evaluateAdditionalPrerequisites(array $version, bool &$requiresReview): array
    {
        $raw = $version['additional_prerequisites'] ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return [];
        }

        $requiresReview = true;

        $criteria = [];

        foreach ($decoded as $index => $prerequisite) {
            $label = is_string($prerequisite) ? $prerequisite : (string) json_encode($prerequisite);

            $criteria[] = [
                'key'    => 'additional_prerequisite_' . $index,
                'label'  => $label,
                'met'    => null,
                'detail' => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION: this prerequisite must be manually verified; it is not auto-checked.',
            ];
        }

        return $criteria;
    }
}
