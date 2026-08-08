<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PackageDocumentModel extends Model
{
    protected $table            = 'package_documents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'document_requirement_id',
        'status',
        'current_version_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'promotion_package_id'    => 'required|is_natural_no_zero',
        'document_requirement_id' => 'required|is_natural_no_zero',
        'status'                  => 'permit_empty|in_list[pending,uploaded,verified,rejected,waived]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Returns a single package_documents row joined with its parent
     * package's organizational_unit_id/reservist_id (for authorization)
     * and its requirement's code/label (for flash-message display), so
     * a controller can authorize and describe the item in one query.
     */
    public function findWithPackage(int $packageDocumentId): ?array
    {
        return $this->select(
            'package_documents.*, promotion_packages.organizational_unit_id, promotion_packages.reservist_id,'
            . ' promotion_packages.status AS package_status, document_requirements.document_code,'
            . ' document_requirements.document_label'
        )
            ->join('promotion_packages', 'promotion_packages.id = package_documents.promotion_package_id')
            ->join('document_requirements', 'document_requirements.id = package_documents.document_requirement_id')
            ->where('package_documents.id', $packageDocumentId)
            ->first();
    }

    /**
     * Returns every package_documents row for $promotionPackageId,
     * joined with its requirement's code/label/conditionality, ordered
     * for checklist display.
     */
    public function checklistFor(int $promotionPackageId): array
    {
        return $this->select(
            'package_documents.*, document_requirements.document_code, document_requirements.document_label,'
            . ' document_requirements.is_conditional, document_requirements.condition_expression,'
            . ' document_requirements.allows_commander_override'
        )
            ->join('document_requirements', 'document_requirements.id = package_documents.document_requirement_id')
            ->where('package_documents.promotion_package_id', $promotionPackageId)
            ->orderBy('document_requirements.display_order', 'ASC')
            ->orderBy('document_requirements.document_code', 'ASC')
            ->findAll();
    }
}
