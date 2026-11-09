<?php

declare(strict_types=1);

namespace App\Controllers;

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
        ]);
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
