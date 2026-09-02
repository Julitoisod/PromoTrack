<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-title">Edit Reservist</h1>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= esc(site_url('reservists/' . $reservist->id)) ?>" method="post">
            <?= csrf_field() ?>
            <?= view('reservists/_form', [
                'reservist'        => $reservist,
                'units'            => $units,
                'ranks'            => $ranks,
                'educationTracks'  => $educationTracks,
                'restrictedUnitId' => $restrictedUnitId,
            ]) ?>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Reservist
                </button>
                <a href="<?= esc(site_url('reservists/' . $reservist->id)) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
