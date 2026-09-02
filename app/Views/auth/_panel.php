<?php
/**
 * Blue welcome panel shared by every auth screen.
 *
 * @var string $lede
 */
?>
<div class="col-12 col-md-5 auth-welcome">
    <h1>WELCOME</h1>
    <h2><?= esc(setting('App.organizationName') ?? 'Philippine Army Reserve Force') ?></h2>
    <p><?= esc($lede) ?></p>
</div>
