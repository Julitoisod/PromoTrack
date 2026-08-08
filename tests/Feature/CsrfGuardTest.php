<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filters\CsrfGuard;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;

/**
 * Covers the `csrf` global filter, which this application replaces with
 * {@see CsrfGuard}.
 *
 * The behaviour that matters here is the failure path, because verification
 * itself is the framework's. A submission with a missing or spent token must:
 *  - still be refused (nothing written),
 *  - come back to the operator as a recoverable form, not a bare 403,
 *  - come back to a JSON caller as a hard 403,
 *  - never bounce the browser to an off-site URL, and
 *  - never flash the spent token or a password back into the form.
 *
 * @internal
 */
final class CsrfGuardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private User $clerk;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();

        // Unlike the other feature tests, csrf stays in the global chain here —
        // it is the subject under test.
        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);

        if (! in_array('csrf', $filtersConfig->globals['before'], true)) {
            $filtersConfig->globals['before'][] = 'csrf';
        }

        $this->db->table('organizational_units')->insert([
            'unit_code' => 'UNIT-CSRF',
            'unit_name' => 'CSRF Test Unit',
            'is_active' => 1,
        ]);
        $this->unitId = (int) $this->db->insertID();

        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'csrfclerk',
            'email'                  => 'csrfclerk@promotrack.local',
            'password'               => 'ClerkPass!12345',
            'full_name'              => 'CSRF Test Clerk',
            'organizational_unit_id' => $this->unitId,
        ]);

        $users->save($user);

        $this->clerk = $users->findById($users->getInsertID());
        $users->activate($this->clerk);
        $this->clerk->addGroup('unit_s1_clerk');
    }

    public function testSubmissionWithoutATokenWritesNothing(): void
    {
        $this->actingAs($this->clerk);

        $this->post('/reservists', [
            'reservist_number'   => 'RSV-CSRF-1',
            'first_name'         => 'Juan',
            'last_name'          => 'Dela Cruz',
            'date_of_birth'      => '1990-01-01',
            'date_of_enlistment' => '2015-01-01',
        ]);

        $this->dontSeeInDatabase('reservists', ['reservist_number' => 'RSV-CSRF-1']);
    }

    public function testBrowserSubmissionIsRedirectedBackWithAnExplanationInsteadOf403(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->post('/reservists', [
            'reservist_number' => 'RSV-CSRF-2',
            'first_name'       => 'Ana',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertStringContainsString(
            'security token has expired',
            (string) session('error')
        );
    }

    public function testRecoveredInputKeepsTheFormFieldsButDropsTheTokenAndPassword(): void
    {
        $this->actingAs($this->clerk);

        $this->post('/reservists', [
            'reservist_number' => 'RSV-CSRF-3',
            'first_name'       => 'Pedro',
            'password'         => 'should-never-be-flashed',
            'token'            => 'spent-token-value',
        ]);

        $old = session('_ci_old_input');

        $this->assertIsArray($old);
        $this->assertSame('RSV-CSRF-3', $old['post']['reservist_number']);
        $this->assertSame('Pedro', $old['post']['first_name']);
        $this->assertArrayNotHasKey('password', $old['post']);
        $this->assertArrayNotHasKey('token', $old['post']);
        $this->assertArrayNotHasKey(csrf_token(), $old['post']);
    }

    public function testJsonCallerGetsAHard403(): void
    {
        $this->actingAs($this->clerk);

        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/reservists', ['first_name' => 'Ana']);

        $result->assertStatus(403);
    }

    public function testAnOffSiteRefererCannotTurnRecoveryIntoAnOpenRedirect(): void
    {
        $this->actingAs($this->clerk);

        session()->set('_ci_previous_url', 'https://attacker.example/phish');

        $result = $this->post('/reservists', ['first_name' => 'Ana']);

        $result->assertRedirect();
        $this->assertStringStartsWith(base_url(), $result->getRedirectUrl());
        $this->assertStringNotContainsString('attacker.example', $result->getRedirectUrl());
    }
}
