<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Database backup and restore support.
 *
 * The design's Phase 8 sign-off asks for a backup whose restore has actually
 * been rehearsed, which means the dump has to be a plain `mysqldump` file an
 * operator can feed straight back to `mysql` -- not a bespoke format only this
 * application understands. So this wraps mysqldump rather than reimplementing
 * it, and `restoreCommand()` hands back the exact line to run, because a
 * restore is a decision a person makes on the server, not a button in a web
 * page that can be clicked by accident.
 *
 * Dumps land in writable/backups, never under public/.
 */
class BackupService
{
    /** Filenames are generated, but the download route still matches this. */
    public const FILENAME_PATTERN = '/^promotrack-[0-9]{8}-[0-9]{6}\.sql$/';

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? WRITEPATH . 'backups', '/\\');
    }

    /**
     * Writes a new dump and returns its filename.
     *
     * @throws RuntimeException when mysqldump is unavailable or fails.
     */
    public function create(): string
    {
        $this->ensureDirectory();

        $config   = config('Database')->default;
        $filename = 'promotrack-' . date('Ymd-His') . '.sql';
        $path     = $this->directory . DIRECTORY_SEPARATOR . $filename;

        $binary = $this->mysqldumpPath();

        $command = sprintf(
            '%s --host=%s --port=%s --user=%s %s --single-transaction --routines --result-file=%s %s',
            escapeshellarg($binary),
            escapeshellarg((string) $config['hostname']),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg((string) $config['username']),
            // An empty password must not become `--password=''`, which makes
            // mysqldump prompt interactively and hang the request.
            ((string) $config['password']) !== '' ? '--password=' . escapeshellarg((string) $config['password']) : '',
            escapeshellarg($path),
            escapeshellarg((string) $config['database'])
        );

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($path) || filesize($path) === 0) {
            if (is_file($path)) {
                unlink($path);
            }

            throw new RuntimeException(
                'The backup could not be created. mysqldump said: ' . trim(implode(' ', $output))
            );
        }

        return $filename;
    }

    /**
     * Every dump on file, newest first.
     *
     * @return list<array{filename: string, size_bytes: int, created_at: string}>
     */
    public function list(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $backups = [];

        foreach ((array) glob($this->directory . DIRECTORY_SEPARATOR . '*.sql') as $path) {
            $path = (string) $path;

            $backups[] = [
                'filename'   => basename($path),
                'size_bytes' => (int) filesize($path),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ];
        }

        usort($backups, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    /**
     * Absolute path of one dump, or null when the name does not match a dump
     * this service created. The pattern check is what stops a crafted filename
     * from walking out of the backup directory.
     */
    public function pathFor(string $filename): ?string
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->pathFor($filename);

        return $path !== null && unlink($path);
    }

    /**
     * The command an operator runs on the server to restore a dump. Shown in
     * the UI rather than executed: restoring overwrites live data, and that
     * belongs to a human at a shell, not to an HTTP request.
     */
    public function restoreCommand(string $filename): string
    {
        $config = config('Database')->default;

        return sprintf(
            'mysql --host=%s --port=%s --user=%s -p %s < %s',
            (string) $config['hostname'],
            (string) ($config['port'] ?? 3306),
            (string) $config['username'],
            (string) $config['database'],
            $this->directory . DIRECTORY_SEPARATOR . $filename
        );
    }

    /**
     * Deletes the oldest dumps beyond $keep. Returns how many were removed.
     */
    public function prune(int $keep): int
    {
        if ($keep < 1) {
            return 0;
        }

        $backups = $this->list();
        $removed = 0;

        foreach (array_slice($backups, $keep) as $backup) {
            if ($this->delete($backup['filename'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Locates mysqldump: XAMPP does not put it on PATH on Windows, so the
     * bundled copy is checked before falling back to whatever PATH offers.
     */
    private function mysqldumpPath(): string
    {
        $candidates = [
            'C:/xampp/mysql/bin/mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mysqldump';
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('The backup directory could not be created.');
        }

        // writable/ is outside the document root, but a stray server config
        // should still never be able to serve a dump directly.
        $htaccess = $this->directory . DIRECTORY_SEPARATOR . '.htaccess';

        if (! is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n");
        }
    }
}
