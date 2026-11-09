<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Reservist;
use App\Models\ReservistModel;
use App\Models\RotcProgressModel;
use App\Services\RotcProgressService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\RotcProgression;
use RuntimeException;

/**
 * The student reservist E1-E8 ladder from the client's "Sign up.docx":
 * which answers complete a stage, which ones block, and which cadet and
 * reserve ranks the answers earn.
 *
 * @internal
 */
final class RotcProgressServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private RotcProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RotcProgressService(new RotcProgressModel(), new RotcProgression());
    }

    private function student(string $yearLevel, string $attainment = 'undergraduate', int $isStudent = 1): Reservist
    {
        static $sequence = 0;
        $sequence++;

        $this->db->table('reservists')->insert([
            'reservist_number'       => 'RSV-ROTC' . $sequence,
            'first_name'             => 'Test',
            'last_name'              => 'Cadet',
            'is_student'             => $isStudent,
            'student_year_level'     => $yearLevel,
            'educational_attainment' => $attainment,
            'status'                 => 'active',
        ]);

        return (new ReservistModel())->find((int) $this->db->insertID());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function save(Reservist $reservist, string $stage, array $input): void
    {
        $this->service->saveStage($reservist, $stage, $input, null, 1);
    }

    public function testTheYearLevelSetsTheELevel(): void
    {
        $this->assertSame('E3', $this->service->currentStage($this->student('2nd Year 1st Sem')));
        $this->assertSame('E4', $this->service->currentStage($this->student('2nd Year 2nd Sem')));
        $this->assertSame('E8', $this->service->currentStage($this->student('4th Year 2nd Sem')));
    }

    public function testOnlyEnrolledUndergraduatesAreOnTheLadder(): void
    {
        $this->assertNull($this->service->summarize($this->student('2nd Year 1st Sem', 'bachelor')));
        $this->assertNull($this->service->summarize($this->student('2nd Year 1st Sem', 'undergraduate', 0)));
    }

    public function testAStageAboveTheCurrentLevelCannotBeFilledIn(): void
    {
        $reservist = $this->student('2nd Year 1st Sem');

        $this->expectException(RuntimeException::class);
        $this->save($reservist, 'E5', ['classification' => 'ready_reserve']);
    }

    public function testAnAnswerThatIsNotOneOfTheButtonsIsRefused(): void
    {
        $reservist = $this->student('2nd Year 1st Sem');

        $this->expectException(RuntimeException::class);
        $this->save($reservist, 'E1', ['classification' => 'active_duty']);
    }

    /**
     * The client's ladder walked to E-4: Cadet 3rd Class needs both COQCs,
     * Ready Reserve after E-2 makes a Private, recommended at E-4 makes
     * CDT CPT 2CL, and Ready Reserve then makes a Private First Class.
     */
    public function testTheRanksEarnedFollowTheClientsLadder(): void
    {
        $reservist = $this->student('2nd Year 2nd Sem');

        $this->save($reservist, 'E1', ['classification' => 'standby']);
        $this->assertSame('Cadet 4th Class', $this->service->summarize($reservist)['cadetRank']);

        $this->save($reservist, 'E2', ['checks' => ['coqc_ms21'], 'classification' => 'ready_reserve']);
        $summary = $this->service->summarize($reservist);
        $this->assertSame('Cadet 4th Class', $summary['cadetRank'], 'One COQC is not enough for Cadet 3rd Class.');
        $this->assertSame('Private', $summary['reserveRank']);

        $this->save($reservist, 'E2', ['checks' => ['coqc_ms21', 'coqc_ms22'], 'classification' => 'ready_reserve']);
        $this->assertSame('Cadet 3rd Class', $this->service->summarize($reservist)['cadetRank']);

        $this->save($reservist, 'E3', ['training_result' => 'complete']);
        $this->save($reservist, 'E4', ['recommendation' => 'recommended', 'classification' => 'ready_reserve']);

        $summary = $this->service->summarize($reservist);
        $this->assertSame('CDT CPT 2CL', $summary['cadetRank']);
        $this->assertSame('Private First Class', $summary['reserveRank']);
        $this->assertSame('Ready Reserve', $summary['classification']);
        $this->assertSame('success', $summary['status']['tone']);
        $this->assertSame('On Track for E-4', $summary['status']['title']);
        $this->assertTrue($summary['msCourses']['MS31']['done']);
        $this->assertTrue($summary['msCourses']['MS32']['done']);
        $this->assertFalse($summary['msCourses']['MS41']['done']);
    }

    public function testAnIncompleteMs31HidesTheRank(): void
    {
        $reservist = $this->student('2nd Year 1st Sem');

        $this->save($reservist, 'E1', ['classification' => 'ready_reserve']);
        $this->save($reservist, 'E2', ['checks' => ['coqc_ms21', 'coqc_ms22'], 'classification' => 'ready_reserve']);
        $this->save($reservist, 'E3', ['training_result' => 'incomplete']);

        $summary = $this->service->summarize($reservist);

        $this->assertTrue($summary['rankHidden']);
        $this->assertNull($summary['cadetRank']);
        $this->assertNull($summary['reserveRank']);
        $this->assertSame('danger', $summary['status']['tone']);
        $this->assertSame('Incomplete', $summary['trainingStatus']['label']);
    }

    public function testNotRecommendedAtE4DropsTheStudentAndDiscardsTheClassification(): void
    {
        $reservist = $this->student('2nd Year 2nd Sem');

        $this->save($reservist, 'E4', ['recommendation' => 'not_recommended', 'classification' => 'ready_reserve']);

        $row = (new RotcProgressModel())->stagesFor($reservist->id)['E4'];
        $this->assertNull($row['classification'], 'A dropped student cannot also be Ready Reserve at E-4.');

        $summary = $this->service->summarize($reservist);
        $this->assertSame('blocked', $summary['stages']['E4']['state']);
        $this->assertSame('Dropped', $summary['trainingStatus']['label']);
        $this->assertStringContainsString('justification letter', $summary['status']['message']);
    }

    public function testPoseNeedsTheCadetRankItGave(): void
    {
        $reservist = $this->student('3rd Year 2nd Sem');

        $this->expectException(RuntimeException::class);
        $this->save($reservist, 'E6', ['checks' => ['pose'], 'classification' => 'ready_reserve']);
    }

    public function testPoseAndReadyReserveAtE6(): void
    {
        $reservist = $this->student('3rd Year 2nd Sem');

        $this->save($reservist, 'E5', ['checks' => ['ms41', 'ms42'], 'classification' => 'ready_reserve']);
        $this->assertSame('CDT MAJ 1CL', $this->service->summarize($reservist)['cadetRank']);

        $this->save($reservist, 'E6', ['checks' => ['pose'], 'pose_rank' => 'CDT COL 1CL', 'classification' => 'ready_reserve']);

        $summary = $this->service->summarize($reservist);
        $this->assertSame('CDT COL 1CL', $summary['cadetRank']);
        $this->assertSame('Corporal', $summary['reserveRank']);
    }

    /**
     * E-7 asks for every MS course and all six good-standing semesters;
     * anything short of that and Standby at E-8 does not make a GMA.
     */
    public function testGmaNeedsEveryBoxAtE7(): void
    {
        $reservist = $this->student('4th Year 2nd Sem');
        $all       = ['ms31', 'ms32', 'ms41', 'ms42', 'gs_2_1', 'gs_2_2', 'gs_3_1', 'gs_3_2', 'gs_4_1', 'gs_4_2'];

        $this->save($reservist, 'E7', ['checks' => array_slice($all, 0, -1)]);
        $this->save($reservist, 'E8', ['classification' => 'standby']);

        $summary = $this->service->summarize($reservist);
        $this->assertFalse($summary['gmaEligible']);
        $this->assertNotSame('GMA', $summary['reserveRank']);

        $this->save($reservist, 'E7', ['checks' => $all]);

        $summary = $this->service->summarize($reservist);
        $this->assertTrue($summary['gmaEligible']);
        $this->assertSame('GMA', $summary['reserveRank']);

        $this->save($reservist, 'E8', ['classification' => 'ready_reserve']);
        $this->assertSame('Sergeant', $this->service->summarize($reservist)['reserveRank']);
    }

    public function testATickedBoxThatIsNotOnTheStageIsIgnored(): void
    {
        $reservist = $this->student('2nd Year 1st Sem');

        $this->save($reservist, 'E2', ['checks' => ['coqc_ms21', 'gs_2_1', '<script>'], 'classification' => 'standby']);

        $this->assertSame(['coqc_ms21'], (new RotcProgressModel())->stagesFor($reservist->id)['E2']['checks']);
    }
}
