<?php $this->extend('_layout_public') ?>
<?= $this->section('title') ?>Login<?php $this->endSection() ?>
<?= $this->section('content') ?>
            <div class="fauth-card">
                <div class="fauth-card-head">
                    <h1 class="fauth-title">Welcome back</h1>
                    <p class="fauth-subtitle">Sign in continue to your OtterNova Admin workspace.</p>
                </div>
                <?php $authAlert = session()->getFlashdata('auth_alert'); ?>
                <?php if (is_array($authAlert) && count($authAlert) === 2): ?>
                    <div id="login-alert" class="alert alert-<?= esc($authAlert[0], 'attr') ?>" role="alert"><?= esc($authAlert[1]) ?></div>
                <?php else: ?>
                    <div id="login-alert" class="alert d-none" role="alert"></div>
                <?php endif; ?>
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
                <form id="password-form" class="fauth-form d-none" action="<?= base_url('change-password') ?>" method="post" novalidate>
                    <div class="fauth-field">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter a new password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="fauth-field">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Enter the new password again" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Change Password</button>
                    <button type="button" id="password-back" class="btn btn-link w-100">Back to sign in</button>
                </form>
                <p class="fauth-foot-text">Don’t have your account? Tell your boss! | Forgot your password, <a href="<?= base_url('forgot-password') ?>" class="fauth-link">reset now</a></p>
            </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var loginForm = document.getElementById('login-form');
  var otpForm = document.getElementById('otp-form');
  var passwordForm = document.getElementById('password-form');
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
    passwordForm.classList.toggle('d-none', step !== 'password');
    ({ login: loginForm.email, otp: otpForm.otp, password: passwordForm.new_password })[step].focus();
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
    // the password has expired: no session until a new one is set
    if (body.status === 'password_change_required') {
      loginForm.password.value = '';
      otpForm.otp.value = '';
      passwordForm.reset();
      showStep('password');
      showAlert('warning', body.message);
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

  passwordForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var confirm = passwordForm.confirm_password;
    confirm.setCustomValidity(confirm.value === passwordForm.new_password.value ? '' : 'The new passwords do not match.');
    if (!passwordForm.reportValidity()) {
      return;
    }
    post(passwordForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      // 410: the password change has expired, so sign in again
      if (res.code === 410) {
        passwordForm.reset();
        showStep('login');
      }
      showAlert('danger', res.body.message || 'Unable to change your password. Please try again.');
    });
  });

  passwordForm.confirm_password.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  ['otp-back', 'password-back'].forEach(function (id) {
    document.getElementById(id).addEventListener('click', function () {
      passwordForm.reset();
      hideAlert();
      showStep('login');
    });
  });
})();
</script>
<?php $this->endSection() ?>
