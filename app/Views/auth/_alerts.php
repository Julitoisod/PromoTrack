<?php // Flash messages, identical on every auth screen. ?>
<?php if (session('error') !== null) : ?>
    <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
<?php elseif (session('errors') !== null) : ?>
    <div class="alert alert-danger" role="alert">
        <?php if (is_array(session('errors'))) : ?>
            <?php foreach (session('errors') as $error) : ?>
                <?= esc($error) ?><br>
            <?php endforeach ?>
        <?php else : ?>
            <?= esc(session('errors')) ?>
        <?php endif ?>
    </div>
<?php endif ?>

<?php if (session('message') !== null) : ?>
    <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
<?php endif ?>
