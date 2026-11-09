<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReservistModel;
use App\Models\UnitModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The reservist's own profile: what they can keep current themselves, and
 * their photo.
 *
 * Name, serial number, CDC, rank and service dates come from service records
 * and stay with the unit S1 clerk; the reservist sees them read-only here.
 * Contact details, schooling and company (within their own CDC) are theirs
 * to update.
 */
class MyProfileController extends ReservistPortalController
{
    public function index(): string
    {
        $reservist = $this->ownReservist();

        if ($reservist === null) {
            return $this->notLinked('My Profile');
        }

        return view('my/profile', $this->portalContext($reservist) + [
            'title'       => 'My Profile',
            'companies'   => $reservist->organizational_unit_id !== null
                ? (new UnitModel())->companiesOf((int) $reservist->organizational_unit_id)
                : [],
            'attainments' => ReservistModel::EDUCATIONAL_ATTAINMENTS,
            'yearLevels'  => ReservistModel::STUDENT_YEAR_LEVELS,
        ]);
    }

    public function update(): RedirectResponse
    {
        $reservist = $this->ownReservist();

        if ($reservist === null) {
            return redirect()->to(site_url('my/profile'));
        }

        $attainment = (string) $this->request->getPost('educational_attainment');
        $rules      = [
            'contact_number'         => ['label' => 'Contact Number', 'rules' => 'required|ph_mobile_number|max_length[30]'],
            'address'                => ['label' => 'Address', 'rules' => 'required|max_length[255]'],
            'sex'                    => ['label' => 'Sex', 'rules' => 'required|in_list[male,female]'],
            'date_of_birth'          => ['label' => 'Date of Birth', 'rules' => 'required|valid_date|not_future_date'],
            'educational_attainment' => ['label' => 'Educational Attainment', 'rules' => 'required|in_list[' . implode(',', array_keys(ReservistModel::EDUCATIONAL_ATTAINMENTS)) . ']'],
            'degree_details'         => ['label' => 'Degree', 'rules' => (in_array($attainment, ReservistModel::ATTAINMENTS_NEEDING_DETAILS, true) ? 'required' : 'permit_empty') . '|max_length[150]'],
            'is_student'             => ['label' => 'Schooling Status', 'rules' => 'required|in_list[0,1]'],
            'student_year_level'     => ['label' => 'Year Level', 'rules' => ($attainment === 'undergraduate' ? 'required' : 'permit_empty') . '|in_list[' . implode(',', ReservistModel::STUDENT_YEAR_LEVELS) . ']'],
            'company_unit_id'        => ['label' => 'Company', 'rules' => 'required|is_natural_no_zero|company_in_unit[organizational_unit_id]'],
        ];

        // company_in_unit reads the CDC from the posted data; the reservist
        // cannot choose a CDC here, so their own is what it is checked against.
        $post                           = $this->request->getPost();
        $post['organizational_unit_id'] = (string) $reservist->organizational_unit_id;

        $validation = service('validation')->setRules($rules);

        if (! $validation->run($post)) {
            return redirect()->back()->withInput()
                ->with('errors', $validation->getErrors())
                ->with('error', 'Your profile could not be saved. Please check the highlighted fields.');
        }

        $isStudent = $post['is_student'] === '1' ? 1 : 0;
        $data      = [
            'id'                     => $reservist->id,
            'contact_number'         => trim((string) $post['contact_number']),
            'address'                => trim((string) $post['address']),
            'sex'                    => $post['sex'],
            'date_of_birth'          => $post['date_of_birth'],
            'educational_attainment' => $attainment,
            'degree_details'         => in_array($attainment, ['bachelor', 'masteral', 'doctorate', 'others'], true)
                ? (trim((string) ($post['degree_details'] ?? '')) ?: null)
                : null,
            'is_student'             => $isStudent,
            'student_year_level'     => $isStudent === 1 && $attainment === 'undergraduate'
                ? $post['student_year_level']
                : null,
            'company_unit_id'        => (int) $post['company_unit_id'],
        ];

        $model = new ReservistModel();

        if (! $model->update($reservist->id, $data)) {
            return redirect()->back()->withInput()
                ->with('errors', $model->errors())
                ->with('error', 'Your profile could not be saved. Please check the highlighted fields.');
        }

        $before = array_intersect_key($reservist->toRawArray(), $data);
        unset($data['id'], $before['id']);

        service('auditLogService')->record($this->currentUserId(), 'reservist.self_updated', 'reservist', $reservist->id, $before, $data);

        return redirect()->to(site_url('my/profile'))->with('success', 'Your profile has been updated.');
    }

    public function uploadPhoto(): RedirectResponse
    {
        $reservist = $this->ownReservist();
        $file      = $this->request->getFile('photo');

        if ($reservist === null || $file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->to(site_url('my/profile'))->with('error', 'Choose a photo to upload.');
        }

        $storage = service('fileStorageService');

        try {
            $stored = $storage->storeAttachment($file, self::PHOTO_DIR, ['image/jpeg', 'image/png'], 'Your photo must be a JPG or PNG image.');
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('my/profile'))->with('error', $exception->getMessage());
        }

        $oldPath = $storage->attachmentPath(self::PHOTO_DIR, $reservist->profile_photo);

        (new ReservistModel())->update($reservist->id, ['id' => $reservist->id, 'profile_photo' => $stored['stored_filename']]);

        if ($oldPath !== null) {
            @unlink($oldPath);
        }

        service('auditLogService')->record($this->currentUserId(), 'reservist.photo_updated', 'reservist', $reservist->id);

        return redirect()->to(site_url('my/profile'))->with('success', 'Your photo has been updated.');
    }

    /**
     * Streams the reservist's own photo. Photos live under writable/, so the
     * web server never serves them directly.
     */
    public function photo(): ResponseInterface
    {
        $reservist = $this->ownReservist();
        $path      = service('fileStorageService')->attachmentPath(self::PHOTO_DIR, $reservist?->profile_photo);

        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        return self::imageResponse($this->response, $path);
    }

    /**
     * Shared with the staff photo route in ReservistsController.
     */
    public static function imageResponse(ResponseInterface $response, string $path): ResponseInterface
    {
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';

        return $response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    private function currentUserId(): ?int
    {
        $user = $this->currentUser();

        return $user !== null ? (int) $user->id : null;
    }
}
