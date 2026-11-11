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
 * The admin panel from the client brief of 2026-11-10: dashboard counts,
 * semester folders with COR verification, registrations, regular
 * reservists, and the Standby / Ready Reserve folders.
 *
 * @internal
 */
final class AdminPanelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $cdcId;
    private int $companyId;
    private int $studentId;
    private int $regularId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff($filtersConfig->globals['before'], ['csrf']));

        $this->cdcId     = $this->insertRow('organizational_units', ['unit_code' => 'AP-CDC', 'unit_name' => 'Panel CDC', 'unit_type' => 'cdc', 'is_active' => 1]);
        $this->companyId = $this->insertRow('organizational_units', ['parent_unit_id' => $this->cdcId, 'unit_code' => 'AP-CDC-A', 'unit_name' => 'Alpha Company', 'unit_type' => 'company', 'is_active' => 1]);

        $this->studentId = $this->insertRow('reservists', [
            'reservist_number' => 'RSV-AP-S', 'afp_serial_number' => 'AP-STUDENT', 'first_name' => 'Stella', 'last_name' => 'Student',
            'organizational_unit_id' => $this->cdcId, 'company_unit_id' => $this->companyId,
            'is_student' => 1, 'educational_attainment' => 'undergraduate', 'student_year_level' => '2nd Year 1st Sem', 'status' => 'active',
        ]);
        $this->regularId = $this->insertRow('reservists', [
            'reservist_number' => 'RSV-AP-R', 'afp_serial_number' => 'AP-REGULAR', 'first_name' => 'Rex', 'last_name' => 'Regular',
            'organizational_unit_id' => $this->cdcId, 'company_unit_id' => $this->companyId,
            'is_student' => 0, 'educational_attainment' => 'bachelor', 'status' => 'active',
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
            'username' => 'ap_' . $name, 'email' => 'ap_' . $name . '@promotrack.local',
            'password' => 'PanelPass!12345', 'organizational_unit_id' => $this->cdcId,
        ]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup($group);

        return $user;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function pageProvider(): array
    {
        return [
            'enrollment folders'  => ['/admin/enrollment', 'Student Reservist Enrollment'],
            'MS31 folder'         => ['/admin/enrollment/MS31', 'MS31 Enrolled Students'],
            'registrations'       => ['/admin/registrations', 'Registrations'],
            'regular reservists'  => ['/admin/regular-reservists', 'Regular Reservists'],
            'classification'      => ['/admin/classification', 'Reservist Classification'],
            'ready reserve folder' => ['/admin/classification/ready_reserve', 'Ready Reserve Personnel'],
        ];
    }

    /**
     * @dataProvider pageProvider
     */
    public function testACdcAdministratorOpensEveryAdminPage(string $path, string $expect): void
    {
        $this->actingAs($this->makeUser('cdc_administrator', 'cdc'));

        $page = $this->get($path);
        $page->assertOK();
        $page->assertSee($expect);
    }

    /**
     * @dataProvider pageProvider
     */
    public function testAClerkCannotOpenTheAdminPages(string $path): void
    {
        $this->actingAs($this->makeUser('unit_s1_clerk', 'clerk'));

        $result = $this->get($path);
        $this->assertTrue($result->isRedirect() || $result->response()->getStatusCode() === 403, 'A clerk reached ' . $path);
    }

    public function testTheAdminDashboardShowsTheCountsAndFolders(): void
    {
        $this->actingAs($this->makeUser('system_administrator', 'sysadmin'));

        $page = $this->get('/');
        $page->assertOK();
        $page->assertSee('Welcome, Administrator!');
        $page->assertSee('AFP Reserve Force');
        foreach (['MS1', 'MS2', 'MS31', 'MS32', 'MS41', 'MS42', 'Standby', 'Ready Reserve', 'Total Student Reservists', 'Total Regular Reservists'] as $text) {
            $page->assertSee($text);
        }
    }

    public function testAClerkKeepsTheOrdinaryDashboard(): void
    {
        $this->actingAs($this->makeUser('unit_s1_clerk', 'clerk'));

        $this->get('/')->assertDontSee('Welcome, Administrator!');
    }

    public function testStudentsLandInTheirSemesterFolderAndRegularsDoNot(): void
    {
        $counts = (new \App\Models\ReservistModel())->adminCounts();
        $this->assertSame(1, $counts['students']);
        $this->assertGreaterThanOrEqual(1, $counts['regular']);

        $folders = \App\Controllers\Admin\EnrollmentController::folders();
        $this->assertSame(1, $folders['MS31']['count']);
        $this->assertSame(0, $folders['MS32']['count']);

        $this->actingAs($this->makeUser('cdc_administrator', 'cdc'));
        $page = $this->get('/admin/enrollment/MS31');
        $page->assertSee('Student, Stella');
        $page->assertDontSee('Regular, Rex');
    }

    public function testAFirstYearStudentFillsTheMs1Folder(): void
    {
        $this->insertRow('reservists', [
            'reservist_number' => 'RSV-AP-F', 'afp_serial_number' => 'AP-FRESH', 'first_name' => 'Fiona', 'last_name' => 'Freshman',
            'organizational_unit_id' => $this->cdcId, 'company_unit_id' => $this->companyId,
            'is_student' => 1, 'educational_attainment' => 'undergraduate', 'student_year_level' => '1st Year 1st Sem', 'status' => 'active',
        ]);

        $folders = \App\Controllers\Admin\EnrollmentController::folders();
        $this->assertSame(1, $folders['MS1']['count']);
        $this->assertSame(0, $folders['MS2']['count']);

        $this->actingAs($this->makeUser('cdc_administrator', 'cdc'));
        $this->get('/admin/enrollment/MS1')->assertSee('Freshman, Fiona');
    }

    public function testVerifyingACorEnrollsTheStudentAndTellsThem(): void
    {
        $studentUser = $this->makeUser('reservist', 'stella');
        $this->db->table('reservists')->where('id', $this->studentId)->update(['user_id' => $studentUser->id]);

        $dir = WRITEPATH . 'uploads/cor';
        is_dir($dir) || mkdir($dir, 0755, true);
        $stored = bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($dir . '/' . $stored, '%PDF-1.4 test');

        $enrollmentId = $this->insertRow('student_enrollments', [
            'reservist_id' => $this->studentId, 'year_level' => '2nd Year 1st Sem',
            'cor_path' => $stored, 'cor_filename' => 'cor.pdf', 'status' => 'pending', 'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->makeUser('cdc_administrator', 'cdc'));
        $this->get('/admin/enrollment/MS31')->assertSee('Verify');
        $this->get('/admin/enrollment/cor/' . $enrollmentId)->assertOK();

        $this->post('/admin/enrollment/cor/' . $enrollmentId . '/verify', ['return_to' => 'https://evil.example/']);

        $this->seeInDatabase('student_enrollments', ['id' => $enrollmentId, 'status' => 'verified']);
        $this->seeInDatabase('notifications', ['user_id' => $studentUser->id, 'type' => 'cor_verified']);
        $this->seeInDatabase('audit_logs', ['action' => 'enrollment.cor_verified', 'entity_id' => $this->studentId]);
        $this->get('/admin/enrollment/MS31')->assertSee('Enrolled');

        @unlink($dir . '/' . $stored);
    }

    public function testAnAdministratorAssignsTheClassification(): void
    {
        $this->actingAs($this->makeUser('superadmin', 'super'));

        $this->post('/admin/classification/reservist/' . $this->regularId, ['classification' => 'standby']);
        $this->seeInDatabase('reservists', ['id' => $this->regularId, 'reserve_classification' => 'standby']);
        $this->get('/admin/classification/standby')->assertSee('Regular, Rex');

        $this->post('/admin/classification/reservist/' . $this->regularId, ['classification' => 'ready_reserve']);
        $this->get('/admin/classification/standby')->assertDontSee('Regular, Rex');
        $this->get('/admin/classification/ready_reserve')->assertSee('Regular, Rex');

        $this->post('/admin/classification/reservist/' . $this->regularId, ['classification' => 'active_duty']);
        $this->seeInDatabase('reservists', ['id' => $this->regularId, 'reserve_classification' => 'ready_reserve']);
    }

    public function testAStudentsSelfReportedClassificationCountsUntilAnAdminSetsOne(): void
    {
        (new \App\Models\RotcProgressModel())->saveStage($this->studentId, 'E1', ['classification' => 'ready_reserve', 'checks' => []]);

        $this->assertSame(1, (new \App\Models\ReservistModel())->directoryQuery(['classification' => 'ready_reserve', 'student' => true])->countAllResults());

        $this->db->table('reservists')->where('id', $this->studentId)->update(['reserve_classification' => 'standby']);

        $this->assertSame(0, (new \App\Models\ReservistModel())->directoryQuery(['classification' => 'ready_reserve', 'student' => true])->countAllResults());
    }
}
