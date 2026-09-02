<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\BackupService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * System settings and database backups.
 *
 * No SystemSettingModel: `codeigniter4/settings` is already installed (Shield
 * depends on it) with its database handler enabled, so `setting()` is a
 * persisted key/value store this application can use as-is. Adding a second
 * one would be a table and a model that do the same job.
 *
 * Only settings something actually reads are offered here. A setting no code
 * consults is a config knob that silently does nothing.
 */
class SettingsController extends BaseController
{
    /**
     * @var array<string, array{label: string, help: string, type: string, default: string}>
     */
    private const EDITABLE = [
        'App.organizationName' => [
            'label'   => 'Organization name',
            'help'    => 'Printed as the letterhead on every generated PDF report.',
            'type'    => 'text',
            'default' => 'Philippine Army Reserve Force',
        ],
        'App.reportFooterNote' => [
            'label'   => 'Report footer note',
            'help'    => 'Printed at the foot of every generated PDF report, e.g. a handling caveat.',
            'type'    => 'text',
            'default' => 'For official use only.',
        ],
        'App.backupsToKeep' => [
            'label'   => 'Backups to keep',
            'help'    => 'Older dumps beyond this count are removed when a new backup is taken.',
            'type'    => 'number',
            'default' => '10',
        ],
    ];

    private BackupService $backupService;

    public function __construct()
    {
        $this->backupService = new BackupService();
    }

    public function index(): string
    {
        $values = [];

        foreach (self::EDITABLE as $key => $meta) {
            $values[$key] = (string) (setting($key) ?? $meta['default']);
        }

        return view('admin/settings', [
            'title'       => 'System Settings',
            'editable'   => self::EDITABLE,
            'values'     => $values,
            'backups'    => $this->backupService->list(),
            'canBackup'  => $this->authorize('backups.manage'),
        ]);
    }

    public function save(): RedirectResponse
    {
        foreach (self::EDITABLE as $key => $meta) {
            $submitted = $this->request->getPost($this->fieldNameFor($key));

            if ($submitted === null) {
                continue;
            }

            $value = trim((string) $submitted);

            if ($meta['type'] === 'number' && (! ctype_digit($value) || (int) $value < 1)) {
                return redirect()->back()->withInput()
                    ->with('error', $meta['label'] . ' must be a whole number of at least 1.');
            }

            setting($key, $value === '' ? $meta['default'] : $value);
        }

        return redirect()->to(site_url('admin/settings'))->with('success', 'Settings saved.');
    }

    public function createBackup(): RedirectResponse
    {
        try {
            $filename = $this->backupService->create();
        } catch (RuntimeException $e) {
            return redirect()->to(site_url('admin/settings'))->with('error', $e->getMessage());
        }

        $keep    = (int) (setting('App.backupsToKeep') ?? self::EDITABLE['App.backupsToKeep']['default']);
        $removed = $this->backupService->prune($keep);

        return redirect()->to(site_url('admin/settings'))->with(
            'success',
            'Backup ' . $filename . ' created.'
                . ($removed > 0 ? ' ' . $removed . ' older backup(s) pruned.' : '')
        );
    }

    public function downloadBackup(string $filename): ResponseInterface
    {
        $path = $this->backupService->pathFor($filename);

        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('That backup was not found.');
        }

        return $this->response->download($path, null)->setFileName($filename);
    }

    public function deleteBackup(string $filename): RedirectResponse
    {
        if (! $this->backupService->delete($filename)) {
            return redirect()->to(site_url('admin/settings'))->with('error', 'That backup could not be removed.');
        }

        return redirect()->to(site_url('admin/settings'))->with('success', 'Backup removed.');
    }

    /**
     * Setting keys contain a dot, which PHP turns into an underscore in POST
     * field names; the form posts the converted name and this maps back.
     */
    private function fieldNameFor(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
