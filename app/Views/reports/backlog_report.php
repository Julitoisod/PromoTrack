<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        .letterhead { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin-bottom: 2px; }
        .report-footer { margin-top: 24px; padding-top: 6px; border-top: 1px solid #999; text-align: center; font-size: 9px; color: #555; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 10px; color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 5px 6px; border: 1px solid #999; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <div class="letterhead"><?= esc($organizationName) ?></div>
    <h1>PROMOTION BACKLOG REPORT</h1>
    <div class="subtitle">Overdue reservists by grade and educational track &mdash; generated <?= esc($generatedAt) ?></div>

    <?php if (empty($overdue)): ?>
        <p>No overdue promotions on file.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reservist #</th>
                    <th>Name</th>
                    <th>Unit</th>
                    <th>Current Rank</th>
                    <th>Education Track</th>
                    <th>Time in Grade</th>
                    <th>Overdue By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overdue as $row): ?>
                    <?php
                    $overdueMonths = App\Models\PromotionAssessmentModel::timeInGradeCriterion($row)['overdue_months'] ?? null;
                    ?>
                    <tr>
                        <td><?= esc($row['reservist_number']) ?></td>
                        <td><?= esc($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= esc($row['unit_name'] ?? '—') ?></td>
                        <td><?= esc($row['rank_name'] ?? '—') ?></td>
                        <td><?= esc($row['track_name'] ?? '—') ?></td>
                        <td><?= esc((string) $row['time_in_grade_months']) ?> mo(s)</td>
                        <td><?= $overdueMonths !== null ? esc((string) $overdueMonths) . ' mo(s)' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="report-footer"><?= esc($reportFooterNote) ?></div>
</body>
</html>
