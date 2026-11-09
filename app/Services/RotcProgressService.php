<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Reservist;
use App\Models\RotcProgressModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\RotcProgression;
use RuntimeException;

/**
 * Reads and records a student reservist's progress up the E1-E8 ladder the
 * client described in "Sign up.docx".
 *
 * The answers are the student's own report of where they stand. Nothing here
 * touches `reservists.current_rank_id`: the cadet and reserve ranks worked out
 * below are shown as "earned through ROTC" for the unit to verify, and the
 * rank on record still only changes through the promotion workflow.
 */
class RotcProgressService
{
    public const JUSTIFICATION_DIR = 'rotc_justifications';

    public function __construct(
        private readonly RotcProgressModel $progressModel,
        private readonly RotcProgression $config,
    ) {
    }

    /**
     * The E-level the reservist's year level puts them at, or null when they
     * are not a student reservist or have no year level on file.
     */
    public function currentStage(Reservist $reservist): ?string
    {
        if (! $reservist->isStudentReservist()) {
            return null;
        }

        return $this->config->yearLevelStages[(string) $reservist->student_year_level] ?? null;
    }

    /**
     * Everything the dashboard and the ROTC Progress page show, worked out
     * from the stored stage rows.
     *
     * @return array<string, mixed>|null null for a reservist who is not on the ladder
     */
    public function summarize(Reservist $reservist): ?array
    {
        $current = $this->currentStage($reservist);

        if ($current === null) {
            return null;
        }

        $rows     = $this->progressModel->stagesFor((int) $reservist->id);
        $codes    = array_keys($this->config->stages);
        $position = array_search($current, $codes, true);

        $stages  = [];
        $blocked = null;
        $pending = null;

        foreach ($codes as $index => $code) {
            $row      = $rows[$code] ?? null;
            $complete = $this->isComplete($code, $row);
            $isBlock  = $this->isBlocking($code, $row);

            if ($index > $position) {
                $state = 'locked';
            } elseif ($isBlock) {
                $state = 'blocked';
                $blocked ??= $code;
            } elseif ($complete) {
                $state = 'done';
            } else {
                $state = 'pending';
                $pending ??= $code;
            }

            $stages[$code] = [
                'code'     => $code,
                'label'    => 'E-' . substr($code, 1),
                'config'   => $this->config->stages[$code],
                'row'      => $row,
                'state'    => $state,
                'complete' => $complete,
            ];
        }

        $ranks = $this->ranks($stages);

        return [
            'currentStage'   => $current,
            'currentLabel'   => 'E-' . substr($current, 1),
            'currentLevel'   => $this->config->stages[$current]['level'],
            'stages'         => $stages,
            'cadetRank'      => $ranks['cadet'],
            'reserveRank'    => $ranks['reserve'],
            'rankHidden'     => $ranks['hidden'],
            'gmaEligible'    => $this->gmaEligible($rows),
            'classification' => $this->latestClassification($stages),
            'msCourses'      => $this->msCourses($rows),
            'trainingStatus' => $this->trainingStatus($stages[$current], $blocked),
            'status'         => $this->status($stages, $current, $blocked, $pending, $ranks),
        ];
    }

    /**
     * Records the student's answers for one stage.
     *
     * Throws RuntimeException with a message meant for the student when the
     * stage is not open to them yet or an answer is not one of the buttons.
     *
     * @param array<string, mixed> $input POST data for the stage form
     */
    public function saveStage(Reservist $reservist, string $stage, array $input, ?UploadedFile $justification, int $actorUserId): void
    {
        $current = $this->currentStage($reservist);
        $codes   = array_keys($this->config->stages);

        if ($current === null) {
            throw new RuntimeException('ROTC progress is only recorded for undergraduate student reservists.');
        }

        if (! in_array($stage, $codes, true)) {
            throw new RuntimeException('Unknown ROTC stage.');
        }

        if (array_search($stage, $codes, true) > array_search($current, $codes, true)) {
            throw new RuntimeException('That stage opens when you reach ' . $this->config->stages[$stage]['level'] . '.');
        }

        $fields = $this->config->stages[$stage]['fields'];
        $data   = [
            'classification'  => null,
            'training_result' => null,
            'recommendation'  => null,
            'pose_rank'       => null,
            'checks'          => [],
            'updated_by'      => $actorUserId,
        ];

        if (in_array('classification', $fields, true)) {
            $data['classification'] = $this->pick($input['classification'] ?? null, array_keys($this->config->classifications), 'classification');
        }

        if (in_array('training_result', $fields, true)) {
            $data['training_result'] = $this->pick($input['training_result'] ?? null, ['complete', 'incomplete'], 'training result');
        }

        if (in_array('recommendation', $fields, true)) {
            $data['recommendation'] = $this->pick($input['recommendation'] ?? null, ['recommended', 'not_recommended'], 'recommendation');

            // A student who is not recommended is dropped; there is no
            // standby/ready-reserve answer to give at this stage.
            if ($data['recommendation'] === 'not_recommended') {
                $data['classification'] = null;
            }
        }

        $allowedChecks = array_keys($this->checksFor($stage));
        $ticked        = array_map('strval', (array) ($input['checks'] ?? []));
        $data['checks'] = array_values(array_intersect($allowedChecks, $ticked));

        if (in_array('pose', $fields, true) && in_array('pose', $ticked, true)) {
            $data['checks'][] = 'pose';
            $data['pose_rank'] = $this->pick($input['pose_rank'] ?? null, $this->config->poseRanks, 'POSE rank');

            if ($data['pose_rank'] === null) {
                throw new RuntimeException('Choose the cadet rank you received with POSE.');
            }
        }

        if ($justification !== null && $justification->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($data['recommendation'] !== 'not_recommended') {
                throw new RuntimeException('A justification letter is only needed when you were not recommended.');
            }

            $stored = service('fileStorageService')->storeAttachment(
                $justification,
                self::JUSTIFICATION_DIR,
                ['application/pdf', 'image/jpeg', 'image/png'],
                'The justification letter must be a PDF, JPG, or PNG file.'
            );

            $data['justification_path']     = $stored['stored_filename'];
            $data['justification_filename'] = $stored['original_filename'];
        }

        $before = $this->progressModel->stagesFor((int) $reservist->id)[$stage] ?? [];

        if (! $this->progressModel->saveStage((int) $reservist->id, $stage, $data)) {
            throw new RuntimeException(implode(' ', $this->progressModel->errors()) ?: 'Your answers could not be saved.');
        }

        service('auditLogService')->record(
            $actorUserId,
            'rotc_progress.saved',
            'reservist',
            (int) $reservist->id,
            $before === [] ? [] : array_intersect_key($before, $data),
            ['stage' => $stage] + array_diff_key($data, ['updated_by' => true])
        );
    }

