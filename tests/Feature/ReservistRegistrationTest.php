<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
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
    use AuthenticationTesting;
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
            'rank_category' => 'enlisted',
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
            'sex'                    => 'male',
            'date_of_birth'          => '1995-05-05',
            'contact_number'         => '09171234567',
            'address'                => 'San Francisco, Agusan del Sur',
            'educational_attainment' => 'undergraduate',
            'rank_category'          => 'enlisted',
            'current_rank_id'        => $this->rankId,
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2024-01-01',
            'education_track_id'     => $this->trackId,
            'is_student'             => '1',
            'student_year_level'     => '3rd Year 1st Sem',
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
            'student_year_level'     => '3rd Year 1st Sem',
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
            'student_year_level' => '3rd Year 1st Sem',
            // The clerk left the track blank, so the reservist's answer fills it.
            'education_track_id' => $this->trackId,
            // What the clerk recorded is left exactly as it was.
            'reservist_number'       => 'RSV-PREENCODED',
            'date_of_last_promotion' => '2024-01-01',
        ]);
    }

    public function testAYearLevelIsNotStoredForSomeoneWhoIsNoLongerAStudent(): void
    {
        $this->signUp(['is_student' => '0', 'student_year_level' => '3rd Year 1st Sem']);

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

    /**
     * The client's form no longer asks for the MLES track: it is worked out
     * from the educational attainment, so a reservist cannot land on the
     * General baseline just by skipping a field they did not understand.
     */
    public function testTheEducationTrackIsWorkedOutFromTheEducationalAttainment(): void
    {
        $collegeTrackId = $this->insertRow('education_tracks', [
            'track_code' => 'COLLEGE_1STYR',
            'track_name' => 'Completed 1st Year College',
            'is_active'  => 1,
        ]);

        $this->signUp(['education_track_id' => '']);

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->assertNotNull($user, 'Sign-up without a track should be accepted now that it is derived.');
        $this->seeInDatabase('reservists', [
            'user_id'                => $user->id,
            'educational_attainment' => 'undergraduate',
            'education_track_id'     => $collegeTrackId,
        ]);
    }

    /**
     * A 1st Year student can sign up (MS1 / MS2), but has not completed
     * 1st Year College yet, so they are assessed on the General track.
     */
    public function testAFirstYearStudentSignsUpOnTheGeneralTrack(): void
    {
        $generalTrackId = $this->insertRow('education_tracks', [
            'track_code' => 'GENERAL',
            'track_name' => 'General',
            'is_active'  => 1,
        ]);
        $this->insertRow('education_tracks', [
            'track_code' => 'COLLEGE_1STYR',
            'track_name' => 'Completed 1st Year College',
            'is_active'  => 1,
        ]);

        $this->signUp(['education_track_id' => '', 'student_year_level' => '1st Year 1st Sem']);

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->assertNotNull($user, 'A 1st Year student should be able to sign up.');
        $this->seeInDatabase('reservists', [
            'user_id'            => $user->id,
            'student_year_level' => '1st Year 1st Sem',
            'education_track_id' => $generalTrackId,
        ]);
    }

    public function testThePersonalDetailsFromTheFormAreStoredOnTheRecord(): void
    {
        $this->signUp(['educational_attainment' => 'bachelor', 'degree_details' => 'BS Information Technology', 'is_student' => '0']);

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->seeInDatabase('reservists', [
            'user_id'                => $user->id,
            'middle_name'            => 'Perez',
            'sex'                    => 'male',
            'date_of_birth'          => '1995-05-05',
            'contact_number'         => '09171234567',
            'address'                => 'San Francisco, Agusan del Sur',
            'educational_attainment' => 'bachelor',
            'degree_details'         => 'BS Information Technology',
            // A year level belongs to undergraduates only.
            'student_year_level'     => null,
        ]);

        // Only the middle initial is printed in the generated name.
        $this->assertSame('Dela Cruz, Juan P.', $user->full_name ?? $this->db->table('users')->where('id', $user->id)->get()->getRow()->full_name);
    }

    /**
     * @dataProvider refusedSignUpProvider
     *
     * @param array<string, mixed> $overrides
     */
    public function testSignUpIsRefusedWhenARequiredAnswerIsMissingOrWrong(array $overrides, string $why): void
    {
        $this->signUp($overrides);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'An account was created although ' . $why . '.'
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function refusedSignUpProvider(): array
    {
        return [
            'no middle name'            => [['middle_name' => ''], 'the middle name is required'],
            'no serial number'          => [['afp_serial_number' => ''], 'the serial number is required'],
            'no sex'                    => [['sex' => ''], 'sex is required'],
            'no birth date'             => [['date_of_birth' => ''], 'the date of birth is required'],
            'bad contact number'        => [['contact_number' => '12345'], 'the contact number is not a mobile number'],
            'no address'                => [['address' => ''], 'the address is required'],
            'no attainment'             => [['educational_attainment' => ''], 'educational attainment is required'],
            'undergrad, no year level'  => [['student_year_level' => ''], 'an undergraduate must give a year level'],
            'old whole-year value'      => [['student_year_level' => '3rd Year'], 'the year level must be a semester'],
            'bachelor, degree unnamed'  => [['educational_attainment' => 'bachelor', 'degree_details' => ''], 'a bachelor\'s degree must be named'],
            'others, not specified'     => [['educational_attainment' => 'others', 'degree_details' => ''], '"others" must be specified'],
            'no rank category'          => [['rank_category' => ''], 'the rank category is required'],
            'rank outside its category' => [['rank_category' => 'officer'], 'the rank is not in the chosen category'],
        ];
    }

    public function testACompanyFromAnotherCdcIsRefused(): void
    {
        $otherCdcId = $this->insertRow('organizational_units', [
            'unit_code' => 'REG-CDC-B',
            'unit_name' => 'Registration Other CDC',
            'unit_type' => 'cdc',
            'is_active' => 1,
        ]);
        $foreignCompanyId = $this->insertRow('organizational_units', [
            'parent_unit_id' => $otherCdcId,
            'unit_code'      => 'REG-CDC-B-A',
            'unit_name'      => 'Alpha Company (other CDC)',
            'unit_type'      => 'company',
            'is_active'      => 1,
        ]);

        $this->signUp(['company_unit_id' => $foreignCompanyId]);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'A reservist was filed under one CDC with a company of another.'
        );
    }

    /**
     * The client's sign-up lists CDC 1501-1505 (inserted by migration) and
     * the rank categories; each company option carries its CDC so the form
     * can show only the chosen CDC's companies.
     */
    public function testTheSignUpFormOffersTheCdcsTheirCompaniesAndTheRankCategories(): void
    {
        $page = $this->get('/register');

        foreach (['1501 CDC', '1502 CDC', '1503 CDC', '1504 CDC', '1505 CDC', 'Echo Company, Ready Reserve Infantry Battalion', 'ROTC Student', 'Officer', 'MS31', 'MS42'] as $text) {
            $page->assertSee($text);
        }

        $cdc1502 = $this->db->table('organizational_units')->where('unit_code', 'CDC-1502')->get()->getRowArray();

        $this->assertNotNull($cdc1502, 'The migration did not add 1502 CDC.');
        $this->assertStringContainsString('data-parent="' . $cdc1502['id'] . '"', (string) $page->getBody());
    }

    /**
     * Client revision: picking Master Sergeant tells the reservist the
     * promotion is processed at RCDG level, and the system then refuses to
     * open a promotion package for it.
     */
    public function testAMasterSergeantIsToldThePromotionIsAtRcdgLevelAndCannotApply(): void
    {
        $msgRankId = $this->insertRow('ranks', [
            'rank_code'     => 'MSG',
            'rank_name'     => 'Master Sergeant',
            'rank_category' => 'non-commissioned',
            'sort_order'    => 70,
            'is_active'     => 1,
        ]);

        $this->signUp(['current_rank_id' => $msgRankId]);

        $user      = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);
        $reservist = $this->db->table('reservists')->where('user_id', $user->id)->get()->getRowArray();

        $this->seeInDatabase('notifications', ['user_id' => $user->id, 'type' => 'rcdg_level']);

        // Shield signs the new account in on registration.
        $result = $this->post('/my/promotion/package');

        $result->assertSessionHas('error', 'Promotion for a Master Sergeant is processed at the RCDG level and cannot be applied for in PromoTrack.');

        $this->dontSeeInDatabase('promotion_packages', ['reservist_id' => $reservist['id']]);
    }

    public function testEveryNewAccountGetsAWelcomeNotification(): void
    {
        $this->signUp();

        $user = auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']);

        $this->seeInDatabase('notifications', ['user_id' => $user->id, 'type' => 'welcome']);
        $this->dontSeeInDatabase('notifications', ['user_id' => $user->id, 'type' => 'rcdg_level']);
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
