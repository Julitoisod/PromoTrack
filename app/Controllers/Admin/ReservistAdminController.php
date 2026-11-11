<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ReservistModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The admin panel's reservist lists (client brief of 2026-11-10):
 * Registrations, Regular Reservists, and Reservist Classification -- a
 * Standby folder and a Ready Reserve folder, each opening its own list
 * where an administrator can assign or change the classification.
 */
class ReservistAdminController extends AdminListController
{
    /**
     * @var array<string, string>
     */
    public const CLASSIFICATIONS = [
        'standby'       => 'Standby',
        'ready_reserve' => 'Ready Reserve',
    ];

    public function registrations(): string
    {
        $filters = $this->filters();
        $model   = new ReservistModel();
        $rows    = $model->directoryQuery($filters + ['registered' => true])
            ->orderBy('reservists.created_at', 'DESC')
            ->asArray()
            ->paginate(self::PER_PAGE);

        return view('admin/reservists/registrations', $this->filterOptions($filters) + [
            'title' => 'Registrations',
            'rows'  => $rows,
            'pager' => $model->pager,
            'total' => $model->pager->getTotal(),
        ]);
    }

    public function regular(): string
    {
        $filters = $this->filters();
        $model   = new ReservistModel();
        $rows    = $model->directoryQuery($filters + ['student' => false])
            ->select(ReservistModel::LATEST_PROMOTION_STATUS_SQL . ' AS promotion_status', false)
            ->orderBy('reservists.last_name', 'ASC')
            ->asArray()
            ->paginate(self::PER_PAGE);

        return view('admin/reservists/regular', $this->filterOptions($filters) + [
            'title' => 'Regular Reservists',
            'rows'  => $rows,
            'pager' => $model->pager,
            'total' => $model->pager->getTotal(),
        ]);
    }

    public function classification(): string
    {
        $counts = (new ReservistModel())->adminCounts();

        return view('admin/reservists/classification', [
            'title'  => 'Reservist Classification',
            'counts' => ['standby' => $counts['standby'], 'ready_reserve' => $counts['ready_reserve']],
        ]);
    }

    public function classificationList(string $type): string
    {
        if (! isset(self::CLASSIFICATIONS[$type])) {
            throw PageNotFoundException::forPageNotFound('Unknown classification.');
        }

        $filters = $this->filters();
        $model   = new ReservistModel();
        $rows    = $model->directoryQuery($filters + ['classification' => $type])
            ->orderBy('reservists.last_name', 'ASC')
            ->asArray()
            ->paginate(self::PER_PAGE);

        return view('admin/reservists/classification_list', $this->filterOptions($filters) + [
            'title'    => self::CLASSIFICATIONS[$type] . ' Personnel',
            'type'     => $type,
            'rows'     => $rows,
            'pager'    => $model->pager,
            'total'    => $model->pager->getTotal(),
            'returnTo' => $this->currentListUrl(),
        ]);
    }

    /**
     * Assigns Standby or Ready Reserve to one reservist.
     */
    public function updateClassification(?int $id = null): RedirectResponse
    {
        $model     = new ReservistModel();
        $reservist = $id !== null ? $model->find($id) : null;
        $value     = (string) $this->request->getPost('classification');
        $back      = (string) ($this->request->getPost('return_to') ?: site_url('admin/classification'));

        if (! str_starts_with($back, site_url())) {
            $back = site_url('admin/classification');
        }

        if ($reservist === null) {
            throw PageNotFoundException::forPageNotFound('That reservist was not found.');
        }

        if (! isset(self::CLASSIFICATIONS[$value])) {
            return redirect()->to($back)->with('error', 'Choose Standby or Ready Reserve.');
        }

        $user = $this->currentUser();
        $old  = $reservist->reserve_classification;

        $model->update($reservist->id, [
            'id'                        => $reservist->id,
            'reserve_classification'    => $value,
            'classification_updated_by' => $user !== null ? (int) $user->id : null,
            'classification_updated_at' => date('Y-m-d H:i:s'),
        ]);

        service('auditLogService')->record(
            $user !== null ? (int) $user->id : null,
            'reservist.classification_updated',
            'reservist',
            $reservist->id,
            ['reserve_classification' => $old],
            ['reserve_classification' => $value]
        );

        if ($reservist->user_id !== null && $old !== $value) {
            service('notificationService')->notify(
                (int) $reservist->user_id,
                'classification',
                'Classification updated: ' . self::CLASSIFICATIONS[$value],
                'Your reserve classification is now ' . self::CLASSIFICATIONS[$value] . '.',
                'reservist',
                $reservist->id
            );
        }

        return redirect()->to($back)->with('success', $reservist->getFullName() . ' is now ' . self::CLASSIFICATIONS[$value] . '.');
    }
}
