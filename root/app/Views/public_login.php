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
                <div id="login-alert" class="alert d-none" role="alert"></div>
                <form id="login-form" class="fauth-form" action="<?= base_url('login') ?>" method="post" novalidate>
                    <div class="fauth-field">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@otternova.com" autocomplete="username" required>
                    </div>
                    <div class="fauth-field">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                </form>
                <form id="otp-form" class="fauth-form d-none" action="<?= base_url('verify-otp') ?>" method="post" novalidate>
                    <div class="fauth-field">
                        <label for="otp" class="form-label">Verification code</label>
                        <input type="text" class="form-control" id="otp" name="otp" placeholder="6-digit code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verify</button>
                    <button type="button" id="otp-back" class="btn btn-link w-100">Back to sign in</button>
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
<script>
(function () {
  'use strict';

  var loginForm = document.getElementById('login-form');
  var otpForm = document.getElementById('otp-form');
  var alertBox = document.getElementById('login-alert');

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  function hideAlert() {
    alertBox.className = 'alert d-none';
    alertBox.textContent = '';
  }

  function showStep(step) {
    loginForm.classList.toggle('d-none', step !== 'login');
    otpForm.classList.toggle('d-none', step !== 'otp');
    (step === 'otp' ? otpForm.otp : loginForm.email).focus();
  }

  function post(form) {
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    hideAlert();
    return fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (body) {
          return { code: res.status, body: body };
        });
      })
      .catch(function () {
        return { code: 0, body: { message: 'Network error. Please check your connection and try again.' } };
      })
      .finally(function () {
        button.disabled = false;
      });
  }

  function handleSuccess(body) {
    if (body.status === 'success' && body.redirect) {
      window.location.href = body.redirect;
      return true;
    }
    return false;
  }

  loginForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!loginForm.reportValidity()) {
      return;
    }
    post(loginForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      if (res.body.status === 'otp_required') {
        loginForm.password.value = '';
        otpForm.otp.value = '';
        showStep('otp');
        showAlert('info', res.body.message);
        return;
      }
      showAlert('danger', res.body.message || 'Unable to sign in. Please try again.');
    });
  });

  otpForm.addEventListener('submit', function (e) {
    e.preventDefault();
    otpForm.otp.value = otpForm.otp.value.replace(/\D/g, '');
    if (!otpForm.reportValidity()) {
      return;
    }
    post(otpForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      otpForm.otp.value = '';
      // 410: the verification has expired, so sign in again
      if (res.code === 410) {
        showStep('login');
      }
      showAlert('danger', res.body.message || 'The verification code is incorrect.');
    });
  });

  document.getElementById('otp-back').addEventListener('click', function () {
    hideAlert();
    showStep('login');
  });
})();
</script>
</body>
</html>