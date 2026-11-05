<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Reservist;
use App\Models\PromotionRuleModel;
use App\Models\ReservistModel;
use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Turns a freshly self-registered Shield account into a reservist record
 * it can act on.
 *
 * A reservist signs up to upload their own promotion documents, and
 * documents hang off a promotion package, which hangs off a
 * `reservists` row -- so without this step a registered reservist has an
 * account that can reach nothing.
 *
 * Two flows have to meet here, because both are real: an S1 clerk or
 * superadmin encodes reservists in advance, and reservists also sign up
 * themselves. The AFP serial number is what joins them -- it is unique
 * on both `users` and `reservists` -- so signing up with a serial that
 * is already on file CLAIMS that record rather than creating a second
 * one for the same person.
 */
class ReservistRegistrationService
{
    public function __construct(
        private readonly ReservistModel $reservistModel,
        private readonly PromotionRuleModel $promotionRuleModel,
        private readonly UserModel $userModel,
    ) {
    }

    /**
     * Claims or creates $user's reservist record from the sign-up form
     * fields in $profile, and writes the assembled display name back to
     * the account.
     *
     * Returns the linked record, or null when the serial number is
     * already claimed by another account (unreachable while both serial
     * columns stay unique, but a silent no-op is the safe answer if a
     * migration ever drops one of those indexes).
     */
    public function linkAccount(User $user, array $profile): ?Reservist
    {
        $serial = trim((string) ($user->afp_serial_number ?? ''));

        $names = [
            'first_name'  => trim((string) ($profile['first_name'] ?? '')),
            'middle_name' => trim((string) ($profile['middle_name'] ?? '')) ?: null,
            'last_name'   => trim((string) ($profile['last_name'] ?? '')),
            'suffix'      => trim((string) ($profile['suffix'] ?? '')) ?: null,
        ];

        $schooling = [
            'is_student'         => ! empty($profile['is_student']) ? 1 : 0,
            'student_year_level' => null,
        ];

        if ($schooling['is_student'] === 1) {
            $yearLevel = (string) ($profile['student_year_level'] ?? '');

            $schooling['student_year_level'] = in_array($yearLevel, ReservistModel::STUDENT_YEAR_LEVELS, true)
                ? $yearLevel
                : null;
        }

        $companyUnitId = ! empty($profile['company_unit_id']) ? (int) $profile['company_unit_id'] : null;

        $service = [
            'current_rank_id'        => ! empty($profile['current_rank_id']) ? (int) $profile['current_rank_id'] : null,
            'date_of_enlistment'     => ! empty($profile['date_of_enlistment']) ? $profile['date_of_enlistment'] : null,
            'date_of_last_promotion' => ! empty($profile['date_of_last_promotion']) ? $profile['date_of_last_promotion'] : null,
        ];

        $existing = $serial !== '' ? $this->reservistModel->findByAfpSerialNumber($serial) : null;

        if ($existing !== null && $existing->user_id !== null && $existing->user_id !== (int) $user->id) {
            return null;
        }

        $this->userModel->update($user->id, ['full_name' => $this->displayName($names)]);

        if ($existing !== null) {
            // The clerk already encoded this reservist: keep everything
            // they recorded and fill in only what sign-up is authoritative
            // for -- the account link and the two answers only the
            // reservist can give.
            // Whatever the clerk already recorded wins: their rank and dates
            // come from service records, the reservist's come from memory.
            $this->reservistModel->update($existing->id, array_merge($schooling, [
                'id'                     => $existing->id,
                'user_id'                => (int) $user->id,
                'company_unit_id'        => $companyUnitId ?? $existing->company_unit_id,
                'current_rank_id'        => $existing->current_rank_id ?? $service['current_rank_id'],
                'date_of_enlistment'     => $existing->date_of_enlistment?->toDateString() ?? $service['date_of_enlistment'],
                'date_of_last_promotion' => $existing->date_of_last_promotion?->toDateString() ?? $service['date_of_last_promotion'],
            ]));

            $this->assess($existing->id, (int) $user->id);

            return $this->reservistModel->find($existing->id);
        }

        $this->reservistModel->insert(array_merge($names, $schooling, $service, [
            'user_id'                => (int) $user->id,
            'reservist_number'       => $this->nextReservistNumber($serial),
            'afp_serial_number'      => $serial !== '' ? $serial : null,
            'organizational_unit_id' => $user->organizational_unit_id !== null ? (int) $user->organizational_unit_id : null,
            'company_unit_id'        => $companyUnitId,
            'email'                  => $user->email,
            'status'                 => 'active',
        ]));

        $reservistId = (int) $this->reservistModel->getInsertID();

        $this->assess($reservistId, (int) $user->id);

        return $this->reservistModel->find($reservistId);
    }

    /**
     * Runs the eligibility assessment straight after sign-up, so a
     * reservist sees their real standing rather than "Pending" on the
     * very first page they land on.
     *
     * Silent when there is no rank or no rule for it -- Pending is then
     * the honest answer, and `MyPromotionController` explains it.
     */
    private function assess(int $reservistId, int $actorUserId): void
    {
        $reservist = $this->reservistModel->find($reservistId);

        if ($reservist === null || $reservist->current_rank_id === null) {
            return;
        }

        $rule = $this->promotionRuleModel->findActiveRuleForFromRank((int) $reservist->current_rank_id);

        if ($rule === null) {
            return;
        }

        service('eligibilityService')->assessAndSave($reservistId, (int) $rule['to_rank_id'], $actorUserId);
    }

    /**
     * "Dela Cruz, Juan P. Jr." -- the same shape
     * `Reservist::getFullName()` renders, so the account name and the
     * record name read identically.
     */
    private function displayName(array $names): string
    {
        $name = trim($names['last_name'] . ', ' . $names['first_name'], ' ,');

        if ($names['middle_name'] !== null) {
            $name .= ' ' . mb_substr($names['middle_name'], 0, 1) . '.';
        }

        if ($names['suffix'] !== null) {
            $name .= ' ' . $names['suffix'];
        }

        return trim($name);
    }

    /**
     * Derives the reservist number from the AFP serial number, which is
     * itself unique -- a colliding serial would have been claimed above,
     * never reach this method. Shaped to satisfy
     * `ReservistRules::valid_reservist_number` (uppercase alphanumerics
     * and hyphens, 3-30 characters).
     */
    private function nextReservistNumber(string $serial): string
    {
        $suffix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $serial) ?? '');

        if ($suffix === '') {
            // No usable serial: fall back to a sequential number, still
            // unique because it is checked against the table.
            $suffix = str_pad((string) ($this->reservistModel->countAllResults(false) + 1), 6, '0', STR_PAD_LEFT);

            while ($this->reservistModel->where('reservist_number', 'RSV-' . $suffix)->countAllResults() > 0) {
                $suffix = str_pad((string) ((int) $suffix + 1), 6, '0', STR_PAD_LEFT);
            }
        }

        return 'RSV-' . mb_substr($suffix, 0, 26);
    }
}
