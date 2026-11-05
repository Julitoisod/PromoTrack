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
 * A reservist can start their own promotion package.
 *
 * The package used to be the unit S1 clerk's to create, so a reservist
 * who had just signed up saw "you have no open promotion package" with
 * no way forward and nothing to upload into — which defeats the point of
 * the reservist uploading their own documents. The client hit exactly
 * that on the live site.
 *
 * @internal
 */
final class ReservistOpensOwnPackageTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $unitId;
    private int $reservistId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->unitId = $this->insert('organizational_units', [
            'unit_code' => 'OWN-UNIT',
            'unit_name' => 'Own Package Test Unit',
            'is_active' => 1,
        ]);

        // No rank on file — the state a freshly self-registered reservist is
        // in, and the one the client was stuck in.
        $this->reservistId = $this->insert('reservists', [
            'reservist_number'       => 'RSV-OWN-1',
            'first_name'             => 'Own',
            'last_name'              => 'Packager',
            'organizational_unit_id' => $this->unitId,
            'status'                 => 'active',
        ]);

        $templateId = $this->insert('document_requirement_templates', [
            'template_name' => 'Own Package Template',
            'version'       => 1,
            'is_active'     => 1,
        ]);

        foreach (['A', 'B', 'C'] as $i => $code) {
            $this->insert('document_requirements', [
                'document_requirement_template_id' => $templateId,
                'document_code'                    => $code,
                'document_label'                   => 'Requirement ' . $code,
                'is_conditional'                   => 0,
                'allows_commander_override'        => 0,
                'display_order'                    => $i + 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        auth()->logout();
        session()->destroy();

        parent::tearDown();
    }

    private function insert(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function reservistUser(bool $link = true): User
    {
        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'own_reservist',
            'email'                  => 'own_reservist@promotrack.local',
            'password'               => 'OwnPackage!12345',
            'full_name'              => 'Own Packager',
            'organizational_unit_id' => $this->unitId,
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('reservist');

        if ($link) {
            $this->db->table('reservists')
                ->where('id', $this->reservistId)
                ->update(['user_id' => $user->id]);
        }

        return $user;
    }

    private function packageCount(): int
    {
        return $this->db->table('promotion_packages')
            ->where('reservist_id', $this->reservistId)
            ->countAllResults();
    }

    public function testTheEmptyPageOffersTheReservistAWayToStart(): void
    {
        $this->actingAs($this->reservistUser());

        $this->get('/my/promotion')->assertSee('Start my promotion package');
    }

    public function testAReservistCanOpenTheirOwnPackageAndGetsTheChecklist(): void
    {
        $this->actingAs($this->reservistUser());

        $this->post('/my/promotion/package');

        $this->assertSame(1, $this->packageCount(), 'No package was created.');

        $package = $this->db->table('promotion_packages')
            ->where('reservist_id', $this->reservistId)
            ->get()
            ->getRowArray();

        $this->assertSame('draft', $package['status']);

        // Having no rank must not leave them with an empty checklist: the
        // rank-agnostic template still supplies every item.
        $this->assertSame(
            3,
            $this->db->table('package_documents')->where('promotion_package_id', $package['id'])->countAllResults(),
            'The package was created with no documents to upload.'
        );
    }

    public function testPressingItTwiceDoesNotCreateASecondPackage(): void
    {
        $this->actingAs($this->reservistUser());

        $this->post('/my/promotion/package');
        $this->post('/my/promotion/package');

        $this->assertSame(1, $this->packageCount(), 'A second open package was created.');
    }

    public function testAnUnlinkedAccountCannotOpenAPackage(): void
    {
        $this->actingAs($this->reservistUser(false));

        $this->post('/my/promotion/package');

        $this->assertSame(0, $this->packageCount(), 'A package was opened for an account owning no record.');
    }

    public function testAReservistWithNoUnitIsToldWhyRatherThanFailing(): void
    {
        $this->db->table('reservists')
            ->where('id', $this->reservistId)
            ->update(['organizational_unit_id' => null]);

        $this->actingAs($this->reservistUser());

        $result = $this->post('/my/promotion/package');

        $this->assertSame(0, $this->packageCount());
        $this->assertTrue($result->isRedirect());
    }
}
