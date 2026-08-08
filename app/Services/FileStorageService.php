<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentVersionModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

/**
 * Stores promotion-package document uploads under `writable/uploads/`
 * (never `public/`), per the design doc's security checklist (§12):
 * randomized filename, original name kept only as metadata, MIME +
 * extension whitelist, size limit, and a SHA-256 checksum recorded for
 * every version.
 */
class FileStorageService
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private readonly DocumentVersionModel $documentVersionModel,
    ) {
    }

    /**
     * Validates and stores $file as a new version of $packageDocumentId,
     * returning the inserted `document_versions` row. Throws
     * RuntimeException with a user-facing message on any validation
     * failure (invalid upload, disallowed type, oversized file).
     */
    public function store(UploadedFile $file, int $packageDocumentId, int $uploadedByUserId): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The uploaded file could not be read: ' . $file->getErrorString());
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new RuntimeException('The uploaded file exceeds the 10 MB size limit.');
        }

        $mimeType  = (string) $file->getClientMimeType();
        $extension = strtolower((string) $file->getClientExtension());

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true) || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Only PDF, JPG, and PNG files are accepted.');
        }

        $checksum = hash_file('sha256', $file->getTempName());

        if ($checksum === false) {
            throw new RuntimeException('The uploaded file could not be checksummed.');
        }

        $destinationDir = WRITEPATH . 'uploads/package_documents/' . $packageDocumentId;

        if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
            throw new RuntimeException('The upload destination could not be created.');
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $originalName   = $file->getClientName();
        $sizeBytes      = $file->getSize();

        $file->move($destinationDir, $storedFilename);

        $storagePath = $destinationDir . DIRECTORY_SEPARATOR . $storedFilename;

        $versionNumber = $this->documentVersionModel->nextVersionNumber($packageDocumentId);

        $this->documentVersionModel->insert([
            'package_document_id' => $packageDocumentId,
            'version_number'      => $versionNumber,
            'stored_filename'     => $storedFilename,
            'original_filename'   => $originalName,
            'mime_type'           => $mimeType,
            'file_size_bytes'     => $sizeBytes,
            'sha256_checksum'     => $checksum,
            'storage_path'        => $storagePath,
            'uploaded_by'         => $uploadedByUserId,
            'uploaded_at'         => date('Y-m-d H:i:s'),
        ]);

        return $this->documentVersionModel->find($this->documentVersionModel->getInsertID());
    }
}
