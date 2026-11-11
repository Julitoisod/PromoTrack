<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><a href="<?= esc(site_url('/')) ?>">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Student Reservists</li>
    </ol>
</nav>

<div class="page-head">
    <div>
        <h1 class="page-title">Student Reservist Enrollment</h1>
        <p class="page-subtitle">Open a semester folder to see its enrolled students and verify their Certificates of Registration.</p>
    </div>
</div>

<?= view('admin/enrollment/_folders', ['folders' => $folders]) ?>
<?= $this->endSection() ?>
