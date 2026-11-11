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
 * Staff Account Settings: username and email masked with Show/Hide, and a
 * password change that touches only the signed-in account.
 *
 * @internal
 */
final class AccountSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff($filtersConfig->globals['before'], ['csrf']));
    }

    protected function tearDown(): void
    {
        auth()->logout();
        session()->destroy();

        parent::tearDown();
    }

    private function signIn(string $group): User
    {
        $users = auth()->getProvider();
        $user  = new User(['username' => 'as_' . $group, 'email' => 'as_' . $group . '@promotrack.local', 'password' => 'AccountPass!12345']);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup($group);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function staffProvider(): array
    {
        return [
            'superadmin' => ['superadmin'],
            'clerk'      => ['unit_s1_clerk'],
            'CDC'        => ['cdc_administrator'],
            'commander'  => ['commander_viewer'],
        ];
    }

    /**
     * @dataProvider staffProvider
     */
    public function testEveryStaffRoleSeesTheirAccountMasked(string $group): void
    {
        $this->signIn($group);

        $page = $this->get('/account/settings');
        $page->assertOK();
        $page->assertSee('Account Settings');
        $html = (string) $page->getBody();
        $this->assertStringContainsString('mask-toggle', $html);
        // The real value is in the page but hidden until "Show".
        $this->assertStringContainsString('<span class="masked-real d-none">as_' . $group . '</span>', $html);
    }

    public function testStaffChangeTheirOwnPassword(): void
    {
        $user = $this->signIn('superadmin');

        $this->post('/account/settings/password', [
            'current_password' => 'Wrong!Password1',
            'password'         => 'BrandNewSecret!2026',
            'password_confirm' => 'BrandNewSecret!2026',
        ])->assertSessionHas('error');

        $this->post('/account/settings/password', [
            'current_password' => 'AccountPass!12345',
            'password'         => 'BrandNewSecret!2026',
            'password_confirm' => 'BrandNewSecret!2026',
        ])->assertSessionHas('success', 'Your password has been changed.');

        $this->assertTrue(auth('session')->check(['email' => $user->email, 'password' => 'BrandNewSecret!2026'])->isOK());
    }

    public function testAReservistIsSentToTheirOwnSettings(): void
    {
        $this->signIn('reservist');

        $this->get('/account/settings')->assertRedirectTo(site_url('my/settings'));
    }

    public function testAGuestIsSentToLogin(): void
    {
        $this->assertTrue($this->get('/account/settings')->isRedirect());
    }
}
