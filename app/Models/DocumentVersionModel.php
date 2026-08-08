<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DocumentVersionModel extends Model
{
    protected $table            = 'document_versions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'package_document_id',
        'version_number',
        'stored_filename',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'sha256_checksum',
        'storage_path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'package_document_id' => 'required|is_natural_no_zero',
        'stored_filename'     => 'required|max_length[255]',
        'original_filename'   => 'required|max_length[255]',
        'mime_type'           => 'required|max_length[150]',
        'sha256_checksum'     => 'required|exact_length[64]',
        'storage_path'        => 'required|max_length[255]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function nextVersionNumber(int $packageDocumentId): int
    {
        $latest = $this->where('package_document_id', $packageDocumentId)
            ->orderBy('version_number', 'DESC')
            ->first();

        return $latest !== null ? ((int) $latest['version_number']) + 1 : 1;
    }
}
