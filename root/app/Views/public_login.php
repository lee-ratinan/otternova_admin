<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_TITLE ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <!-- Favicons -->
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="icon">
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts - Manrope + Sora + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&family=Ubuntu+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/remixicon/remixicon.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/phosphor-icons/phosphor-icons.css') ?>') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/lucide-icons/lucide.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/simple-datatables/style.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.snow.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.bubble.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/choices.js/choices.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/flatpickr/flatpickr.min.css') ?>" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet">
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
            <img src="<?= base_url('assets/img/logo.webp') ?>" alt="<?= SITE_TITLE ?>" style="height:50px;">
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
            <div class="fauth-card">
                <div class="fauth-card-head">
                    <h1 class="fauth-title">Welcome back</h1>
                    <p class="fauth-subtitle">Sign in continue to your OtterNova Admin workspace.</p>
                </div>
                <form class="fauth-form" novalidate>
                    <div class="fauth-field">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@otternova.com" required>
                    </div>
                    <div class="fauth-field">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-primary w-100">Sign In</a>
                </form>
                <p class="fauth-foot-text">Don’t have your account? Forget your password? Tell your boss!</p>
            </div>
            <footer class="footer-centered">
                <div class="footer-copyright">&copy; 2025-<?= date('Y') ?> <b>OtterNova</b>. All Rights Reserved.</div>
            </footer>
        </div>
    </main>
</div>
<!-- Vendor JS Files -->
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/apexcharts/apexcharts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/chart.js/chart.umd.js') ?>"></script>
<script src="<?= base_url('assets/vendor/echarts/echarts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/simple-datatables/simple-datatables.js') ?>"></script>
<script src="<?= base_url('assets/vendor/quill/quill.js') ?>"></script>
<script src="<?= base_url('assets/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/choices.js/choices.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/php-email-form/validate.js') ?>"></script>
<!-- Template Main JS Files -->
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
<!-- App Sidebar Toggle (for app pages with sidebars) -->
<script src="<?= base_url('assets/js/apps-sidebar-toggle.js') ?>"></script>
</body>
</html>