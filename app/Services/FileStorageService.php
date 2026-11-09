<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentVersionModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Mimes;
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
        [$mimeType, $extension] = $this->acceptedType($file, self::ALLOWED_MIME_TYPES, 'Only PDF, JPG, and PNG files are accepted.');

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

    /**
     * Stores a file that is not a package document version -- a profile
     * photo, an ROTC justification letter -- under
     * `writable/uploads/{$subdirectory}/`, with the same checks as store():
     * type detected from the file's own bytes, randomized name, size cap.
     *
     * @param list<string> $allowedMimeTypes subset of the PDF/JPEG/PNG whitelist
     *
     * @return array{stored_filename: string, original_filename: string, mime_type: string}
     */
    public function storeAttachment(UploadedFile $file, string $subdirectory, array $allowedMimeTypes, string $typeMessage): array
    {
        $allowed = array_values(array_intersect($allowedMimeTypes, self::ALLOWED_MIME_TYPES));

        [$mimeType, $extension] = $this->acceptedType($file, $allowed, $typeMessage);

        $destinationDir = WRITEPATH . 'uploads/' . trim($subdirectory, '/');

        if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
            throw new RuntimeException('The upload destination could not be created.');
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $originalName   = $file->getClientName();

        $file->move($destinationDir, $storedFilename);

        return [
            'stored_filename'   => $storedFilename,
            'original_filename' => $originalName,
            'mime_type'         => $mimeType,
        ];
    }

    /**
     * Absolute path of a file stored by storeAttachment(), or null when the
     * name is not a plain stored filename or the file is gone. The name comes
     * from the database, but it is still checked so it can never walk out of
     * the upload directory.
     */
    public function attachmentPath(string $subdirectory, ?string $storedFilename): ?string
    {
        if ($storedFilename === null || preg_match('/^[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$/', $storedFilename) !== 1) {
            return null;
        }

        $path = WRITEPATH . 'uploads/' . trim($subdirectory, '/') . DIRECTORY_SEPARATOR . $storedFilename;

        return is_file($path) ? $path : null;
    }

    /**
     * Validates the upload and returns [mime type, extension]. Throws
     * RuntimeException with a user-facing message when it is refused.
     *
     * @param list<string> $allowedMimeTypes
     *
     * @return array{0: string, 1: string}
     */
    private function acceptedType(UploadedFile $file, array $allowedMimeTypes, string $typeMessage): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The uploaded file could not be read: ' . $file->getErrorString());
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new RuntimeException('The uploaded file exceeds the 10 MB size limit.');
        }

        // The whitelist decision is made from the file's own bytes. The browser
        // also sends a Content-Type and a filename, but both are attacker-
        // controlled strings; they are kept as metadata only.
        $mimeType  = $this->detectMimeType($file->getTempName());
        $extension = strtolower((string) Mimes::guessExtensionFromType($mimeType));

        if ($mimeType === ''
            || ! in_array($mimeType, $allowedMimeTypes, true)
            || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)
        ) {
            throw new RuntimeException($typeMessage);
        }

        return [$mimeType, $extension];
    }

    /**
     * Returns the MIME type detected from the file's contents, or an empty
     * string when it cannot be determined.
     *
     * Deliberately not `UploadedFile::getMimeType()`: that calls finfo_file()
     * unguarded, and libmagic on Windows/XAMPP returns false outright for some
     * inputs -- notably a file whose contents begin with `<?php`, exactly the
     * upload this whitelist exists to stop. The resulting PHP warning is
     * promoted to an ErrorException by the framework, so a crafted upload
     * produced a 500 instead of a clean refusal. Here a failed detection is
     * simply an unknown type, and an unknown type is refused by the caller.
     */
    private function detectMimeType(string $path): string
    {
        if (! function_exists('finfo_open')) {
            return '';
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return '';
        }

        $detected = @finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($detected) ? strtolower($detected) : '';
    }
}
