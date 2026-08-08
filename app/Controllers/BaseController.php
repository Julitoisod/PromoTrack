<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends \CodeIgniter\Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    protected $session;

    /**
     * Helpers loaded for every controller that extends this class.
     *
     * @var list<string>
     */
    protected $helpers = ['form', 'url', 'auth', 'setting', 'csv'];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        $this->session = service('session');
    }

    /**
     * Returns the currently authenticated Shield user, or null when a
     * request reaches a controller action without being logged in
     * (should not normally happen on filter-protected routes).
     */
    protected function currentUser(): ?User
    {
        return auth()->user();
    }

    /**
     * Convenience guard for controller-level authorization checks that
     * complement (never replace) the `permission` route filter. Use this
     * inside a controller action when the required permission depends on
     * runtime data (for example, a different permission for the same
     * route depending on the record being acted on).
     */
    protected function authorize(string $permission): bool
    {
        $user = $this->currentUser();

        return $user !== null && $user->can($permission);
    }

    /**
     * Verifies that the given organizational-unit id belongs to the
     * current user, unless the user belongs to a group with cross-unit
     * visibility. Intended as an explicit, controller-level companion to
     * the `unit-access` route filter for record-level checks.
     */
    protected function belongsToUserUnit(?int $organizationalUnitId): bool
    {
        $user = $this->currentUser();

        if ($user === null) {
            return false;
        }

        if (! $user->inGroup('unit_s1_clerk')) {
            // Higher-echelon roles are not restricted to a single unit.
            return true;
        }

        return $organizationalUnitId !== null
            && $organizationalUnitId === (int) $user->organizational_unit_id;
    }

    /**
     * Builds the standardized JSON success envelope used by every
     * PromoTrack AFP-RF API/AJAX endpoint.
     */
    protected function jsonSuccess(string $message = 'Operation completed successfully.', array $data = [], int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * Builds the standardized JSON failure envelope used by every
     * PromoTrack AFP-RF API/AJAX endpoint.
     */
    protected function jsonError(string $message = 'The operation could not be completed.', array $errors = [], int $status = 400): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
    }
}
