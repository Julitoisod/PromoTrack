<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 6px 8px; border: 1px solid #999; text-align: left; }
        .notice { margin-top: 24px; font-size: 10px; color: #a00; border: 1px dashed #a00; padding: 8px; }
    </style>
</head>
<body>
    <h1>PROMOTION ORDER (DRAFT)</h1>
    <div class="subtitle">PromoTrack AFP-RF &mdash; Draft, not an official AFP document</div>

    <?php if ($order === null): ?>
        <p>Promotion order record not found.</p>
    <?php else: ?>
        <table>
            <tr><th>Order Number</th><td><?= esc($order['order_number']) ?></td></tr>
            <tr><th>Reservist</th><td><?= esc($order['last_name'] . ', ' . $order['first_name']) ?> (<?= esc($order['reservist_number']) ?>)</td></tr>
            <tr><th>New Rank</th><td><?= esc($order['new_rank_name']) ?></td></tr>
            <tr><th>Order Date</th><td><?= esc($order['order_date']) ?></td></tr>
            <tr><th>Effective Date</th><td><?= esc($order['effective_date']) ?></td></tr>
        </table>
    <?php endif; ?>

    <div class="notice">
        This is a system-generated draft. The official promotion order numbering scheme and required
        document format REQUIRE OFFICIAL AFP/PHILIPPINE ARMY VALIDATION before this draft may be treated
        as an authoritative record.
    </div>
</body>
</html>
