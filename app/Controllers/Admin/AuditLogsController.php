<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Read-only window onto `audit_logs`.
 *
 * AuditLogService and AuditRequestFilter have been writing this table since
 * the workflow was built, but nothing could read it inside the application --
 * the tamper-evidence trail the design promises was reachable only through
 * phpMyAdmin. This controller is deliberately read-only: no update, no delete,
 * no route that could edit a row.
 *
 * The listing is paginated in SQL rather than handed to the browser whole;
 * this is the table that grows fastest in the system.
 */
class AuditLogsController extends BaseController
{
    private const PER_PAGE = 50;

    /** Hard ceiling on an export, so one click cannot stream a million rows. */
    private const EXPORT_LIMIT = 5000;

    private AuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLogModel();
    }

    public function index(): string
    {
        $filters = $this->filters();

        // filteredQuery() returns its own model instance, so the pager has to
        // be read from that instance -- not from $this->auditLogModel.
        $query = $this->filteredQuery($filters)
            ->orderBy('audit_logs.created_at', 'DESC')
            ->orderBy('audit_logs.id', 'DESC');

        $rows = $query->paginate(self::PER_PAGE);

        return view('admin/audit_logs', [
            'title'       => 'Audit Logs',
            'breadcrumbs' => [
                'Dashboard'  => site_url('/'),
                'Audit Logs' => '',
            ],
            'rows'        => $rows,
            'pager'       => $query->pager,
            'filters'     => $filters,
            'actions'     => $this->distinctValues('action'),
            'entityTypes' => $this->distinctValues('entity_type'),
        ]);
    }

    /**
     * Streams the current filter selection as CSV, for the paper trail an
     * audit review actually needs.
     */
    public function export(): ResponseInterface
    {
        $filters = $this->filters();

        $rows = $this->filteredQuery($filters)
            ->orderBy('audit_logs.created_at', 'DESC')
            ->findAll(self::EXPORT_LIMIT);

        $columns = ['created_at', 'username', 'action', 'entity_type', 'entity_id', 'ip_address', 'old_values', 'new_values'];

        return $this->response->download(
            'audit-logs-' . date('Ymd-His') . '.csv',
            csv_from_rows($rows, $columns)
        )->setContentType('text/csv');
    }

    /**
     * @return array{action: string, entity_type: string, date_from: string, date_to: string, q: string}
     */
    private function filters(): array
    {
        return [
            'action'      => (string) ($this->request->getGet('action') ?? ''),
            'entity_type' => (string) ($this->request->getGet('entity_type') ?? ''),
            'date_from'   => (string) ($this->request->getGet('date_from') ?? ''),
            'date_to'     => (string) ($this->request->getGet('date_to') ?? ''),
            'q'           => (string) ($this->request->getGet('q') ?? ''),
        ];
    }

    /**
     * Applies the filter selection to a fresh query, joining the acting user's
     * name so the listing reads as people rather than ids.
     */
    private function filteredQuery(array $filters): AuditLogModel
    {
        $model = (new AuditLogModel())
            ->select('audit_logs.*, users.username AS username')
            ->join('users', 'users.id = audit_logs.user_id', 'left');

        if ($filters['action'] !== '') {
            $model->where('audit_logs.action', $filters['action']);
        }

        if ($filters['entity_type'] !== '') {
            $model->where('audit_logs.entity_type', $filters['entity_type']);
        }

        if ($filters['date_from'] !== '') {
            $model->where('audit_logs.created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if ($filters['date_to'] !== '') {
            $model->where('audit_logs.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('users.username', $filters['q'])
                ->orLike('audit_logs.entity_type', $filters['q'])
                ->orLike('audit_logs.new_values', $filters['q'])
                ->groupEnd();
        }

        return $model;
    }

    /**
     * Distinct values of one column, for the filter dropdowns.
     *
     * @return list<string>
     */
    private function distinctValues(string $column): array
    {
        $rows = (new AuditLogModel())
            ->select($column)
            ->distinct()
            ->where($column . ' IS NOT NULL')
            ->orderBy($column, 'ASC')
            ->findAll();

        return array_values(array_filter(array_column($rows, $column)));
    }
}
