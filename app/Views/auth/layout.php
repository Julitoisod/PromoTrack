<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $this->renderSection('title') ?></title>

    <?php // Overrides \CodeIgniter\Shield\Views\layout, which loads Bootstrap from a CDN.
          // Config/ContentSecurityPolicy restricts styles to 'self', so the CDN copy is
          // refused by the browser and the auth pages render unstyled. ?>
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app.css')) ?>">

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="bg-light">

    <main role="main" class="container">
        <?= $this->renderSection('main') ?>
    </main>

    <script src="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
