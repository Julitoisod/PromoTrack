<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Security\Security;

/**
 * Replaces the framework `csrf` filter alias.
 *
 * Verification itself is unchanged — a request with a bad or missing token is
 * still refused and still writes nothing. What changes is the failure path: a
 * browser is sent back to the form it came from with its input flashed and a
 * plain-language error, instead of a bare 403 that loses everything the clerk
 * typed. JSON/AJAX callers still get a hard 403, because there is no form to
 * return them to.
 *
 * Why this exists: `Security.regenerate` used to rotate the token on every
 * successful POST, so a second tab, a back button, or a re-submitted form
 * carried a spent token and hit that dead end. `Config\Security` now uses a
 * per-session token (see the note there), and this filter covers the cases
 * that remain — an expired session, or a form left open past the session
 * lifetime.
 */
class CsrfGuard implements FilterInterface
{
    /**
     * Request fields that must never be written into the session's flashed
     * old-input: the spent token would be re-rendered into the recovered form,
     * and credentials have no business surviving a redirect.
     *
     * @var list<string>
     */
    private const NEVER_FLASH = [
        'password',
        'password_confirm',
        'pass',
        'token',
    ];

    /**
     * @param list<string>|null $arguments
     *
     * @return RedirectResponse|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        /** @var Security $security */
        $security = service('security');

        try {
            $security->verify($request);
        } catch (SecurityException $e) {
            return $this->recover($request, $e);
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * Turns a verification failure into the most useful response the caller
     * can act on.
     */
    private function recover(IncomingRequest $request, SecurityException $e): ResponseInterface
    {
        $wantsJson = $request->isAJAX()
            || str_contains($request->getHeaderLine('Accept'), 'application/json');

        if ($wantsJson) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Your session security token has expired. Reload the page and try again.',
                'errors'  => [],
            ]);
        }

        $input = $request->getPost();

        foreach (self::NEVER_FLASH as $field) {
            unset($input[$field]);
        }

        unset($input[csrf_token()]);

        // Set `_ci_old_input` directly rather than via withInput(): that helper
        // flashes the raw $_POST, which would put the spent token and any
        // password straight back into the recovered form.
        return redirect()
            ->to($this->safeReturnUrl())
            ->with('_ci_old_input', ['post' => $input, 'get' => $request->getGet()])
            ->with('error', 'Your session security token has expired, so the form was not submitted. '
                . 'Your entries have been restored below — please submit again.');
    }

    /**
     * Returns the URL to send the user back to, refusing anything that points
     * off this host so the recovery path cannot be turned into an open
     * redirect by a crafted Referer.
     */
    private function safeReturnUrl(): string
    {
        $previous = previous_url();

        if ($previous === '' || strpos($previous, base_url()) !== 0) {
            return site_url('/');
        }

        return $previous;
    }
}
