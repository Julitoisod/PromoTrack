<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1 class="h3 mb-4">Add Reservist</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?= esc(site_url('reservists')) ?>" method="post">
            <?= csrf_field() ?>
            <?= view('reservists/_form', [
                'reservist'        => null,
                'units'            => $units,
                'ranks'            => $ranks,
                'educationTracks'  => $educationTracks,
                'restrictedUnitId' => $restrictedUnitId,
            ]) ?>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Reservist
                </button>
                <a href="<?= esc(site_url('reservists')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
