<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FlexAdmin</title>
    <meta name="description" content="FlexAdmin - Bootstrap Admin Template">
    <meta name="keywords" content="admin, dashboard, bootstrap">
    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    <!-- Google Fonts - Manrope + Sora + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&family=Ubuntu+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="assets/vendor/phosphor-icons/phosphor-icons.css" rel="stylesheet">
    <link href="assets/vendor/lucide-icons/lucide.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="assets/vendor/choices.js/choices.min.css" rel="stylesheet">
    <link href="assets/vendor/flatpickr/flatpickr.min.css" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">
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
        <a href="index.html" class="fauth-logo">
            <img src="assets/img/logo.webp" alt="FlexAdmin">
            <span>FlexAdmin</span>
        </a>
        <span class="fauth-kicker">Workspace Intelligence</span>
        <h2 class="fauth-visual-title">Command your operations from one modern control center.</h2>
        <p class="fauth-visual-text">Track growth, team activity, and operational risk with a dashboard built for fast decisions.</p>
        <div class="fauth-visual-points">
            <div class="fauth-point"><i class="bi bi-graph-up-arrow"></i> Real-time business insights</div>
            <div class="fauth-point"><i class="bi bi-shield-lock"></i> Enterprise-grade account protection</div>
            <div class="fauth-point"><i class="bi bi-lightning-charge"></i> Fast collaboration workflows</div>
        </div>
    </aside>
    <main class="fauth-main">
        <div class="fauth-main-inner">
            <div class="fauth-card">
                <div class="fauth-card-head">
                    <h1 class="fauth-title">Welcome back</h1>
                    <p class="fauth-subtitle">Sign in to continue to your FlexAdmin workspace.</p>
                </div>
                <form class="fauth-form" novalidate>
                    <div class="fauth-field">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    </div>

                    <div class="fauth-field">
                        <div class="fauth-row-between">
                            <label for="password" class="form-label">Password</label>
                            <a href="auth-forgot-password.html" class="fauth-link">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="fauth-row-between mb-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="auth-lock-screen.html" class="fauth-link">Use lock screen</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Sign In</button>

                    <div class="fauth-divider"><span>or continue with</span></div>

                    <div class="fauth-social">
                        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-google"></i> Google</button>
                        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-github"></i> GitHub</button>
                    </div>
                </form>

                <p class="fauth-foot-text">Don't have an account? <a href="auth-register.html" class="fauth-link">Create one</a></p>
            </div>

            <footer class="footer-centered">
                <div class="footer-copyright">&copy; 2026 <a href="#">FlexAdmin</a>. All Rights Reserved.</div>
                <div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Help</a></div>
            </footer>
        </div>
    </main>
</div>
<!-- Vendor JS Files -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/quill/quill.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/choices.js/choices.min.js"></script>
<script src="assets/vendor/flatpickr/flatpickr.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<!-- Template Main JS Files -->
<script src="assets/js/theme.js"></script>
<script src="assets/js/main.js"></script>
<!-- App Sidebar Toggle (for app pages with sidebars) -->
<script src="assets/js/apps-sidebar-toggle.js"></script>
</body>
</html>