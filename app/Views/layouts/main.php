<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
    <title><?= esc($title ?? 'PromoTrack AFP-RF') ?></title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables (Bootstrap 5 theme) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <!-- Application styles -->
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css')) ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body>
    <?= view('components/navbar') ?>
    <?= view('components/sidebar') ?>

    <main class="main-content">
        <?= view('components/breadcrumbs', ['breadcrumbs' => $breadcrumbs ?? []]) ?>
        <?= view('components/alerts') ?>

        <?= $this->renderSection('content') ?>
    </main>

    <!-- Bootstrap 5.3 JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (required by DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Application script -->
    <script>var baseUrl = <?= json_encode(base_url('/')) ?>;</script>
    <script src="<?= esc(base_url('assets/js/app.js')) ?>"></script>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
