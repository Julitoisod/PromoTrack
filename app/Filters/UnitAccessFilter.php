<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforces organizational-unit ownership on record-level routes.
 *
 * Usage in routes (filter arguments): 'unit-access:{table},{unitColumn}'
 * Example: 'unit-access:reservists,organizational_unit_id'
 *
 * The record's primary key is read from the last routed URI segment.
 * Only unit-scoped groups (currently `unit_s1_clerk`) are restricted to
 * records belonging to their own organizational unit; higher-echelon
 * groups (CDC, RCDG, Records, Commander, System Administrator,
 * Superadmin) legitimately need cross-unit visibility as a package
 * moves through the workflow, so they bypass this filter. Fine-grained,
 * per-permission checks are still enforced separately by the
 * `permission` filter and the relevant Service layer.
 */
class UnitAccessFilter implements FilterInterface
{
    /**
     * Groups whose access is restricted to their own organizational unit.
     *
     * @var list<string>
     */
    private array $unitScopedGroups = ['unit_s1_clerk'];

    /**
     * @param list<string>|null $arguments [0] => table name, [1] => unit column (optional)
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return;
        }

        $user = auth()->user();

        $isUnitScoped = false;

        foreach ($this->unitScopedGroups as $group) {
            if ($user->inGroup($group)) {
                $isUnitScoped = true;
                break;
            }
        }

        if (! $isUnitScoped) {
            // Higher-echelon roles are not restricted by this filter.
            return;
        }

        if (empty($arguments[0])) {
            // No table specified: nothing record-specific to validate here.
            return;
        }

        $table      = $arguments[0];
        $unitColumn = $arguments[1] ?? 'organizational_unit_id';

        $recordId = $this->recordIdFromUri($request);

        if ($recordId === null) {
            return;
        }

        $db  = db_connect();
        $row = $db->table($table)
            ->select($unitColumn)
            ->where('id', (int) $recordId)
            ->get()
            ->getRowArray();

        if ($row === null) {
            // Record does not exist; let the controller return a 404.
            return;
        }

        $recordUnitId = $row[$unitColumn] ?? null;

        if ($recordUnitId !== null && (int) $recordUnitId !== (int) $user->organizational_unit_id) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'message' => 'You are not authorized to access records outside your organizational unit.',
                    'errors'  => [],
                ]);
        }
    }

    /**
     * Returns the id of the record being acted on: the first numeric segment
     * of the URI.
     *
     * It used to read the *last* segment, which silently did nothing on every
     * route that ends in a verb or a sub-resource -- `reservists/12/edit`,
     * `reservists/12/delete`, `reservists/12/assessments`,
     * `reservists/12/packages` -- so cross-unit access on those paths was
     * caught only by the controller's own check, never by this filter. Every
     * route this filter is attached to names its record with the first numeric
     * segment; any later number identifies a row in a different table (a
     * nested record) and must not be looked up in `$table`.
     */
    private function recordIdFromUri(RequestInterface $request): ?int
    {
        foreach ($request->getUri()->getSegments() as $segment) {
            if (is_numeric($segment)) {
                return (int) $segment;
            }
        }

        return null;
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required after the request.
    }
}
