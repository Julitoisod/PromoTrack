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
 * The reservist portal from the client revision of 2026-11-09: Dashboard,
 * My Profile, ROTC Progress, Notifications, Settings. Drives real requests
 * through the real route filters.
 *
 * @internal
 */
final class ReservistPortalTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $cdcId;
    private int $companyId;
    private int $reservistId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff($filtersConfig->globals['before'], ['csrf']));

        $this->cdcId     = $this->insertRow('organizational_units', ['unit_code' => 'PT-CDC', 'unit_name' => 'Portal CDC', 'unit_type' => 'cdc', 'is_active' => 1]);
        $this->companyId = $this->insertRow('organizational_units', [
            'parent_unit_id' => $this->cdcId, 'unit_code' => 'PT-CDC-A', 'unit_name' => 'Alpha Company, Ready Reserve Infantry Battalion',
            'unit_type' => 'company', 'is_active' => 1,
        ]);

        $this->reservistId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-PORTAL',
            'afp_serial_number'      => 'PT-0001',
            'first_name'             => 'Juan',
            'middle_name'            => 'Perez',
            'last_name'              => 'Dela Cruz',
            'organizational_unit_id' => $this->cdcId,
            'company_unit_id'        => $this->companyId,
            'is_student'             => 1,
            'student_year_level'     => '2nd Year 2nd Sem',
            'educational_attainment' => 'undergraduate',
            'contact_number'         => '09171234567',
            'address'                => 'Old Address',
            'sex'                    => 'male',
            'date_of_birth'          => '2004-03-15',
            'status'                 => 'active',
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

    private function makeUser(string $group, string $name): User
    {
        $users = auth()->getProvider();
        $user  = new User([
            'username'               => 'pt_' . $name,
            'email'                  => 'pt_' . $name . '@promotrack.local',
            'password'               => 'PortalPass!12345',
            'organizational_unit_id' => $this->cdcId,
        ]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup($group);

        return $user;
    }

    private function signInAsTheReservist(): User
    {
        $user = $this->makeUser('reservist', 'student');
        $this->db->table('reservists')->where('id', $this->reservistId)->update(['user_id' => $user->id]);
        $this->actingAs($user);

        return $user;
    }

    public function testAReservistLandsOnTheirDashboard(): void
    {
        $this->signInAsTheReservist();

        $this->get('/')->assertRedirectTo(site_url('my/dashboard'));
    }

    public function testTheStudentDashboardFollowsTheMockUpWithoutQuickAccessOrNotifications(): void
    {
        $this->signInAsTheReservist();

        $page = $this->get('/my/dashboard');

        $page->assertOK();
        $page->assertSee('Welcome, Juan Dela Cruz!');
        $page->assertSee('Student Reservist Dashboard');
        $page->assertSee('Portal CDC • Alpha Company');
        $page->assertSee('My ROTC Progress');
        $page->assertSee('My Information');
        $page->assertSee('Your Current Status');
        $page->assertSee('E-4');
        // The client asked for these two cards to go: the side menu has both.
        $page->assertDontSee('Quick Access');
        $page->assertDontSee('View All');
    }

    public function testAReservistWhoIsNotAStudentGetsTheGeneralDashboard(): void
    {
        $this->db->table('reservists')->where('id', $this->reservistId)->update(['is_student' => 0, 'student_year_level' => null]);
        $this->signInAsTheReservist();

        $page = $this->get('/my/dashboard');

        $page->assertOK();
        $page->assertSee('Reservist Dashboard');
        $page->assertDontSee('Student Reservist Dashboard');
        $page->assertDontSee('My ROTC Progress');
    }

    /**
     * @dataProvider portalPageProvider
     */
    public function testEveryPortalPageOpensForTheReservist(string $path, string $expect): void
    {
        $this->signInAsTheReservist();

        $page = $this->get($path);

        $page->assertOK();
        $page->assertSee($expect);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function portalPageProvider(): array
    {
        return [
            'dashboard'     => ['/my/dashboard', 'Dashboard'],
            'profile'       => ['/my/profile', 'Personal Details'],
            'rotc'          => ['/my/rotc', 'Enrolled 2nd Sem (MS32)'],
            'documents'     => ['/my/promotion', 'My Documents'],
            'notifications' => ['/my/notifications', 'No notifications yet.'],
            'settings'      => ['/my/settings', 'Change Password'],
        ];
    }

    /**
     * @dataProvider portalPageProvider
     */
    public function testStaffCannotOpenThePortal(string $path): void
    {
        $this->actingAs($this->makeUser('unit_s1_clerk', 'clerk'));

        $result = $this->get($path);

        $this->assertTrue(
            $result->isRedirect() || $result->response()->getStatusCode() === 403,
            'A clerk reached ' . $path . ', which is the reservist\'s own screen.'
        );
    }

    /**
     * The ROTC save route uses a regex placeholder; this proves the group's
     * session + permission filters still run on it.
     */
    public function testOnlyTheReservistCanPostAnRotcStage(): void
    {
        $guest = $this->post('/my/rotc/E3', ['training_result' => 'complete']);
        $this->assertTrue($guest->isRedirect());

        $this->actingAs($this->makeUser('unit_s1_clerk', 'clerk'));
        $clerk = $this->post('/my/rotc/E3', ['training_result' => 'complete']);
        $this->assertTrue($clerk->isRedirect() || $clerk->response()->getStatusCode() === 403);

        $this->dontSeeInDatabase('rotc_progress', ['stage' => 'E3']);
    }

    public function testAGuestIsSentToLogin(): void
    {
        $this->assertTrue($this->get('/my/dashboard')->isRedirect());
    }

    public function testTheReservistCanUpdateTheirOwnDetails(): void
    {
        $this->signInAsTheReservist();

        $this->post('/my/profile', [
            'contact_number'         => '09998887777',
            'address'                => 'New Address, Butuan City',
            'sex'                    => 'male',
            'date_of_birth'          => '2004-03-15',
            'educational_attainment' => 'undergraduate',
            'is_student'             => '1',
            'student_year_level'     => '3rd Year 1st Sem',
            'company_unit_id'        => $this->companyId,
        ])->assertRedirectTo(site_url('my/profile'));

        $this->seeInDatabase('reservists', [
            'id'                 => $this->reservistId,
            'contact_number'     => '09998887777',
            'address'            => 'New Address, Butuan City',
            'student_year_level' => '3rd Year 1st Sem',
        ]);
        $this->seeInDatabase('audit_logs', ['action' => 'reservist.self_updated', 'entity_id' => $this->reservistId]);
    }

    public function testTheReservistCannotMoveIntoACompanyOfAnotherCdc(): void
    {
        $otherCdc     = $this->insertRow('organizational_units', ['unit_code' => 'PT-CDC2', 'unit_name' => 'Other CDC', 'unit_type' => 'cdc', 'is_active' => 1]);
        $otherCompany = $this->insertRow('organizational_units', [
            'parent_unit_id' => $otherCdc, 'unit_code' => 'PT-CDC2-A', 'unit_name' => 'Other Alpha', 'unit_type' => 'company', 'is_active' => 1,
        ]);
        $this->signInAsTheReservist();

        $this->post('/my/profile', [
            'contact_number'         => '09998887777',
            'address'                => 'New Address',
            'sex'                    => 'male',
            'date_of_birth'          => '2004-03-15',
            'educational_attainment' => 'undergraduate',
            'is_student'             => '1',
            'student_year_level'     => '2nd Year 2nd Sem',
            'company_unit_id'        => $otherCompany,
        ]);

        $this->seeInDatabase('reservists', ['id' => $this->reservistId, 'company_unit_id' => $this->companyId, 'address' => 'Old Address']);
    }

    public function testTheProfileFormCannotChangeTheServiceRecord(): void
    {
        $this->signInAsTheReservist();

        $this->post('/my/profile', [
            'contact_number'         => '09998887777',
            'address'                => 'New Address',
            'sex'                    => 'male',
            'date_of_birth'          => '2004-03-15',
            'educational_attainment' => 'undergraduate',
            'is_student'             => '1',
            'student_year_level'     => '2nd Year 2nd Sem',
            'company_unit_id'        => $this->companyId,
            // Not on the form; must be ignored if posted anyway.
            'afp_serial_number'      => 'HIJACK',
            'organizational_unit_id' => 999,
            'current_rank_id'        => 1,
        ]);

        $this->seeInDatabase('reservists', ['id' => $this->reservistId, 'afp_serial_number' => 'PT-0001', 'organizational_unit_id' => $this->cdcId, 'current_rank_id' => null]);
    }

    public function testAStageIsSavedThroughTheRotcPage(): void
    {
        $this->signInAsTheReservist();

        $this->post('/my/rotc/E3', ['training_result' => 'complete'])->assertSessionHas('success', 'E-3 saved.');

        $this->seeInDatabase('rotc_progress', ['reservist_id' => $this->reservistId, 'stage' => 'E3', 'training_result' => 'complete']);
        $this->seeInDatabase('audit_logs', ['action' => 'rotc_progress.saved', 'entity_id' => $this->reservistId]);
    }

    public function testALockedStageIsRefused(): void
    {
        $this->signInAsTheReservist();

        $this->post('/my/rotc/E6', ['classification' => 'ready_reserve'])->assertSessionHas('error');

        $this->dontSeeInDatabase('rotc_progress', ['reservist_id' => $this->reservistId, 'stage' => 'E6']);
    }

    public function testThePasswordChangeChecksTheCurrentPassword(): void
    {
        $user = $this->signInAsTheReservist();

        $this->post('/my/settings/password', [
            'current_password' => 'NotMyPassword!1',
            'password'         => 'BrandNewSecret!2026',
            'password_confirm' => 'BrandNewSecret!2026',
        ])->assertSessionHas('error');

        $this->assertTrue(auth('session')->check(['email' => $user->email, 'password' => 'PortalPass!12345'])->isOK());

        $this->post('/my/settings/password', [
            'current_password' => 'PortalPass!12345',
            'password'         => 'BrandNewSecret!2026',
            'password_confirm' => 'BrandNewSecret!2026',
        ])->assertSessionHas('success', 'Your password has been changed.');

        $this->assertTrue(auth('session')->check(['email' => $user->email, 'password' => 'BrandNewSecret!2026'])->isOK());
    }

    public function testNotificationsAreListedAndCanBeMarkedRead(): void
    {
        $user = $this->signInAsTheReservist();
        service('notificationService')->notify((int) $user->id, 'welcome', 'Welcome to PromoTrack', 'Hello');

        $this->get('/my/notifications')->assertSee('Welcome to PromoTrack');
        $this->post('/my/notifications/read-all');

        $this->dontSeeInDatabase('notifications', ['user_id' => $user->id, 'is_read' => 0]);
    }

    public function testAReservistCannotFetchAnotherRecordsPhotoThroughTheStaffRoute(): void
    {
        $this->signInAsTheReservist();

        $result = $this->get('/reservists/' . $this->reservistId . '/photo');

        $this->assertTrue($result->isRedirect() || $result->response()->getStatusCode() === 403);
    }

    public function testStaffSeeTheStudentsRotcProgressOnTheProfile(): void
    {
        (new \App\Models\RotcProgressModel())->saveStage($this->reservistId, 'E3', ['training_result' => 'complete', 'checks' => []]);

        $this->actingAs($this->makeUser('unit_s1_clerk', 'clerk'));

        $page = $this->get('/reservists/' . $this->reservistId);

        $page->assertOK();
        $page->assertSee('ROTC Progress');
        $page->assertSee('reported by the student');
    }

    public function testAnAdministratorCanFileACompanyUnderACdc(): void
    {
        $this->actingAs($this->makeUser('superadmin', 'admin'));

        $this->post('/admin/reference/units', [
            'unit_code'      => 'PT-CDC-F',
            'unit_name'      => 'Foxtrot Company, Ready Reserve Infantry Battalion',
            'unit_type'      => 'company',
            'parent_unit_id' => $this->cdcId,
        ]);

        $this->seeInDatabase('organizational_units', ['unit_code' => 'PT-CDC-F', 'parent_unit_id' => $this->cdcId, 'unit_type' => 'company']);
    }
}
