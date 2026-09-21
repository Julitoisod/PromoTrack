<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\WorkflowStateMachine;
use App\Models\DocumentReviewModel;
use App\Models\DocumentVersionModel;
use App\Models\PackageDocumentModel;
use App\Models\WaiverRequestModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Upload, verify, reject, waive, and download individual promotion
 * package documents. Access is authorized via the parent package's
 * organizational unit (see `PackageDocumentModel::findWithPackage()`),
 * since a package_documents row has no unit column of its own.
 */
class PackageDocumentsController extends BaseController
{
    private PackageDocumentModel $packageDocumentModel;
    private DocumentVersionModel $documentVersionModel;
    private DocumentReviewModel $documentReviewModel;
    private WaiverRequestModel $waiverRequestModel;

    public function __construct()
    {
        $this->packageDocumentModel = new PackageDocumentModel();
        $this->documentVersionModel = new DocumentVersionModel();
        $this->documentReviewModel  = new DocumentReviewModel();
        $this->waiverRequestModel   = new WaiverRequestModel();
    }

    /**
     * The package statuses a document may still be uploaded against:
     * while the package is with the unit, and when the CDC or the RCDG
     * has sent it back. Checked here and not only in the view, or a
     * hand-made POST could replace a document the CDC is in the middle
     * of validating.
     *
     * @var list<string>
     */
    private const UPLOADABLE_PACKAGE_STATUSES = [
        'draft',
        'requirements_incomplete',
        'returned_to_unit',
        'returned_by_rcdg',
    ];

