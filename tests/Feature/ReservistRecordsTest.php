<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;

/**
 * Covers ReservistRecordsController: the add/remove paths for the four service
 * record sets that EligibilityService reads.
 *
 * The important cases are not the happy path but the two ways a clerk could
 * reach a record that is not theirs: another unit's reservist, and their own
 * reservist id paired with someone else's record id.
 *
 * @internal
 */
final class ReservistRecordsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $ownUnitId;
    private int $otherUnitId;
    private int $ownReservistId;
    private int $otherReservistId;
    private int $trainingTypeId;
    private int $rankId;
    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        // csrf is covered by CsrfGuardTest; disabled here so these cases can
        // focus on authorization and persistence.
        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->ownUnitId   = $this->insertRow('organizational_units', ['unit_code' => 'REC-OWN', 'unit_name' => 'Own Unit', 'is_active' => 1]);
        $this->otherUnitId = $this->insertRow('organizational_units', ['unit_code' => 'REC-OTHER', 'unit_name' => 'Other Unit', 'is_active' => 1]);

        $this->trainingTypeId = $this->insertRow('training_types', [
            'training_code' => 'MLES-1',
            'training_name' => 'Basic Officer Course',
            'is_mles'       => 1,
            'is_active'     => 1,
        ]);

        $this->rankId = $this->insertRow('ranks', [
            'rank_code'     => 'PVT',
            'rank_name'     => 'Private',
            'rank_category' => 'enlisted',
            'sort_order'    => 1,
            'is_active'     => 1,
        ]);

        $this->ownReservistId   = $this->insertReservist($this->ownUnitId, 'OWN');
        $this->otherReservistId = $this->insertReservist($this->otherUnitId, 'OTHER');

        $this->clerk = $this->createUnitS1Clerk($this->ownUnitId);
    }

    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function insertReservist(int $unitId, string $suffix): int
    {
        return $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-REC-' . $suffix,
            'first_name'             => 'Test',
            'last_name'              => 'Reservist',
            'date_of_birth'          => '1990-01-01',
            'date_of_enlistment'     => '2015-01-01',
            'organizational_unit_id' => $unitId,
            'status'                 => 'active',
        ]);
    }

    private function createUnitS1Clerk(int $unitId): User
    {
        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'recclerk',
            'email'                  => 'recclerk@promotrack.local',
            'password'               => 'ClerkPass!12345',
            'full_name'              => 'Records Test Clerk',
            'organizational_unit_id' => $unitId,
        ]);

        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('unit_s1_clerk');

        return $user;
    }

    public function testClerkCanRecordATrainingForTheirOwnReservist(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->post('/reservists/' . $this->ownReservistId . '/records/trainings', [
            'training_type_id' => $this->trainingTypeId,
            'training_title'   => 'Basic Officer Course 2024',
            'date_completed'   => '2024-06-30',
        ]);

        $result->assertRedirectTo('/reservists/' . $this->ownReservistId);

        $this->seeInDatabase('reservist_trainings', [
            'reservist_id'     => $this->ownReservistId,
            'training_type_id' => $this->trainingTypeId,
            'training_title'   => 'Basic Officer Course 2024',
        ]);
    }

    public function testClerkCanRecordAFitnessTestEligibilityAndRankHistory(): void
    {
        $this->actingAs($this->clerk);

        $this->post('/reservists/' . $this->ownReservistId . '/records/fitness-tests', [
            'test_date' => '2024-05-01',
            'result'    => 'pass',
            'score'     => '92.50',
        ]);
        $this->seeInDatabase('physical_fitness_tests', [
            'reservist_id' => $this->ownReservistId,
            'result'       => 'pass',
        ]);

        $this->post('/reservists/' . $this->ownReservistId . '/records/eligibilities', [
            'eligibility_title' => 'CSC Professional',
            'date_obtained'     => '2020-03-15',
        ]);
        $this->seeInDatabase('professional_eligibilities', [
            'reservist_id'      => $this->ownReservistId,
            'eligibility_title' => 'CSC Professional',
        ]);

        $this->post('/reservists/' . $this->ownReservistId . '/records/rank-history', [
            'rank_id'         => $this->rankId,
            'effective_date'  => '2019-01-01',
            'order_reference' => 'GO 123 s.2019',
        ]);
        $this->seeInDatabase('reservist_rank_histories', [
            'reservist_id'    => $this->ownReservistId,
            'order_reference' => 'GO 123 s.2019',
        ]);
    }

    public function testInvalidTrainingIsRejectedWithoutWriting(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->post('/reservists/' . $this->ownReservistId . '/records/trainings', [
            'training_type_id' => '',
            'training_title'   => 'No type selected',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');

        $this->dontSeeInDatabase('reservist_trainings', ['training_title' => 'No type selected']);
    }

    public function testClerkCannotAddARecordToAnotherUnitsReservist(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->post('/reservists/' . $this->otherReservistId . '/records/fitness-tests', [
            'test_date' => '2024-05-01',
            'result'    => 'pass',
        ]);

        $result->assertStatus(403);

        $this->dontSeeInDatabase('physical_fitness_tests', ['reservist_id' => $this->otherReservistId]);
    }

    public function testARecordCannotBeDeletedThroughADifferentReservist(): void
    {
        $foreignRecordId = $this->insertRow('physical_fitness_tests', [
            'reservist_id' => $this->otherReservistId,
            'test_date'    => '2024-01-01',
            'result'       => 'pass',
        ]);

        $this->actingAs($this->clerk);

        // Own (permitted) reservist id in the path, someone else's record id.
        try {
            $this->post(
                '/reservists/' . $this->ownReservistId . '/records/fitness-tests/' . $foreignRecordId . '/delete'
            );
            $this->fail('Deleting another reservist\'s record was allowed.');
        } catch (PageNotFoundException $e) {
            $this->assertStringContainsString('does not belong', $e->getMessage());
        }

        $this->seeInDatabase('physical_fitness_tests', ['id' => $foreignRecordId]);
    }

    public function testClerkCanRemoveARecordFromTheirOwnReservist(): void
    {
        $recordId = $this->insertRow('physical_fitness_tests', [
            'reservist_id' => $this->ownReservistId,
            'test_date'    => '2024-01-01',
            'result'       => 'fail',
        ]);

        $this->actingAs($this->clerk);

        $result = $this->post(
            '/reservists/' . $this->ownReservistId . '/records/fitness-tests/' . $recordId . '/delete'
        );

        $result->assertRedirectTo('/reservists/' . $this->ownReservistId);

        $this->dontSeeInDatabase('physical_fitness_tests', ['id' => $recordId]);
    }

    public function testAnUnknownRecordTypeIsRefused(): void
    {
        $this->actingAs($this->clerk);

        $this->expectException(PageNotFoundException::class);

        $this->post('/reservists/' . $this->ownReservistId . '/records/salaries', ['amount' => 1]);
    }
}
