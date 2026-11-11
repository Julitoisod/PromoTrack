<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><a href="<?= esc(site_url('/')) ?>">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reservist Classification</li>
    </ol>
</nav>

<div class="page-head">
    <div>
        <h1 class="page-title">Reservist Classification</h1>
        <p class="page-subtitle">Open a folder to see its personnel and assign or update their classification.</p>
    </div>
</div>

<?= view('admin/reservists/_classification_folders', ['counts' => $counts]) ?>

<p class="small text-muted mt-3 mb-0">
    A student reservist without an assigned classification is counted by what they reported on their ROTC Progress.
</p>
<?= $this->endSection() ?>
