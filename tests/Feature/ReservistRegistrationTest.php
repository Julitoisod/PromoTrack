<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;

/**
 * Self-registration: a reservist signs up, and comes out the other side
 * with a reservist record their account owns.
 *
 * The case that matters most here is the first one. Registration used to
 * put every self-registered account in `unit_s1_clerk`, which handed a
 * stranger `reservists.create` and `reservists.update` over a whole unit
 * -- so "a new sign-up is a reservist and nothing more" is asserted
 * directly, not inferred from the config file.
 *
 * @internal
 */
final class ReservistRegistrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;

    private int $unitId;
    private int $companyId;
    private int $rankId;
    private int $trackId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->unitId = $this->insertRow('organizational_units', [
            'unit_code' => 'REG-UNIT',
            'unit_name' => 'Registration Test Unit',
            'unit_type' => 'reserve_unit',
            'is_active' => 1,
        ]);

        $this->companyId = $this->insertRow('organizational_units', [
            'parent_unit_id' => $this->unitId,
            'unit_code'      => 'REG-UNIT-A',
            'unit_name'      => 'Alpha Company',
            'unit_type'      => 'company',
            'is_active'      => 1,
        ]);

        $this->rankId = $this->insertRow('ranks', [
            'rank_code'  => 'REG-R1',
            'rank_name'  => 'Registration Test Rank',
            'sort_order' => 1,
            'is_active'  => 1,
        ]);

        $this->trackId = $this->insertRow('education_tracks', [
            'track_code' => 'REG-ROTC',
            'track_name' => 'Registration ROTC Professional',
            'is_active'  => 1,
        ]);
    }

    protected function tearDown(): void
    {
        auth()->logout();
        session()->destroy();

        parent::tearDown();
    }

    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function signUp(array $overrides = []): void
    {
        $this->post('/register', array_merge([
            'first_name'             => 'Juan',
            'middle_name'            => 'Perez',
            'last_name'              => 'Dela Cruz',
            'afp_serial_number'      => 'REG-123456',
            'organizational_unit_id' => $this->unitId,
            'company_unit_id'        => $this->companyId,
            'current_rank_id'        => $this->rankId,
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2024-01-01',
            'education_track_id'     => $this->trackId,
            'is_student'             => '1',
            'student_year_level'     => '3rd Year',
            'email'                  => 'juan@promotrack.local',
            'username'               => 'juandelacruz',
            'password'               => 'RegisterPass!12345',
            'password_confirm'       => 'RegisterPass!12345',
        ], $overrides));
    }

    public function testSignUpCreatesAReservistAccountAndNotAClerkAccount(): void
    {
        $this->signUp();

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->assertNotNull($user, 'Sign-up did not create an account.');
        $this->assertTrue($user->inGroup('reservist'));
        $this->assertFalse(
            $user->inGroup('unit_s1_clerk'),
            'A self-registered account was given unit clerk rights.'
        );
        $this->assertFalse($user->can('reservists.create'));
        $this->assertFalse($user->can('reservists.update'));
        $this->assertTrue($user->can('documents.upload'));
        $this->assertTrue($user->can('promotions.view_own'));
    }

    public function testSignUpCreatesTheReservistRecordTheAccountOwns(): void
    {
        $this->signUp();

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->seeInDatabase('reservists', [
            'user_id'                => $user->id,
            'afp_serial_number'      => 'REG-123456',
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'organizational_unit_id' => $this->unitId,
            'company_unit_id'        => $this->companyId,
            'is_student'             => 1,
            'student_year_level'     => '3rd Year',
        ]);
    }

    public function testSignUpClaimsTheRecordTheClerkAlreadyEncodedInsteadOfDuplicatingIt(): void
    {
        $existingId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-PREENCODED',
            'afp_serial_number'      => 'REG-123456',
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2024-01-01',
            'organizational_unit_id' => $this->unitId,
            'status'                 => 'active',
        ]);

        $this->signUp();

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->assertSame(
            1,
            $this->db->table('reservists')->where('afp_serial_number', 'REG-123456')->countAllResults(),
            'Sign-up created a second record for a reservist already on file.'
        );

        $this->seeInDatabase('reservists', [
            'id'                 => $existingId,
            'user_id'            => $user->id,
            'company_unit_id'    => $this->companyId,
            'is_student'         => 1,
            'student_year_level' => '3rd Year',
            // The clerk left the track blank, so the reservist's answer fills it.
            'education_track_id' => $this->trackId,
            // What the clerk recorded is left exactly as it was.
            'reservist_number'       => 'RSV-PREENCODED',
            'date_of_last_promotion' => '2024-01-01',
        ]);
    }

    public function testAYearLevelIsNotStoredForSomeoneWhoIsNoLongerAStudent(): void
    {
        $this->signUp(['is_student' => '0', 'student_year_level' => '3rd Year']);

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->seeInDatabase('reservists', [
            'user_id'            => $user->id,
            'is_student'         => 0,
            'student_year_level' => null,
        ]);
    }

    /**
     * The point of collecting rank and an anchor date at sign-up: the
     * reservist lands on a real standing instead of "Pending". Before
     * this, every self-registered reservist read Pending forever,
     * because there was no rank to resolve a rule with and no date to
     * measure time in grade against.
     */
    public function testSignUpProducesARealStandingRatherThanPending(): void
    {
        $toRankId = $this->insertRow('ranks', [
            'rank_code'  => 'REG-R2',
            'rank_name'  => 'Registration Test Rank 2',
            'sort_order' => 2,
            'is_active'  => 1,
        ]);

        $ruleId = $this->insertRow('promotion_rules', [
            'rule_name'    => 'Registration test rule',
            'from_rank_id' => $this->rankId,
            'to_rank_id'   => $toRankId,
            'is_active'    => 1,
        ]);

        $this->insertRow('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => null,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 24,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        // Promoted well over the 24-month threshold.
        $this->signUp(['date_of_last_promotion' => '2020-01-01']);

        $user      = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);
        $reservist = $this->db->table('reservists')->where('user_id', $user->id)->get()->getRowArray();

        $assessment = $this->db->table('promotion_assessments')
            ->where('reservist_id', $reservist['id'])
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        $this->assertNotNull($assessment, 'Sign-up left the reservist with no assessment at all.');
        $this->assertSame(
            'overdue',
            $assessment['promotion_status'],
            'A reservist who signed up well past the threshold still reads as Pending.'
        );
    }

    /**
     * The live report of 2026-11-06: a SGT 48 months in grade signed up,
     * expected to read as promotable, and read "Not Yet Eligible". Sign-up
     * collected no education track, so the assessment fell back to the
     * track-agnostic baseline -- the General figure, 84 months -- instead
     * of their own track's 36.
     */
    public function testTheEducationTrackPicksTheMlesThresholdTheReservistIsAssessedOn(): void
    {
        $toRankId = $this->insertRow('ranks', [
            'rank_code'  => 'REG-R2',
            'rank_name'  => 'Registration Test Rank 2',
            'sort_order' => 2,
            'is_active'  => 1,
        ]);

        $ruleId = $this->insertRow('promotion_rules', [
            'rule_name'    => 'Registration test rule',
            'from_rank_id' => $this->rankId,
            'to_rank_id'   => $toRankId,
            'is_active'    => 1,
        ]);

        // Baseline (General) 84 months; this reservist's own track 36.
        foreach ([[null, 84], [$this->trackId, 36]] as [$trackId, $months]) {
            $this->insertRow('promotion_rule_versions', [
                'promotion_rule_id'            => $ruleId,
                'education_track_id'           => $trackId,
                'version_number'               => 1,
                'minimum_time_in_grade_months' => $months,
                'effective_date'               => '2020-01-01',
                'is_active'                    => 1,
            ]);
        }

        $this->signUp(['date_of_last_promotion' => date('Y-m-d', strtotime('-48 months'))]);

        $user      = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);
        $reservist = $this->db->table('reservists')->where('user_id', $user->id)->get()->getRowArray();

        $this->assertSame($this->trackId, (int) $reservist['education_track_id']);

        $assessment = $this->db->table('promotion_assessments')
            ->where('reservist_id', $reservist['id'])
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        $this->assertSame(
            'overdue',
            $assessment['promotion_status'],
            '48 months against the track\'s 36 is overdue; "not_yet_eligible" means the General 84 was used.'
        );
        $this->assertSame(12, \App\Models\PromotionAssessmentModel::timeInGradeCriterion($assessment)['overdue_months']);
    }

    public function testSignUpRequiresAnEducationTrack(): void
    {
        $this->signUp(['education_track_id' => '']);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'An account was created with no education track, which silently assesses on the longest threshold.'
        );
    }

    /**
     * Only tracks with a Table 3 threshold of their own are offered:
     * any other track lands on the General baseline, so listing it would
     * re-open the exact trap the field exists to close.
     */
    public function testTheSignUpFormOffersOnlyTracksWithTheirOwnThreshold(): void
    {
        $toRankId = $this->insertRow('ranks', [
            'rank_code'  => 'REG-R2',
            'rank_name'  => 'Registration Test Rank 2',
            'sort_order' => 2,
            'is_active'  => 1,
        ]);

        $ruleId = $this->insertRow('promotion_rules', [
            'rule_name'    => 'Registration test rule',
            'from_rank_id' => $this->rankId,
            'to_rank_id'   => $toRankId,
            'is_active'    => 1,
        ]);

        $this->insertRow('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => $this->trackId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 36,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        $this->insertRow('education_tracks', [
            'track_code' => 'REG-GRAD',
            'track_name' => 'Registration Unmapped Graduate',
            'is_active'  => 1,
        ]);

        $page = $this->get('/register');

        $page->assertSee('Registration ROTC Professional');
        $page->assertDontSee('Registration Unmapped Graduate');
    }

    public function testSignUpRequiresARankSoTheStandingCanBeComputed(): void
    {
        $this->signUp(['current_rank_id' => '']);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'An account was created with no rank, which can never produce a standing.'
        );
    }

    public function testSignUpRequiresAUnitAndACompany(): void
    {
        $this->signUp(['company_unit_id' => '']);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'An account was created without a company.'
        );
    }
}
