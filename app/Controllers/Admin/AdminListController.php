<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UnitModel;

/**
 * Shared by the admin panel's reservist lists (client brief of 2026-11-10):
 * the search / CDC / company filter bar and its option lists.
 */
abstract class AdminListController extends BaseController
{
    protected const PER_PAGE = 20;

    /**
     * @return array{q: string, cdc: int, company: int}
     */
    protected function filters(): array
    {
        return [
            'q'       => trim((string) $this->request->getGet('q')),
            'cdc'     => (int) $this->request->getGet('cdc'),
            'company' => (int) $this->request->getGet('company'),
        ];
    }

    /**
     * What the filter bar needs to render.
     *
     * @param array{q: string, cdc: int, company: int} $filters
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(array $filters): array
    {
        $units     = new UnitModel();
        $companies = array_values(array_filter(
            $units->activeCompanies(),
            static fn (array $c): bool => $filters['cdc'] === 0 || (int) $c['parent_unit_id'] === $filters['cdc']
        ));

        return [
            'filters'       => $filters,
            'filterCdcs'    => (new UnitModel())->activeCdcs(),
            'filterCompanies' => $companies,
        ];
    }

    /**
     * Current list URL with its query, for a form to return to.
     */
    protected function currentListUrl(): string
    {
        $query = $this->request->getUri()->getQuery();

        return current_url() . ($query !== '' ? '?' . $query : '');
    }
}
