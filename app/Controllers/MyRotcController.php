<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\StudentEnrollmentModel;
use App\Services\RotcProgressService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\RotcProgression;
use RuntimeException;

/**
 * The student reservist's ROTC ladder (E1-E8), where they record the
 * buttons the client listed for each semester. See RotcProgressService.
 */
class MyRotcController extends ReservistPortalController
{
    public function index(): string
    {
        $reservist = $this->ownReservist();

        if ($reservist === null) {
            return $this->notLinked('ROTC Progress');
        }

        $service = service('rotcProgressService');

        return view('my/rotc', $this->portalContext($reservist) + [
            'title'   => 'ROTC Progress',
            'rotc'    => $service->summarize($reservist),
            'config'  => config(RotcProgression::class),
            'service' => $service,
            'cor'     => (new StudentEnrollmentModel())->findFor($reservist->id, $reservist->student_year_level),
        ]);
    }

    /**
     * Uploads the student's Certificate of Registration for the semester
     * they are in now. A new upload replaces the old file and goes back to
     * "Pending" until an administrator verifies it.
     */
    public function uploadCor(): RedirectResponse
    {
        $reservist = $this->ownReservist();
        $file      = $this->request->getFile('cor');
        $back      = site_url('my/rotc');

        if ($reservist === null || ! $reservist->isStudentReservist() || $reservist->student_year_level === null) {
            return redirect()->to($back)->with('error', 'Set your year level on My Profile before uploading a COR.');
        }

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->to($back)->with('error', 'Choose your COR file to upload.');
        }

        $storage = service('fileStorageService');

        try {
            $stored = $storage->storeAttachment($file, StudentEnrollmentModel::COR_DIR, ['application/pdf', 'image/jpeg', 'image/png'], 'Your COR must be a PDF, JPG, or PNG file.');
        } catch (RuntimeException $exception) {
            return redirect()->to($back)->with('error', $exception->getMessage());
        }

        $model    = new StudentEnrollmentModel();
        $existing = $model->findFor($reservist->id, $reservist->student_year_level);
        $data     = [
            'reservist_id' => $reservist->id,
            'year_level'   => $reservist->student_year_level,
            'cor_path'     => $stored['stored_filename'],
            'cor_filename' => $stored['original_filename'],
            'status'       => 'pending',
            'uploaded_at'  => date('Y-m-d H:i:s'),
            'verified_by'  => null,
            'verified_at'  => null,
        ];

        if ($existing === null) {
            $model->insert($data);
        } else {
            $old = $storage->attachmentPath(StudentEnrollmentModel::COR_DIR, $existing['cor_path']);
            $model->update($existing['id'], $data);

            if ($old !== null) {
                @unlink($old);
            }
        }

        $user = $this->currentUser();
        service('auditLogService')->record($user !== null ? (int) $user->id : null, 'enrollment.cor_uploaded', 'reservist', $reservist->id, [], ['year_level' => $reservist->student_year_level]);

        return redirect()->to($back)->with('success', 'Your COR is uploaded and waiting for verification.');
    }

    /**
     * Shows the student their own uploaded COR.
     */
    public function cor(): ResponseInterface
    {
        $reservist = $this->ownReservist();
        $row       = $reservist !== null ? (new StudentEnrollmentModel())->findFor($reservist->id, $reservist->student_year_level) : null;
        $path      = service('fileStorageService')->attachmentPath(StudentEnrollmentModel::COR_DIR, $row['cor_path'] ?? null);

        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        return \App\Controllers\Admin\EnrollmentController::fileResponse($this->response, $path, (string) $row['cor_filename']);
    }

    public function save(string $stage): RedirectResponse
    {
        $reservist = $this->ownReservist();

        if ($reservist === null) {
            return redirect()->to(site_url('my/rotc'));
        }

        $stage = strtoupper($stage);
        $user  = $this->currentUser();

        try {
            service('rotcProgressService')->saveStage(
                $reservist,
                $stage,
                $this->request->getPost(),
                $this->request->getFile('justification'),
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('my/rotc') . '#stage-' . $stage)->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('my/rotc') . '#stage-' . $stage)
            ->with('success', 'E-' . substr($stage, 1) . ' saved.');
    }

    /**
     * Downloads the reservist's own E-4 justification letter.
     */
    public function justification(): ResponseInterface
    {
        $reservist = $this->ownReservist();
        $row       = $reservist !== null
            ? (model(\App\Models\RotcProgressModel::class)->stagesFor($reservist->id)['E4'] ?? null)
            : null;

        return self::justificationResponse($this->response, $row);
    }

    /**
     * Shared with the staff download route in ReservistsController.
     *
     * @param array<string, mixed>|null $row the E4 rotc_progress row
     */
    public static function justificationResponse(ResponseInterface $response, ?array $row): ResponseInterface
    {
        $path = service('fileStorageService')->attachmentPath(RotcProgressService::JUSTIFICATION_DIR, $row['justification_path'] ?? null);

        if ($path === null) {
            return $response->setStatusCode(404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'   => 'application/pdf',
            'png'   => 'image/png',
            default => 'image/jpeg',
        };
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) ($row['justification_filename'] ?? 'justification-letter')) ?: 'justification-letter';

        return $response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }
}
