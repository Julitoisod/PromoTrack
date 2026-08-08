<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
    <title><?= esc($title ?? 'PromoTrack AFP-RF') ?></title>

    <!-- Vendored locally rather than loaded from a CDN: Config/ContentSecurityPolicy
         restricts scripts and styles to 'self', and the system is expected to run on
         an intranet with no outbound internet access. -->
    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <!-- DataTables 1.13.8 (Bootstrap 5 theme) -->
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) ?>">
    <!-- SweetAlert2 11 — the split js+css build, so nothing injects a <style>
         element at runtime and style-src can stay at 'self'. -->
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/sweetalert2/sweetalert2.min.css')) ?>">
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

    <!-- Bootstrap 5.3.3 JS bundle -->
    <script src="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
    <!-- jQuery 3.7.1 (required by DataTables) -->
    <script src="<?= esc(base_url('assets/vendor/jquery/jquery.min.js')) ?>"></script>
    <!-- DataTables 1.13.8 -->
    <script src="<?= esc(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
    <!-- Chart.js 4.4.4 -->
    <script src="<?= esc(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
    <!-- SweetAlert2 11 -->
    <script src="<?= esc(base_url('assets/vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
    <!-- Application script -->
    <script {csp-script-nonce}>var baseUrl = <?= json_encode(base_url('/')) ?>;</script>
    <script src="<?= esc(base_url('assets/js/app.js')) ?>"></script>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
