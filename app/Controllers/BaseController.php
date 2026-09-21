<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReservistModel;
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
     * Memoized `ownReservistId()` result; $ownReservistResolved
     * distinguishes "not looked up yet" from "looked up, owns none".
     */
    private ?int $ownReservistId = null;

    private bool $ownReservistResolved = false;

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

        if ($this->isReservistAccount()) {
            // A reservist is scoped to their own record, never to a unit:
            // "same unit" would hand them every colleague's package. Their
            // surfaces authorize through canAccessPackageRow() instead, so
            // failing here keeps anything not yet updated closed.
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
     * True when the signed-in account is a reservist's own account
     * rather than a staff account.
     */
    protected function isReservistAccount(): bool
    {
        $user = $this->currentUser();

        return $user !== null && $user->inGroup('reservist');
    }

    /**
     * The `reservists.id` this account owns, or null for a staff account
     * (and for a reservist account whose record could not be linked at
     * sign-up). Resolved once per request.
     */
    protected function ownReservistId(): ?int
    {
        if ($this->ownReservistResolved) {
            return $this->ownReservistId;
        }

        $this->ownReservistResolved = true;
        $user                      = $this->currentUser();

        if ($user === null) {
            return $this->ownReservistId = null;
        }

        $reservist = (new ReservistModel())->findByUserId((int) $user->id);

        return $this->ownReservistId = $reservist?->id;
    }

    /**
     * Authorizes a row that carries a promotion package's
     * `reservist_id` and `organizational_unit_id` -- a package itself,
     * or a package document joined to its package.
     *
     * Two different ownership questions meet here, which is why it is one
     * method rather than a check repeated per controller: staff are
     * scoped by organizational unit, a reservist is scoped to their own
     * record and nothing else.
     */
    protected function canAccessPackageRow(array $row): bool
    {
        if ($this->isReservistAccount()) {
            $ownReservistId = $this->ownReservistId();

            return $ownReservistId !== null
                && isset($row['reservist_id'])
                && (int) $row['reservist_id'] === $ownReservistId;
        }

        return $this->belongsToUserUnit(
            isset($row['organizational_unit_id']) ? (int) $row['organizational_unit_id'] : null
        );
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