    public function upload(?int $packageDocumentId = null): RedirectResponse
    {
        $packageDocument = $this->findAccessibleOrFail($packageDocumentId);
        $file            = $this->request->getFile('document');
        $user            = $this->currentUser();

        if (! in_array($packageDocument['package_status'], self::UPLOADABLE_PACKAGE_STATUSES, true)) {
            return $this->backToPackage(
                $packageDocument,
                'error',
                'Documents can no longer be uploaded: this package has already left the unit.'
            );
        }

        if ($file === null) {
            return $this->backToPackage($packageDocument, 'error', 'No file was selected.');
        }

        try {
            $version = service('fileStorageService')->store(
                $file,
                (int) $packageDocument['id'],
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException $exception) {
            return $this->backToPackage($packageDocument, 'error', $exception->getMessage());
        }

        $this->packageDocumentModel->update((int) $packageDocument['id'], [
            'status'             => 'uploaded',
            'current_version_id' => $version['id'],
        ]);

        return $this->backToPackage($packageDocument, 'success', 'Document uploaded: ' . $packageDocument['document_code'] . ' - ' . $packageDocument['document_label']);
    }

    public function verify(?int $packageDocumentId = null): RedirectResponse
    {
        $packageDocument = $this->findAccessibleOrFail($packageDocumentId);

        if (($refusal = $this->refuseIfPackageIsClosed($packageDocument)) !== null) {
            return $refusal;
        }

        return $this->recordReviewDecision($packageDocument, 'verified');
    }

    public function reject(?int $packageDocumentId = null): RedirectResponse
    {
        $packageDocument = $this->findAccessibleOrFail($packageDocumentId);

        if (($refusal = $this->refuseIfPackageIsClosed($packageDocument)) !== null) {
            return $refusal;
        }

        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($remarks === '') {
            return $this->backToPackage($packageDocument, 'error', 'Remarks are required to reject a document.');
        }

        return $this->recordReviewDecision($packageDocument, 'rejected', $remarks);
    }

    public function waive(?int $packageDocumentId = null): RedirectResponse
    {
        $packageDocument = $this->findAccessibleOrFail($packageDocumentId);

        if (($refusal = $this->refuseIfPackageIsClosed($packageDocument)) !== null) {
            return $refusal;
        }

        $justification = trim((string) $this->request->getPost('justification'));

        if ($justification === '') {
            return $this->backToPackage($packageDocument, 'error', 'A justification is required to waive a document.');
        }

        $user = $this->currentUser();

        $this->waiverRequestModel->insert([
            'package_document_id' => $packageDocument['id'],
            'requested_by'        => $user !== null ? (int) $user->id : null,
            'justification'       => $justification,
            'approved_by'         => $user !== null ? (int) $user->id : null,
            'decision'            => 'approved',
            'decided_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->packageDocumentModel->update((int) $packageDocument['id'], ['status' => 'waived']);

        return $this->backToPackage($packageDocument, 'success', 'Document waived: ' . $packageDocument['document_code'] . ' - ' . $packageDocument['document_label']);
    }

    /**
     * Streams the current version of a package document. Never a
     * static/public URL -- served only through this authenticated,
     * authorized controller action, per the security checklist.
     */
    public function download(?int $documentVersionId = null): ResponseInterface
    {
        if ($documentVersionId === null) {
            throw new RuntimeException('A document version id is required.');
        }

        $version = $this->documentVersionModel->find($documentVersionId);

        if ($version === null) {
            throw new RuntimeException("Document version #{$documentVersionId} was not found.");
        }

        $packageDocument = $this->packageDocumentModel->findWithPackage((int) $version['package_document_id']);

        if ($packageDocument === null || ! $this->canAccessPackageRow($packageDocument)) {
            throw new RuntimeException('You are not authorized to access this document.');
        }

        return $this->response->download($version['storage_path'], null)
            ->setFileName($version['original_filename']);
    }

    /**
     * Refuses a review decision once the package has reached a terminal
     * status, returning the redirect to send back, or null to continue.
     *
     * `upload()` has its own, narrower gate (documents are frozen as soon
     * as the package leaves the unit). Verifying, rejecting, and waiving
     * stay open right through the CDC and RCDG stages, which is why they
     * are gated on terminality instead -- but they must still stop at the
     * end: a closed or disapproved package is a finished record, and a
     * verify recorded against it writes a `document_reviews` row dated
     * after the promotion order it supposedly justified.
     *
     * Terminality is `WorkflowStateMachine`'s to define, not this
     * controller's, so it is asked rather than re-listed here.
     */
    private function refuseIfPackageIsClosed(array $packageDocument): ?RedirectResponse
    {
        if (! (new WorkflowStateMachine())->isTerminal((string) $packageDocument['package_status'])) {
            return null;
        }

        return $this->backToPackage(
            $packageDocument,
            'error',
            'This package is already ' . str_replace('_', ' ', (string) $packageDocument['package_status'])
                . '; its documents can no longer be changed.'
        );
    }

    private function recordReviewDecision(array $packageDocument, string $decision, ?string $remarks = null): RedirectResponse
    {
        if ($packageDocument['current_version_id'] === null) {
            return $this->backToPackage($packageDocument, 'error', 'This document has no uploaded version to review.');
        }

        $user = $this->currentUser();

        $this->documentReviewModel->insert([
            'document_version_id' => $packageDocument['current_version_id'],
            'reviewed_by'         => $user !== null ? (int) $user->id : null,
            'decision'            => $decision,
            'remarks'             => $remarks,
            'reviewed_at'         => date('Y-m-d H:i:s'),
        ]);

        $this->packageDocumentModel->update((int) $packageDocument['id'], ['status' => $decision]);

        $label = $packageDocument['document_code'] . ' - ' . $packageDocument['document_label'];

        return $this->backToPackage(
            $packageDocument,
            $decision === 'verified' ? 'success' : 'info',
            ($decision === 'verified' ? 'Document verified: ' : 'Document rejected: ') . $label
        );
    }

    private function backToPackage(array $packageDocument, string $flashType, string $message): RedirectResponse
    {
        return redirect()->to(site_url('packages/' . $packageDocument['promotion_package_id']))
            ->with($flashType, $message);
    }

    private function findAccessibleOrFail(?int $id): array
    {
        if ($id === null) {
            throw new RuntimeException('A package document id is required.');
        }

        $packageDocument = $this->packageDocumentModel->findWithPackage($id);

        if ($packageDocument === null) {
            throw new RuntimeException("Package document #{$id} was not found.");
        }

        if (! $this->canAccessPackageRow($packageDocument)) {
            throw new RuntimeException('You are not authorized to access this promotion package.');
        }

        return $packageDocument;
    }
}
