<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - <?= SITE_TITLE ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <link href="<?= assets_url() ?>" rel="preconnect">
    <!-- Favicons -->
    <link href="<?= assets_url('img/logo.webp') ?>" rel="icon">
    <link href="<?= assets_url('img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&family=Ubuntu+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="<?= assets_url('vendor/bootstrap5.3.8/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/bootstrap-icons1.13.1/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('themes/admin/css/main.css') ?>" rel="stylesheet">
    <!-- =======================================================
      * Template Name: FlexAdmin - Bootstrap Admin Template
      * Template URL: https://bootstrapmade.com/flex-admin-bootstrap-template/
      * Updated: Jun 09 2026 with Bootstrap v5.3.8
      * Author: BootstrapMade.com
      * License: https://bootstrapmade.com/license/
      ======================================================== -->
</head>
<body>
<div class="fauth fauth-split">
    <aside class="fauth-visual">
        <a href="<?= base_url() ?>" class="fauth-logo">
            <img src="<?= assets_url('img/logo.webp') ?>" alt="<?= SITE_TITLE ?>" style="height:50px;">
            <?= SITE_TITLE ?>
        </a>
        <h2 class="fauth-visual-title">OtterNova Command Center</h2>
        <p class="fauth-visual-text">Own your work, be responsible, and break the leg!</p>
        <div class="fauth-visual-points">
            <div class="fauth-point"><i class="bi bi-graph-up-arrow"></i> Let’s aim for better tomorrow!</div>
            <div class="fauth-point"><i class="bi bi-shield-lock"></i> Be cautious and practice security at all time!</div>
            <div class="fauth-point"><i class="bi bi-lightning-charge"></i> We are in this together, let’s succeed together!</div>
        </div>
    </aside>
    <main class="fauth-main">
        <div class="fauth-main-inner">
            <?= $this->renderSection('content') ?>
            <footer class="footer-centered">
                <div class="footer-copyright">&copy; 2025-<?= date('Y') ?> <b>OtterNova</b>. All Rights Reserved.</div>
            </footer>
        </div>
    </main>
</div>
<!-- Ribbon -->
<?php $env = getenv('CI_ENVIRONMENT'); ?>
<?php if (in_array($env, ['beta', 'development'])): ?>
    <?php if ('development' == $env) { $env = 'dev'; } ?>
    <div class="corner-ribbon">
        <span><?= esc($env) ?></span>
    </div>
<?php endif; ?>
<!-- Vendor JS Files -->
<script src="<?= assets_url('vendor/bootstrap5.3.8/js/bootstrap.bundle.min.js') ?>"></script>
<!-- Template Main JS Files -->
<script src="<?= assets_url('themes/admin/js/theme.js') ?>"></script>
<script src="<?= assets_url('themes/admin/js/main.js') ?>"></script>
<?= $this->renderSection('script') ?>
</body>
</html>