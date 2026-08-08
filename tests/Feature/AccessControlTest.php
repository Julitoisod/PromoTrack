<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;

/**
 * The IDOR / privilege-escalation suite the design's security checklist (§12)
 * asks for: "cross-unit and cross-role access explicitly tested".
 *
 * Two questions are asked of every protected surface:
 *  - can a unit-scoped clerk reach a record belonging to another unit?
 *  - can a role reach a screen or action that belongs to a different role?
 *
 * Each case drives a real request through the real route filters, so it fails
 * if a route is added without its permission filter, or if a filter stops
 * matching the URL shape it is attached to.
 *
 * @internal
 */
final class AccessControlTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $ownUnitId;
    private int $otherUnitId;
    private int $ownReservistId;
    private int $otherReservistId;

    /** @var array<string, User> group name => user */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        // csrf is exercised by CsrfGuardTest; these cases are about who may
        // reach what, so the token is not in the way.
        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->ownUnitId   = $this->insertRow('organizational_units', ['unit_code' => 'AC-OWN', 'unit_name' => 'Own Unit', 'is_active' => 1]);
        $this->otherUnitId = $this->insertRow('organizational_units', ['unit_code' => 'AC-OTHER', 'unit_name' => 'Other Unit', 'is_active' => 1]);

        $this->ownReservistId   = $this->insertReservist($this->ownUnitId, 'AC-OWN');
        $this->otherReservistId = $this->insertReservist($this->otherUnitId, 'AC-OTHER');
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

    private function insertReservist(int $unitId, string $suffix): int
    {
        return $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-' . $suffix,
            'first_name'             => 'Test',
            'last_name'              => 'Reservist',
            'date_of_birth'          => '1990-01-01',
            'date_of_enlistment'     => '2015-01-01',
            'organizational_unit_id' => $unitId,
            'status'                 => 'active',
        ]);
    }

    /**
     * Creates (once per test) an activated user in $group, scoped to a unit.
     */
    private function userIn(string $group, ?int $unitId = null): User
    {
        if (isset($this->users[$group])) {
            return $this->users[$group];
        }

        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'ac_' . $group,
            'email'                  => 'ac_' . $group . '@promotrack.local',
            'password'               => 'AccessPass!12345',
            'full_name'              => 'Access Test ' . $group,
            'organizational_unit_id' => $unitId ?? $this->ownUnitId,
        ]);

        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup($group);

        return $this->users[$group] = $user;
    }

    // ---------------------------------------------------------------- guests

    /**
     * @dataProvider protectedPathProvider
     */
    public function testAGuestCannotReachAnyProtectedScreen(string $path): void
    {
        $result = $this->get($path);

        $this->assertTrue(
            $result->isRedirect(),
            $path . ' was reachable without logging in.'
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function protectedPathProvider(): array
    {
        return [
            'dashboard'       => ['/'],
            'reservists'      => ['/reservists'],
            'packages'        => ['/packages'],
            'reference data'  => ['/admin/reference'],
            'promotion rules' => ['/admin/rules'],
            'audit logs'      => ['/admin/audit-logs'],
            'settings'        => ['/admin/settings'],
        ];
    }

    // ------------------------------------------------------------ cross-unit

    public function testClerkCannotViewAReservistFromAnotherUnit(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->get('/reservists/' . $this->otherReservistId)->assertStatus(403);
    }

    public function testClerkCannotOpenTheEditFormForAnotherUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        // This path ends in a verb, which the unit-access filter used to skip
        // entirely -- the case that fix exists for.
        $this->get('/reservists/' . $this->otherReservistId . '/edit')->assertStatus(403);
    }

    public function testClerkCannotUpdateAnotherUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->post('/reservists/' . $this->otherReservistId, [
            'reservist_number'   => 'RSV-AC-OTHER',
            'first_name'         => 'Hijacked',
            'last_name'          => 'Reservist',
            'date_of_birth'      => '1990-01-01',
            'date_of_enlistment' => '2015-01-01',
        ])->assertStatus(403);

        $this->dontSeeInDatabase('reservists', ['first_name' => 'Hijacked']);
    }

    public function testClerkCannotDeleteAnotherUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->post('/reservists/' . $this->otherReservistId . '/delete')->assertStatus(403);

        $row = $this->db->table('reservists')->select('deleted_at')
            ->where('id', $this->otherReservistId)->get()->getRowArray();

        $this->assertNull($row['deleted_at'], 'A clerk soft-deleted another unit\'s reservist.');
    }

    public function testClerkCannotRunAnAssessmentAgainstAnotherUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->post('/reservists/' . $this->otherReservistId . '/assessments')->assertStatus(403);
    }

    public function testClerkCannotOpenAPackageForAnotherUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->post('/reservists/' . $this->otherReservistId . '/packages')->assertStatus(403);

        $this->dontSeeInDatabase('promotion_packages', ['reservist_id' => $this->otherReservistId]);
    }

    public function testClerkCanStillReachTheirOwnUnitsReservist(): void
    {
        $this->actingAs($this->userIn('unit_s1_clerk', $this->ownUnitId));

        $this->get('/reservists/' . $this->ownReservistId)->assertOK();
    }

    // ------------------------------------------------------------ cross-role

    /**
     * @dataProvider forbiddenRoleScreenProvider
     */
    public function testARoleCannotReachAScreenBelongingToAnotherRole(string $group, string $path): void
    {
        $this->actingAs($this->userIn($group));

        $result = $this->get($path);

        $this->assertTrue(
            $result->isRedirect() || $result->response()->getStatusCode() === 403,
            $group . ' reached ' . $path . ', which belongs to another role.'
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function forbiddenRoleScreenProvider(): array
    {
        return [
            'clerk -> reference data'     => ['unit_s1_clerk', '/admin/reference'],
            'clerk -> promotion rules'    => ['unit_s1_clerk', '/admin/rules'],
            'clerk -> audit logs'         => ['unit_s1_clerk', '/admin/audit-logs'],
            'clerk -> settings'           => ['unit_s1_clerk', '/admin/settings'],
            'commander -> reference data' => ['commander_viewer', '/admin/reference'],
            'commander -> audit logs'     => ['commander_viewer', '/admin/audit-logs'],
            'cdc -> settings'             => ['cdc_administrator', '/admin/settings'],
            'rcdg -> promotion rules'     => ['rcdg_reviewer', '/admin/rules'],
            'records -> audit logs'       => ['records_officer', '/admin/audit-logs'],
        ];
    }

    /**
     * @dataProvider forbiddenRoleActionProvider
     */
    public function testARoleCannotPerformAnActionBelongingToAnotherRole(string $group, string $path): void
    {
        $this->actingAs($this->userIn($group));

        $result = $this->post($path);

        $this->assertTrue(
            $result->isRedirect() || $result->response()->getStatusCode() === 403,
            $group . ' was allowed to POST ' . $path . ', which belongs to another role.'
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function forbiddenRoleActionProvider(): array
    {
        return [
            'commander cannot create reservists' => ['commander_viewer', '/reservists'],
            'commander cannot validate at CDC'   => ['commander_viewer', '/cdc/packages/1/validate'],
            'clerk cannot validate at CDC'       => ['unit_s1_clerk', '/cdc/packages/1/validate'],
            'clerk cannot endorse at RCDG'       => ['unit_s1_clerk', '/rcdg/packages/1/endorse'],
            'clerk cannot record forwarding'     => ['unit_s1_clerk', '/records/packages/1/forward'],
            'clerk cannot record an order'       => ['unit_s1_clerk', '/records/packages/1/promotion-order'],
            'cdc cannot endorse at RCDG'         => ['cdc_administrator', '/rcdg/packages/1/endorse'],
            'records cannot validate at CDC'     => ['records_officer', '/cdc/packages/1/validate'],
            'clerk cannot edit reference data'   => ['unit_s1_clerk', '/admin/reference/units'],
            'clerk cannot version a rule'        => ['unit_s1_clerk', '/admin/rules/1/versions'],
            'clerk cannot take a backup'         => ['unit_s1_clerk', '/admin/backups'],
        ];
    }

    public function testSuperadminReachesTheAdministrationScreens(): void
    {
        $this->actingAs($this->userIn('superadmin'));

        $this->get('/admin/reference')->assertOK();
        $this->get('/admin/rules')->assertOK();
        $this->get('/admin/audit-logs')->assertOK();
        $this->get('/admin/settings')->assertOK();
    }
}
