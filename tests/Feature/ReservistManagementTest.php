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
 * End-to-end CRUD coverage for `ReservistsController`, exercised through
 * real HTTP-style requests against the actual routes, filters, model
 * validation, and database -- not through direct method calls.
 *
 * Verifies:
 *  - an authenticated, permitted unit_s1_clerk can create/view/update/
 *    soft-delete a reservist scoped to their own organizational unit,
 *  - model-level validation (duplicate reservist_number) blocks writes,
 *  - the `unit-access` filter blocks cross-unit record access, and
 *  - the `session` filter blocks anonymous/guest access entirely.
 *
 * @internal
 */
final class ReservistManagementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    /**
     * Migrate every namespace (App + Shield), not just Tests\Support.
     */
    protected $namespace = null;

    private int $ownUnitId;
    private int $otherUnitId;
    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        // The `csrf` global filter is real application security and is
        // covered by CSRFProtectionTest; it is disabled here only so this
        // test can focus purely on authorization/CRUD/validation behavior
        // without hand-rolling a token for every mutating request.
        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->ownUnitId = $this->insertRow('organizational_units', [
            'unit_code' => 'UNIT-OWN',
            'unit_name' => 'Own Unit',
            'is_active' => 1,
        ]);

        $this->otherUnitId = $this->insertRow('organizational_units', [
            'unit_code' => 'UNIT-OTHER',
            'unit_name' => 'Other Unit',
            'is_active' => 1,
        ]);

        $this->clerk = $this->createUnitS1Clerk($this->ownUnitId);
    }

    /**
     * Inserts a row via the query builder and returns its auto-increment
     * id via `insertID()`. Deliberately bypasses `hasInDatabase()`: that
     * helper returns the boolean result of `insert()` (not the id), and
     * its automatic cleanup deletes rows in insertion order, which can
     * violate foreign keys. Since `$refresh = true` already fully drops
     * and recreates the schema before every test, per-row cleanup here
     * is unnecessary anyway.
     */
    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Creates and activates a Shield user in the `unit_s1_clerk` group,
     * scoped to the given organizational unit -- mirroring the pattern
     * used by `AdminUserSeeder`.
     */
    private function createUnitS1Clerk(int $organizationalUnitId): User
    {
        static $sequence = 0;
        $sequence++;

        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'clerk' . $sequence,
            'email'                  => 'clerk' . $sequence . '@promotrack.local',
            'password'               => 'ClerkPass!12345',
            'full_name'              => 'Test Unit Clerk',
            'organizational_unit_id' => $organizationalUnitId,
        ]);

        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('unit_s1_clerk');

        return $user;
    }

    /**
     * Inserts a reservist row directly and returns its id.
     */
    private function createReservistRow(int $organizationalUnitId, string $numberSuffix): int
    {
        return $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-' . $numberSuffix,
            'first_name'             => 'Maria',
            'last_name'              => 'Santos',
            'date_of_birth'          => '1990-01-01',
            'date_of_enlistment'     => '2015-01-01',
            'organizational_unit_id' => $organizationalUnitId,
            'status'                 => 'active',
        ]);
    }

    public function testGuestIsRedirectedAwayFromReservistsIndex(): void
    {
        $result = $this->get('/reservists');

        $result->assertRedirect();
    }

    public function testAuthenticatedClerkCanCreateAReservistInTheirOwnUnit(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->post('/reservists', [
            'reservist_number'   => 'RSV-100',
            'first_name'         => 'Juan',
            'last_name'          => 'Dela Cruz',
            'date_of_birth'      => '1990-01-01',
            'date_of_enlistment' => '2015-01-01',
        ]);

        $result->assertRedirectTo('/reservists');

        $this->seeInDatabase('reservists', [
            'reservist_number'       => 'RSV-100',
            'first_name'             => 'Juan',
            'organizational_unit_id' => $this->ownUnitId,
        ]);
    }

    public function testCreationForcesTheClerksOwnUnitRegardlessOfSubmittedValue(): void
    {
        $this->actingAs($this->clerk);

        $this->post('/reservists', [
            'reservist_number'       => 'RSV-101',
            'first_name'             => 'Pedro',
            'last_name'              => 'Reyes',
            'date_of_birth'          => '1990-01-01',
            'date_of_enlistment'     => '2015-01-01',
            'organizational_unit_id' => $this->otherUnitId,
        ]);

        $this->seeInDatabase('reservists', [
            'reservist_number'       => 'RSV-101',
            'organizational_unit_id' => $this->ownUnitId,
        ]);

        $this->dontSeeInDatabase('reservists', [
            'reservist_number'       => 'RSV-101',
            'organizational_unit_id' => $this->otherUnitId,
        ]);
    }

    public function testDuplicateReservistNumberIsRejectedByModelValidation(): void
    {
        $this->createReservistRow($this->ownUnitId, '102');

        $this->actingAs($this->clerk);

        $result = $this->post('/reservists', [
            'reservist_number'   => 'RSV-102',
            'first_name'         => 'Ana',
            'last_name'          => 'Lopez',
            'date_of_birth'      => '1990-01-01',
            'date_of_enlistment' => '2015-01-01',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');

        $rows = $this->db->table('reservists')
            ->where('reservist_number', 'RSV-102')
            ->countAllResults();

        $this->assertSame(1, $rows);
    }

    public function testClerkCanUpdateAReservistInTheirOwnUnit(): void
    {
        $reservistId = $this->createReservistRow($this->ownUnitId, '103');

        $this->actingAs($this->clerk);

        $result = $this->post('/reservists/' . $reservistId, [
            'reservist_number'   => 'RSV-103',
            'first_name'         => 'Maria',
            'last_name'          => 'Cruz-Santos',
            'date_of_birth'      => '1990-01-01',
            'date_of_enlistment' => '2015-01-01',
        ]);

        $result->assertRedirectTo('/reservists/' . $reservistId);

        $this->seeInDatabase('reservists', [
            'id'        => $reservistId,
            'last_name' => 'Cruz-Santos',
        ]);
    }

    public function testClerkCannotAccessAReservistFromAnotherUnit(): void
    {
        $reservistId = $this->createReservistRow($this->otherUnitId, '104');

        $this->actingAs($this->clerk);

        $result = $this->get('/reservists/' . $reservistId);

        $result->assertStatus(403);
    }

    public function testClerkCanSoftDeleteAReservistInTheirOwnUnit(): void
    {
        $reservistId = $this->createReservistRow($this->ownUnitId, '105');

        $this->actingAs($this->clerk);

        $result = $this->post('/reservists/' . $reservistId . '/delete');

        $result->assertRedirectTo('/reservists');

        $row = $this->db->table('reservists')
            ->select('deleted_at')
            ->where('id', $reservistId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row['deleted_at']);
    }
}
