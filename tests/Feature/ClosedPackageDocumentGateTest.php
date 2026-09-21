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
 * A finished package is a finished record.
 *
 * Verify, reject, and waive were gated on permission alone, so a CDC
 * administrator could still record a review decision against a package
 * that had already closed -- writing a `document_reviews` row dated
 * after the promotion order it supposedly justified. Upload had its own
 * gate; these three did not.
 *
 * Both directions are asserted, because a gate that refuses everything
 * would pass a one-sided test: refused once the package is terminal,
 * still allowed while it is live.
 *
 * @internal
 */
final class ClosedPackageDocumentGateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $unitId;
    private int $reservistId;
    private int $requirementId;

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
            'unit_code' => 'CG-UNIT',
            'unit_name' => 'Closed Gate Test Unit',
            'is_active' => 1,
        ]);

        $this->reservistId = $this->insert('reservists', [
            'reservist_number'       => 'RSV-CG-1',
            'first_name'             => 'Gate',
            'last_name'              => 'Tester',
            'organizational_unit_id' => $this->unitId,
            'status'                 => 'active',
        ]);

        $templateId = $this->insert('document_requirement_templates', [
            'template_name' => 'Closed Gate Template',
            'version'       => 1,
            'is_active'     => 1,
        ]);

        $this->requirementId = $this->insert('document_requirements', [
            'document_requirement_template_id' => $templateId,
            'document_code'                    => 'A',
            'document_label'                   => 'Recommendation for Promotion',
            'is_conditional'                   => 0,
            'allows_commander_override'        => 0,
            'display_order'                    => 1,
        ]);
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

    /**
     * @return int the package_documents id, sitting at `uploaded`
     */
    private function packageDocumentInPackageStatus(string $packageStatus): int
    {
        $packageId = $this->insert('promotion_packages', [
            'reservist_id'           => $this->reservistId,
            'organizational_unit_id' => $this->unitId,
            'package_number'         => 'PKG-CG-' . bin2hex(random_bytes(3)),
            'status'                 => $packageStatus,
            'current_routing_level'  => 'CDC',
        ]);

        $documentId = $this->insert('package_documents', [
            'promotion_package_id'    => $packageId,
            'document_requirement_id' => $this->requirementId,
            'status'                  => 'uploaded',
        ]);

        // A review decision needs a current version to attach itself to.
        $versionId = $this->insert('document_versions', [
            'package_document_id' => $documentId,
            'version_number'      => 1,
            'stored_filename'     => 'a.pdf',
            'original_filename'   => 'a.pdf',
            'mime_type'           => 'application/pdf',
            'file_size_bytes'     => 10,
            'sha256_checksum'     => str_repeat('c', 64),
            'storage_path'        => WRITEPATH . 'uploads/a.pdf',
        ]);

        $this->db->table('package_documents')
            ->where('id', $documentId)
            ->update(['current_version_id' => $versionId]);

        return $documentId;
    }

    private function cdcAdministrator(): User
    {
        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'cg_cdc',
            'email'                  => 'cg_cdc@promotrack.local',
            'password'               => 'ClosedGate!12345',
            'full_name'              => 'Closed Gate CDC',
            'organizational_unit_id' => $this->unitId,
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('cdc_administrator');

        return $user;
    }

    private function statusOf(int $documentId): string
    {
        return (string) $this->db->table('package_documents')
            ->select('status')
            ->where('id', $documentId)
            ->get()
            ->getRowArray()['status'];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function terminalStatusProvider(): array
    {
        return [
            'closed'      => ['closed'],
            'disapproved' => ['disapproved'],
            'archived'    => ['archived'],
        ];
    }

    /**
     * @dataProvider terminalStatusProvider
     */
    public function testReviewDecisionsAreRefusedOnATerminalPackage(string $packageStatus): void
    {
        $documentId = $this->packageDocumentInPackageStatus($packageStatus);

        $this->actingAs($this->cdcAdministrator());

        $this->post('/package-documents/' . $documentId . '/verify');
        $this->assertSame('uploaded', $this->statusOf($documentId), 'verify was allowed on a ' . $packageStatus . ' package.');

        $this->post('/package-documents/' . $documentId . '/reject', ['remarks' => 'no']);
        $this->assertSame('uploaded', $this->statusOf($documentId), 'reject was allowed on a ' . $packageStatus . ' package.');

        $this->post('/package-documents/' . $documentId . '/waive', ['justification' => 'no']);
        $this->assertSame('uploaded', $this->statusOf($documentId), 'waive was allowed on a ' . $packageStatus . ' package.');

        $this->assertSame(0, $this->db->table('document_reviews')->countAllResults(), 'A review row was written against a finished package.');
    }

    public function testVerifyStillWorksWhileThePackageIsLive(): void
    {
        $documentId = $this->packageDocumentInPackageStatus('under_cdc_validation');

        $this->actingAs($this->cdcAdministrator());

        $this->post('/package-documents/' . $documentId . '/verify');

        $this->assertSame('verified', $this->statusOf($documentId), 'The gate is refusing live packages too.');
    }
}
