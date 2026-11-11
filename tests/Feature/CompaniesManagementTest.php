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
 * The Companies screen (client: the CDC or admin side can add and edit the
 * companies in the sign-up form's "Select Company").
 *
 * @internal
 */
final class CompaniesManagementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $cdcId;
    private int $otherCdcId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff($filtersConfig->globals['before'], ['csrf']));

        $this->cdcId      = $this->insertRow('organizational_units', ['unit_code' => 'CM-CDC1', 'unit_name' => 'Test CDC One', 'unit_type' => 'cdc', 'is_active' => 1]);
        $this->otherCdcId = $this->insertRow('organizational_units', ['unit_code' => 'CM-CDC2', 'unit_name' => 'Test CDC Two', 'unit_type' => 'cdc', 'is_active' => 1]);
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

    private function signIn(string $group): User
    {
        $users = auth()->getProvider();
        $user  = new User([
            'username'               => 'cm_' . $group,
            'email'                  => 'cm_' . $group . '@promotrack.local',
            'password'               => 'CompanyPass!12345',
            'organizational_unit_id' => $this->cdcId,
        ]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup($group);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @dataProvider managerProvider
     */
    public function testTheCdcAndAdminSidesCanAddACompanyThatThenAppearsAtSignUp(string $group): void
    {
        $this->signIn($group);

        $page = $this->get('/admin/companies');
        $page->assertOK();
        $page->assertSee('Add Company');

        $this->post('/admin/companies', ['cdc_id' => $this->cdcId, 'company_name' => 'Foxtrot Company, Ready Reserve Infantry Battalion']);

        $this->seeInDatabase('organizational_units', [
            'unit_name'      => 'Foxtrot Company, Ready Reserve Infantry Battalion',
            'unit_type'      => 'company',
            'parent_unit_id' => $this->cdcId,
            'unit_code'      => 'CM-CDC1-F',
            'is_active'      => 1,
        ]);
        $this->seeInDatabase('audit_logs', ['action' => 'company.created']);

        auth()->logout();
        session()->destroy();

        $this->get('/register')->assertSee('Foxtrot Company, Ready Reserve Infantry Battalion');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function managerProvider(): array
    {
        return [
            'CDC administrator'    => ['cdc_administrator'],
            'system administrator' => ['system_administrator'],
            'superadmin'           => ['superadmin'],
        ];
    }

    /**
     * @dataProvider outsiderProvider
     */
    public function testOtherRolesCannotManageCompanies(string $group): void
    {
        $this->signIn($group);

        $page = $this->get('/admin/companies');
        $this->assertTrue($page->isRedirect() || $page->response()->getStatusCode() === 403);

        $this->post('/admin/companies', ['cdc_id' => $this->cdcId, 'company_name' => 'Sneaky Company']);
        $this->dontSeeInDatabase('organizational_units', ['unit_name' => 'Sneaky Company']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function outsiderProvider(): array
    {
        return [
            'clerk'     => ['unit_s1_clerk'],
            'commander' => ['commander_viewer'],
            'reservist' => ['reservist'],
        ];
    }

    public function testADuplicateNameInTheSameCdcIsRefusedButAllowedInAnother(): void
    {
        $this->signIn('cdc_administrator');

        $this->post('/admin/companies', ['cdc_id' => $this->cdcId, 'company_name' => 'Golf Company']);
        $this->post('/admin/companies', ['cdc_id' => $this->cdcId, 'company_name' => 'golf company'])->assertSessionHas('error');
        $this->post('/admin/companies', ['cdc_id' => $this->otherCdcId, 'company_name' => 'Golf Company']);

        $this->assertSame(1, $this->db->table('organizational_units')->where('parent_unit_id', $this->cdcId)->where('unit_name', 'Golf Company')->countAllResults());
        $this->assertSame(1, $this->db->table('organizational_units')->where('parent_unit_id', $this->otherCdcId)->where('unit_name', 'Golf Company')->countAllResults());
    }

    public function testACompanyCanBeRenamedAndHidden(): void
    {
        $companyId = $this->insertRow('organizational_units', ['parent_unit_id' => $this->cdcId, 'unit_code' => 'CM-CDC1-H', 'unit_name' => 'Hotel Coy', 'unit_type' => 'company', 'is_active' => 1]);
        $this->signIn('cdc_administrator');

        $this->post('/admin/companies/' . $companyId, ['cdc_id' => $this->cdcId, 'company_name' => 'Hotel Company']);
        $this->seeInDatabase('organizational_units', ['id' => $companyId, 'unit_name' => 'Hotel Company']);

        $this->post('/admin/companies/' . $companyId . '/toggle');
        $this->seeInDatabase('organizational_units', ['id' => $companyId, 'is_active' => 0]);

        auth()->logout();
        session()->destroy();

        $this->get('/register')->assertDontSee('Hotel Company');
    }

    public function testACompanyWithReservistsStaysUnderItsCdc(): void
    {
        $companyId = $this->insertRow('organizational_units', ['parent_unit_id' => $this->cdcId, 'unit_code' => 'CM-CDC1-I', 'unit_name' => 'India Company', 'unit_type' => 'company', 'is_active' => 1]);
        $this->insertRow('reservists', [
            'reservist_number' => 'RSV-CM-1', 'first_name' => 'Test', 'last_name' => 'Member',
            'organizational_unit_id' => $this->cdcId, 'company_unit_id' => $companyId, 'status' => 'active',
        ]);
        $this->signIn('superadmin');

        $this->post('/admin/companies/' . $companyId, ['cdc_id' => $this->otherCdcId, 'company_name' => 'India Company'])->assertSessionHas('error');

        $this->seeInDatabase('organizational_units', ['id' => $companyId, 'parent_unit_id' => $this->cdcId]);
    }

    public function testOnlyACdcCanBeTheParent(): void
    {
        $notCdc = $this->insertRow('organizational_units', ['unit_code' => 'CM-HQ', 'unit_name' => 'Some HQ', 'unit_type' => 'headquarters', 'is_active' => 1]);
        $this->signIn('superadmin');

        $this->post('/admin/companies', ['cdc_id' => $notCdc, 'company_name' => 'Juliet Company'])->assertSessionHas('error');

        $this->dontSeeInDatabase('organizational_units', ['unit_name' => 'Juliet Company']);
    }
}
