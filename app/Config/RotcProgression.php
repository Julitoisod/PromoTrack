<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The student reservist E-level ladder, transcribed from the client's
 * "Sign up.docx" (revision of 2026-11-09, section "FOR STUDENT RESERVIST").
 *
 * Each stage lists the buttons the client asked for. The labels live here so
 * a wording change from the client is a config edit, not a code change; the
 * rules that turn the answers into a cadet rank, a reserve rank and a status
 * live in App\Services\RotcProgressService.
 *
 * Field keys a stage can carry:
 *  - classification   Standby / Ready Reserve buttons
 *  - training_result  Complete / Incomplete buttons
 *  - recommendation   Recommended / Not recommended buttons (+ justification letter)
 *  - checks           checkbox key => label
 *  - pose             POSE checkbox plus a cadet-rank dropdown
 */
class RotcProgression extends BaseConfig
{
    /**
     * Year level as stored in `reservists.student_year_level` => stage code.
     * Sign-up offers every semester from 1st Year 1st Sem (E1) to 4th
     * Year 2nd Sem (E8).
     *
     * @var array<string, string>
     */
    public array $yearLevelStages = [
        '1st Year 1st Sem' => 'E1',
        '1st Year 2nd Sem' => 'E2',
        '2nd Year 1st Sem' => 'E3',
        '2nd Year 2nd Sem' => 'E4',
        '3rd Year 1st Sem' => 'E5',
        '3rd Year 2nd Sem' => 'E6',
        '4th Year 1st Sem' => 'E7',
        '4th Year 2nd Sem' => 'E8',
    ];

    /**
     * @var array<string, string>
     */
    public array $classifications = [
        'standby'       => 'Standby Reserve',
        'ready_reserve' => 'Ready Reserve',
    ];

    /**
     * Cadet ranks a student may receive on passing POSE (stage E6).
     *
     * @var list<string>
     */
    public array $poseRanks = ['CDT LTC 1CL', 'CDT COL 1CL'];

    /**
     * The six semesters ticked under "good standing" at stage E7. Any one
     * left unticked means the student cannot become GMA at E8.
     *
     * @var array<string, string>
     */
    public array $goodStanding = [
        'gs_2_1' => '2nd Year 1st Sem',
        'gs_2_2' => '2nd Year 2nd Sem',
        'gs_3_1' => '3rd Year 1st Sem',
        'gs_3_2' => '3rd Year 2nd Sem',
        'gs_4_1' => '4th Year 1st Sem',
        'gs_4_2' => '4th Year 2nd Sem',
    ];

    /**
     * The admin panel's semester folders (client brief of 2026-11-10):
     * folder => the year level whose students it lists.
     *
     * @var array<string, string>
     */
    public array $enrollmentFolders = [
        'MS1'  => '1st Year 1st Sem',
        'MS2'  => '1st Year 2nd Sem',
        'MS31' => '2nd Year 1st Sem',
        'MS32' => '2nd Year 2nd Sem',
        'MS41' => '3rd Year 1st Sem',
        'MS42' => '3rd Year 2nd Sem',
    ];

    /**
     * The four MS courses shown on the dashboard's "My ROTC Progress" card.
     *
     * @var array<string, string>
     */
    public array $msCourses = [
        'MS31' => '2nd Year, 1st Sem',
        'MS32' => '2nd Year, 2nd Sem',
        'MS41' => '3rd Year, 1st Sem',
        'MS42' => '3rd Year, 2nd Sem',
    ];

    /**
     * @var array<string, array{
     *     level: string,
     *     title: string,
     *     note?: string,
     *     fields: list<string>,
     *     checks?: array<string, string>
     * }>
     */
    public array $stages = [
        'E1' => [
            'level'  => '1st Year 1st Sem',
            'title'  => 'Enrolled 1st Sem ROTC (Aspirant)',
            'note'   => 'Rank: Cadet 4th Class.',
            'fields' => ['classification'],
        ],
        'E2' => [
            'level'  => '1st Year 2nd Sem',
            'title'  => 'Enrolled ROTC (Aspirant)',
            'note'   => 'Rank: Cadet 3rd Class once the COQC for MS21 and MS22 are both in. '
                . 'After both semesters as Ready Reserve the rank is Private.',
            'fields' => ['checks', 'classification'],
            'checks' => [
                'coqc_ms21' => 'COQC MS21',
                'coqc_ms22' => 'COQC MS22',
            ],
        ],
        'E3' => [
            'level'  => '2nd Year 1st Sem',
            'title'  => 'Enrolled 1st Sem (MS31) RSCT / ARAPT',
            'note'   => 'If the training is not completed, no rank is shown.',
            'fields' => ['training_result'],
        ],
        'E4' => [
            'level'  => '2nd Year 2nd Sem',
            'title'  => 'Enrolled 2nd Sem (MS32)',
            'note'   => 'Recommended: Cadet Captain 2nd Class. Not recommended: dropped, and a '
                . 'justification letter can be uploaded. As Ready Reserve the rank is Private First Class.',
            'fields' => ['recommendation', 'classification'],
        ],
        'E5' => [
            'level'  => '3rd Year 1st Sem',
            'title'  => 'Enrolled 1st Sem ARAPT',
            'note'   => 'Once MS41 and MS42 are both finished the rank is CDT MAJ 1CL.',
            'fields' => ['checks', 'classification'],
            'checks' => [
                'ms41' => 'MS41 finished',
                'ms42' => 'MS42 finished',
            ],
        ],
        'E6' => [
            'level'  => '3rd Year 2nd Sem',
            'title'  => 'POSE',
            'note'   => 'Passing POSE gives CDT LTC 1CL or CDT COL 1CL. As Ready Reserve the rank is Corporal.',
            'fields' => ['pose', 'classification'],
        ],
        'E7' => [
            'level'  => '4th Year 1st Sem',
            'title'  => 'Course completion and good standing',
            'note'   => 'Every box must be ticked to become GMA at E-8.',
            'fields' => ['checks'],
            'checks' => [
                'ms31' => 'MS31',
                'ms32' => 'MS32',
                'ms41' => 'MS41',
                'ms42' => 'MS42',
            ],
        ],
        'E8' => [
            'level'  => '4th Year 2nd Sem',
            'title'  => 'Final classification',
            'note'   => 'Standby: rank GMA. Ready Reserve: rank Sergeant.',
            'fields' => ['classification'],
        ],
    ];
}
