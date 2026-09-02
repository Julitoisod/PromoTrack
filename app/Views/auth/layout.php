<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $this->renderSection('title') ?></title>

    <?php // Overrides \CodeIgniter\Shield\Views\layout, which loads Bootstrap from a CDN.
          // Config/ContentSecurityPolicy restricts styles to 'self', so the CDN copy is
          // refused by the browser and the auth pages render unstyled.
          // auth.css replaces app.css here: the auth screens use none of the dashboard's
          // sidebar rules, and app.css offsets the body by the navbar height. ?>
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/auth.css')) ?>">

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="auth-page">

    <main role="main">
        <?= $this->renderSection('main') ?>
    </main>

    <script src="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
