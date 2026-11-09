<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-title"><?= esc($title) ?></h1>
</div>

<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-person-exclamation"></i>
            <p class="mb-1">Your account is not linked to a reservist record yet.</p>
            <p class="text-muted mb-0">
                Ask your unit S1 clerk to check that the serial number on your
                record matches the one you signed up with.
            </p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
