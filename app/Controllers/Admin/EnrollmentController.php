<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ReservistModel;
use App\Models\StudentEnrollmentModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\RotcProgression;

/**
 * Student Reservist Enrollment (client brief of 2026-11-10): one folder per
 * semester (MS1 ... MS42) with its enrolled-student count; each folder opens
 * its own page listing those students, their Certificate of Registration
 * (COR) and whether it was verified.
 */
class EnrollmentController extends AdminListController
{
    public function index(): string
    {
        return view('admin/enrollment/index', [
            'title'   => 'Student Reservist Enrollment',
            'folders' => self::folders(),
        ]);
    }

    public function folder(string $code): string
    {
        $config = config(RotcProgression::class);
        $code   = strtoupper($code);
        $level  = $config->enrollmentFolders[$code] ?? null;

        if ($level === null) {
            throw PageNotFoundException::forPageNotFound('Unknown semester folder.');
        }

        $filters  = $this->filters();
        $model    = new ReservistModel();
        $students = $model->directoryQuery($filters + ['student' => true, 'year_level' => $level])
            ->orderBy('reservists.last_name', 'ASC')
            ->asArray()
            ->paginate(self::PER_PAGE);

        return view('admin/enrollment/folder', $this->filterOptions($filters) + [
            'title'       => $code . ' Enrolled Students',
            'code'        => $code,
            'level'       => $level,
            'students'    => $students,
            'pager'       => $model->pager,
            'total'       => $model->pager->getTotal(),
            'enrollments' => (new StudentEnrollmentModel())->currentFor($students),
            'returnTo'    => $this->currentListUrl(),
        ]);
    }

    /**
     * Streams an uploaded COR.
     */
    public function cor(?int $id = null): ResponseInterface
    {
        $row  = $id !== null ? (new StudentEnrollmentModel())->find($id) : null;
        $path = service('fileStorageService')->attachmentPath(StudentEnrollmentModel::COR_DIR, $row['cor_path'] ?? null);

        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('That COR was not found.');
        }

        return self::fileResponse($this->response, $path, (string) ($row['cor_filename'] ?? 'cor'));
    }

    public function verify(?int $id = null): RedirectResponse
    {
        return $this->decide($id, 'verified');
    }

    public function reject(?int $id = null): RedirectResponse
    {
        return $this->decide($id, 'rejected');
    }

    /**
     * Folder code => [year level, enrolled count, CORs waiting].
     *
     * @return array<string, array{level: string, count: int, pending: int}>
     */
    public static function folders(): array
    {
        $counts  = (new ReservistModel())->studentCountsByYearLevel();
        $pending = db_connect()->table('student_enrollments e')
            ->select('e.year_level, COUNT(*) AS total')
            ->join('reservists r', 'r.id = e.reservist_id AND r.student_year_level = e.year_level AND r.deleted_at IS NULL')
            ->where('e.status', 'pending')
            ->groupBy('e.year_level')
            ->get()
            ->getResultArray();
        $pending = array_column($pending, 'total', 'year_level');

        $folders = [];

        foreach (config(RotcProgression::class)->enrollmentFolders as $code => $level) {
            $folders[$code] = [
                'level'   => $level,
                'count'   => (int) ($counts[$level] ?? 0),
                'pending' => (int) ($pending[$level] ?? 0),
            ];
        }

        return $folders;
    }

    public static function fileResponse(ResponseInterface $response, string $path, string $name): ResponseInterface
    {
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'   => 'application/pdf',
            'png'   => 'image/png',
            default => 'image/jpeg',
        };
        $safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'cor';

        return $response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . $safe . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    private function decide(?int $id, string $status): RedirectResponse
    {
        $model = new StudentEnrollmentModel();
        $row   = $id !== null ? $model->find($id) : null;
        $back  = (string) ($this->request->getPost('return_to') ?: site_url('admin/enrollment'));

        // Only ever redirect within the site.
        if (! str_starts_with($back, site_url())) {
            $back = site_url('admin/enrollment');
        }

        if ($row === null || $row['cor_path'] === null) {
            return redirect()->to($back)->with('error', 'There is no uploaded COR to ' . ($status === 'verified' ? 'verify' : 'reject') . '.');
        }

        $user = $this->currentUser();

        $model->update($row['id'], [
            'status'      => $status,
            'verified_by' => $user !== null ? (int) $user->id : null,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        service('auditLogService')->record(
            $user !== null ? (int) $user->id : null,
            'enrollment.cor_' . $status,
            'reservist',
            (int) $row['reservist_id'],
            ['status' => $row['status']],
            ['status' => $status, 'year_level' => $row['year_level']]
        );

        $reservist = (new ReservistModel())->find((int) $row['reservist_id']);

        if ($reservist?->user_id !== null) {
            service('notificationService')->notify(
                (int) $reservist->user_id,
                'cor_' . $status,
                $status === 'verified' ? 'COR verified' : 'COR needs to be uploaded again',
                $status === 'verified'
                    ? 'Your Certificate of Registration for ' . $row['year_level'] . ' was verified. You are enrolled.'
                    : 'Your Certificate of Registration for ' . $row['year_level'] . ' was not accepted. Upload a clear copy on ROTC Progress.',
                'reservist',
                (int) $row['reservist_id']
            );
        }

        $name = $reservist !== null ? $reservist->getFullName() : 'The student';

        return redirect()->to($back)->with('success', $status === 'verified'
            ? $name . "'s enrollment is verified."
            : $name . "'s COR was rejected; they have been asked to upload it again.");
    }
}
