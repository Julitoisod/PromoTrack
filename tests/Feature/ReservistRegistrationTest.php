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

    public function testSignUpRequiresAUnitAndACompany(): void
    {
        $this->signUp(['company_unit_id' => '']);

        $this->assertNull(
            auth()->getProvider()->findByCredentials(['email' => 'juan@promotrack.local']),
            'An account was created without a company.'
        );
    }
}
