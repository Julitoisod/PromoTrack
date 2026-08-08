<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EndorsementModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates the AFP-formatted PDF outputs the thesis calls for: a
 * promotion order draft, an endorsement report, and the Promotion
 * Backlog Report (Objective 3 / Table 1's "Report Generation" and
 * "Backlog Report" functional requirements).
 *
 * Layouts here are plain, functional placeholders -- the thesis's own
 * design doc flags "official report layouts/letterheads" as
 * REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION (§14), so this
 * service does not invent a letterhead it has no authority to define.
 */
class ReportService
{
    public function __construct(
        private readonly PromotionOrderModel $promotionOrderModel,
        private readonly EndorsementModel $endorsementModel,
        private readonly PromotionAssessmentModel $promotionAssessmentModel,
    ) {
    }

    /**
     * Renders the promotion order draft for $promotionOrderId as a PDF
     * and returns the raw PDF bytes.
     */
    public function promotionOrderDraft(int $promotionOrderId): string
    {
        $order = $this->promotionOrderModel
            ->select('promotion_orders.*, reservists.first_name, reservists.last_name, reservists.reservist_number,'
                . ' ranks.rank_name AS new_rank_name')
            ->join('reservists', 'reservists.id = promotion_orders.reservist_id')
            ->join('ranks', 'ranks.id = promotion_orders.new_rank_id')
            ->find($promotionOrderId);

        return $this->renderPdf('reports/promotion_order_draft', ['order' => $order]);
    }

    /**
     * Renders the endorsement report for $promotionPackageId as a PDF.
     */
    public function endorsementReport(int $promotionPackageId): string
    {
        $endorsements = $this->endorsementModel->where('promotion_package_id', $promotionPackageId)->findAll();

        return $this->renderPdf('reports/endorsement_report', ['endorsements' => $endorsements]);
    }

    /**
     * Renders the Promotion Backlog Report -- every reservist whose
     * most recent assessment is `overdue`, optionally scoped to a
     * single organizational unit -- as a PDF.
     */
    public function backlogReport(?int $organizationalUnitId = null): string
    {
        $overdue = $this->promotionAssessmentModel->overdueReservists($organizationalUnitId);

        return $this->renderPdf('reports/backlog_report', ['overdue' => $overdue, 'generatedAt' => date('Y-m-d H:i')]);
    }

    private function renderPdf(string $viewName, array $data): string
    {
        // Letterhead and footer caveat come from system settings so an
        // administrator can change them without a code edit; see
        // Admin\SettingsController::EDITABLE.
        $data += [
            'organizationName' => (string) (setting('App.organizationName') ?? 'Philippine Army Reserve Force'),
            'reportFooterNote' => (string) (setting('App.reportFooterNote') ?? 'For official use only.'),
        ];

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($viewName, $data));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
