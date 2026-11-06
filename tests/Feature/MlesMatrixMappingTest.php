<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Migrations\CorrectMlesMatrixRowsMigration;
use App\Database\Seeds\EducationTracksSeeder;
use App\Database\Seeds\PromotionRulesSeeder;
use App\Database\Seeds\PromotionRuleVersionsSeeder;
use App\Database\Seeds\RanksSeeder;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

require_once APPPATH . 'Database/Migrations/2026-11-06-000100_CorrectMlesMatrixRowsMigration.php';

/**
 * Table 3's rows are the grade a reservist is promoted INTO.
 *
 * The matrix was first seeded keyed on the grade promoted FROM, one row
 * too low. The client entered two reservists to land exactly on the
 * threshold -- a CPL on the ROTC track at 3 years, a SGT on the ROTC track
 * at 4 -- and both read Overdue, because the system was applying 2 and 3
 * years. Eligible could not be produced from the client's own reading of
 * the table at all.
 *
 * @internal
 */
final class MlesMatrixMappingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $seed      = [RanksSeeder::class, EducationTracksSeeder::class, PromotionRulesSeeder::class, PromotionRuleVersionsSeeder::class];

    private int $reservistCount = 0;

    public function testTheClientsExactThresholdRecordsReadEligible(): void
    {
        $this->assertSame('eligible', $this->statusFor('CPL', 'ROTC_PROF', 36), 'CPL -> SGT on ROTC is Table 3 E-4: 3 years.');
        $this->assertSame('eligible', $this->statusFor('SGT', 'ROTC_PROF', 48), 'SGT -> SSG on ROTC is Table 3 E-5: 4 years.');
    }

    public function testAllThreeVerdictsAreReachable(): void
    {
        $this->assertSame('not_yet_eligible', $this->statusFor('CPL', 'GENERAL', 60), 'CPL -> SGT on General is 7 years.');
        $this->assertSame('eligible', $this->statusFor('CPL', 'GENERAL', 84));
        $this->assertSame('overdue', $this->statusFor('CPL', 'GENERAL', 96));
    }

    public function testAPrivateIsNotDueForPromotionOnEnlistmentDay(): void
    {
        $this->assertSame('not_yet_eligible', $this->statusFor('PVT', 'GENERAL', 0), 'Table 3 E-1 (0 years) is the entry grade, not PVT -> PFC.');
        $this->assertSame('eligible', $this->statusFor('PVT', 'GENERAL', 24));
    }

    /**
     * An existing install already holds version 1 with the one-grade-low
     * figures. The migration must supersede them -- never edit them -- and
     * must leave alone any threshold an administrator changed on purpose.
     */
    public function testTheMigrationSupersedesTheOldRowsAndKeepsAdministratorChanges(): void
    {
        $cplRotc = $this->versionRow('CPL', 'ROTC_PROF');
        $sgtGen  = $this->versionRow('SGT', 'GENERAL');

        // Back to the pre-fix state: CPL/ROTC at the old 2 years; SGT/General
        // set by an administrator to a figure the old seeder never wrote.
        $this->db->table('promotion_rule_versions')->where('id', $cplRotc['id'])->update(['minimum_time_in_grade_months' => 24]);
        $this->db->table('promotion_rule_versions')->where('id', $sgtGen['id'])->update(['minimum_time_in_grade_months' => 99]);

        (new CorrectMlesMatrixRowsMigration())->up();

        $this->assertSame(36, $this->effectiveMonths('CPL', 'ROTC_PROF'));
        $this->assertSame(99, $this->effectiveMonths('SGT', 'GENERAL'), 'An administrator\'s own threshold was overwritten.');

        $this->seeInDatabase('promotion_rule_versions', [
            'id'                           => $cplRotc['id'],
            'minimum_time_in_grade_months' => 24,
            'expiry_date'                  => date('Y-m-d', strtotime('-1 day')),
        ]);

        $this->assertSame('eligible', $this->statusFor('CPL', 'ROTC_PROF', 36));
    }

    private function statusFor(string $rankCode, string $trackCode, int $monthsInGrade): ?string
    {
        $promoted = date('Y-m-d', strtotime("-{$monthsInGrade} months"));

        $this->db->table('reservists')->insert([
            'reservist_number'       => 'RSV-MLES-' . ++$this->reservistCount,
            'first_name'             => 'Matrix',
            'last_name'              => 'Case ' . $this->reservistCount,
            'current_rank_id'        => $this->idOf('ranks', 'rank_code', $rankCode),
            'education_track_id'     => $this->idOf('education_tracks', 'track_code', $trackCode),
            'date_of_enlistment'     => $promoted,
            'date_of_last_promotion' => $promoted,
            'status'                 => 'active',
        ]);

        $reservistId = (int) $this->db->insertID();
        $rule        = (new PromotionRuleModel())->findActiveRuleForFromRank($this->idOf('ranks', 'rank_code', $rankCode));

        return service('eligibilityService')->evaluate($reservistId, (int) $rule['to_rank_id'])['promotion_status'];
    }

    private function effectiveMonths(string $rankCode, string $trackCode): int
    {
        $rule = (new PromotionRuleModel())->findActiveRuleForFromRank($this->idOf('ranks', 'rank_code', $rankCode));

        return (int) (new PromotionRuleVersionModel())
            ->resolveEffectiveVersion((int) $rule['id'], date('Y-m-d'), $this->idOf('education_tracks', 'track_code', $trackCode))['minimum_time_in_grade_months'];
    }

    private function versionRow(string $rankCode, string $trackCode): array
    {
        $rule = (new PromotionRuleModel())->findActiveRuleForFromRank($this->idOf('ranks', 'rank_code', $rankCode));

        return $this->db->table('promotion_rule_versions')
            ->where('promotion_rule_id', $rule['id'])
            ->where('education_track_id', $this->idOf('education_tracks', 'track_code', $trackCode))
            ->get()
            ->getRowArray();
    }

    private function idOf(string $table, string $column, string $code): int
    {
        return (int) $this->db->table($table)->where($column, $code)->get()->getRow()->id;
    }
}
