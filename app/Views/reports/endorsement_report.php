<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .letterhead { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin-bottom: 2px; }
        .report-footer { margin-top: 24px; padding-top: 6px; border-top: 1px solid #999; text-align: center; font-size: 9px; color: #555; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 6px 8px; border: 1px solid #999; text-align: left; }
    </style>
</head>
<body>
    <div class="letterhead"><?= esc($organizationName) ?></div>
    <h1>ENDORSEMENT REPORT</h1>
    <div class="subtitle">PromoTrack AFP-RF</div>

    <?php if (empty($endorsements)): ?>
        <p>No endorsement records found for this package.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Reference #</th><th>Date</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($endorsements as $endorsement): ?>
                    <tr>
                        <td><?= esc($endorsement['endorsement_reference_number']) ?></td>
                        <td><?= esc($endorsement['endorsement_date']) ?></td>
                        <td><?= esc($endorsement['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="report-footer"><?= esc($reportFooterNote) ?></div>
</body>
</html>