    /**
     * Checkbox key => label for $stage. E7 carries the six good-standing
     * semesters on top of its four MS courses.
     *
     * @return array<string, string>
     */
    public function checksFor(string $stage): array
    {
        $checks = $this->config->stages[$stage]['checks'] ?? [];

        return $stage === 'E7' ? $checks + $this->config->goodStanding : $checks;
    }

    // ------------------------------------------------------------------

    /**
     * @param list<string> $allowed
     */
    private function pick(mixed $value, array $allowed, string $what): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! in_array((string) $value, $allowed, true)) {
            throw new RuntimeException('That ' . $what . ' is not one of the choices.');
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function isComplete(string $code, ?array $row): bool
    {
        if ($row === null) {
            return false;
        }

        return match ($code) {
            'E3'    => $row['training_result'] !== null,
            'E4'    => $row['recommendation'] === 'not_recommended'
                || ($row['recommendation'] === 'recommended' && $row['classification'] !== null),
            'E6'    => $row['classification'] !== null
                && (! in_array('pose', $row['checks'], true) || $row['pose_rank'] !== null),
            'E7'    => true,
            default => $row['classification'] !== null,
        };
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function isBlocking(string $code, ?array $row): bool
    {
        return ($code === 'E3' && ($row['training_result'] ?? null) === 'incomplete')
            || ($code === 'E4' && ($row['recommendation'] ?? null) === 'not_recommended');
    }

    /**
     * Walks the reached stages in order and applies each rank rule from the
     * client's list.
     *
     * @param array<string, array<string, mixed>> $stages
     *
     * @return array{cadet: ?string, reserve: ?string, hidden: bool}
     */
    private function ranks(array $stages): array
    {
        $cadet   = null;
        $reserve = null;
        $hidden  = false;

        foreach ($stages as $code => $stage) {
            $row = $stage['row'];

            if ($stage['state'] === 'locked' || $row === null) {
                continue;
            }

            $checks = $row['checks'];
            $ready  = $row['classification'] === 'ready_reserve';

            switch ($code) {
                case 'E1':
                    $cadet = 'Cadet 4th Class';
                    break;

                case 'E2':
                    if (in_array('coqc_ms21', $checks, true) && in_array('coqc_ms22', $checks, true)) {
                        $cadet = 'Cadet 3rd Class';
                    }
                    $reserve = $ready ? 'Private' : $reserve;
                    break;

                case 'E3':
                    $hidden = $row['training_result'] === 'incomplete';
                    break;

                case 'E4':
                    if ($row['recommendation'] === 'recommended') {
                        $cadet   = 'CDT CPT 2CL';
                        $reserve = $ready ? 'Private First Class' : $reserve;
                    } elseif ($row['recommendation'] === 'not_recommended') {
                        $cadet = null;
                    }
                    break;

                case 'E5':
                    if (in_array('ms41', $checks, true) && in_array('ms42', $checks, true)) {
                        $cadet = 'CDT MAJ 1CL';
                    }
                    break;

                case 'E6':
                    if (in_array('pose', $checks, true) && $row['pose_rank'] !== null) {
                        $cadet = $row['pose_rank'];
                    }
                    $reserve = $ready ? 'Corporal' : $reserve;
                    break;

                case 'E8':
                    if ($ready) {
                        $reserve = 'Sergeant';
                    } elseif ($row['classification'] === 'standby' && $this->gmaEligibleFromStages($stages)) {
                        $reserve = 'GMA';
                    }
                    break;
            }
        }

        return ['cadet' => $hidden ? null : $cadet, 'reserve' => $hidden ? null : $reserve, 'hidden' => $hidden];
    }

    /**
     * @param array<string, array<string, mixed>> $rows stage rows keyed by code
     */
    private function gmaEligible(array $rows): bool
    {
        $checks   = $rows['E7']['checks'] ?? [];
        $required = array_keys($this->checksFor('E7'));

        return $required !== [] && array_diff($required, $checks) === [];
    }

    /**
     * @param array<string, array<string, mixed>> $stages
     */
    private function gmaEligibleFromStages(array $stages): bool
    {
        return $this->gmaEligible(array_filter(array_map(
            static fn (array $stage) => $stage['row'],
            $stages
        )));
    }

    /**
     * @param array<string, array<string, mixed>> $stages
     */
    private function latestClassification(array $stages): ?string
    {
        $latest = null;

        foreach ($stages as $stage) {
            if ($stage['state'] !== 'locked' && ($stage['row']['classification'] ?? null) !== null) {
                $latest = $stage['row']['classification'];
            }
        }

        return $latest !== null ? $this->config->classifications[$latest] : null;
    }

    /**
     * MS31-MS42 as the dashboard card shows them. A course counts as done
     * from the stage where it is taken, or from the E7 completion ticks.
     *
     * @param array<string, array<string, mixed>> $rows
     *
     * @return array<string, array{label: string, done: bool}>
     */
    private function msCourses(array $rows): array
    {
        $e7   = $rows['E7']['checks'] ?? [];
        $done = [
            'MS31' => ($rows['E3']['training_result'] ?? null) === 'complete',
            'MS32' => ($rows['E4']['recommendation'] ?? null) === 'recommended',
            'MS41' => in_array('ms41', $rows['E5']['checks'] ?? [], true),
            'MS42' => in_array('ms42', $rows['E5']['checks'] ?? [], true),
        ];

        $courses = [];

        foreach ($this->config->msCourses as $code => $label) {
            $courses[$code] = [
                'label' => $label,
                'done'  => $done[$code] || in_array(strtolower($code), $e7, true),
            ];
        }

        return $courses;
    }

    /**
     * @param array<string, mixed> $currentStage
     *
     * @return array{label: string, tone: string}
     */
    private function trainingStatus(array $currentStage, ?string $blocked): array
    {
        if ($blocked === 'E4') {
            return ['label' => 'Dropped', 'tone' => 'danger'];
        }

        if ($blocked === 'E3') {
            return ['label' => 'Incomplete', 'tone' => 'danger'];
        }

        return $currentStage['complete']
            ? ['label' => 'Completed', 'tone' => 'success']
            : ['label' => 'In Progress', 'tone' => 'warning'];
    }

    /**
     * The "Your Current Status" card.
     *
     * @param array<string, array<string, mixed>> $stages
     * @param array{cadet: ?string, reserve: ?string, hidden: bool} $ranks
     *
     * @return array{tone: string, title: string, message: string}
     */
    private function status(array $stages, string $current, ?string $blocked, ?string $pending, array $ranks): array
    {
        if ($blocked === 'E4') {
            $letter = ($stages['E4']['row']['justification_path'] ?? null) !== null;

            return [
                'tone'    => 'danger',
                'title'   => 'Not Recommended at E-4',
                'message' => $letter
                    ? 'Your justification letter is on file for your unit to review.'
                    : 'You were not recommended at MS32. Upload a justification letter on the ROTC Progress page.',
            ];
        }

        if ($blocked === 'E3') {
            return [
                'tone'    => 'danger',
                'title'   => 'Training Incomplete',
                'message' => 'Complete your MS31 training (RSCT / ARAPT). No rank is shown until you do.',
            ];
        }

        if ($pending !== null) {
            return [
                'tone'    => 'warning',
                'title'   => 'Action Needed for E-' . substr($pending, 1),
                'message' => 'Fill in ' . $this->config->stages[$pending]['level'] . ' on your ROTC Progress page.',
            ];
        }

        if ($current === 'E8' && $ranks['reserve'] !== null) {
            return [
                'tone'    => 'success',
                'title'   => 'ROTC Completed: ' . $ranks['reserve'],
                'message' => 'You have finished every stage of the ROTC ladder.',
            ];
        }

        if ($current === 'E8' && ($stages['E8']['row']['classification'] ?? null) === 'standby') {
            return [
                'tone'    => 'warning',
                'title'   => 'Not Yet GMA',
                'message' => 'Every MS course and good-standing semester must be ticked at E-7 to become GMA.',
            ];
        }

        return [
            'tone'    => 'success',
            'title'   => 'On Track for E-' . substr($current, 1),
            'message' => 'You have completed the required training for your current level.',
        ];
    }
}
