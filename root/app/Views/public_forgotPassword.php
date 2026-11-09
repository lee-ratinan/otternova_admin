<?php $this->extend('_layout_public') ?>
<?= $this->section('title') ?>Forgot Password<?php $this->endSection() ?>
<?= $this->section('content') ?>
            <div class="fauth-card">
                <div class="fauth-card-head">
                    <h1 class="fauth-title">Forgot your password?</h1>
                    <p class="fauth-subtitle">Enter the email address of your OtterNova Admin account and we will send you a link to reset its password.</p>
                </div>
                <div id="forgot-alert" class="alert d-none" role="alert"></div>
                <form id="forgot-form" class="fauth-form" action="<?= base_url('forgot-password') ?>" method="post" novalidate>
                    <div class="fauth-field">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@otternova.com" maxlength="128" autocomplete="email" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>
                <a href="<?= base_url() ?>" class="btn btn-link w-100">Back to sign in</a>
            </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('forgot-form');
  var alertBox = document.getElementById('forgot-alert');

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  form.email.addEventListener('input', function () {
    this.classList.remove('is-invalid');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    form.email.value = form.email.value.trim();
    var ok = form.email.checkValidity();
    form.email.classList.toggle('is-invalid', !ok);
    if (!ok) {
      return;
    }
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    alertBox.className = 'alert d-none';
    fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; });
      })
      .catch(function () {
        return { message: 'Network error. Please check your connection and try again.' };
      })
      .then(function (body) {
        button.disabled = false;
        if (body.status === 'success') {
          // same message whether or not the address has an account; the next step is the link in the email
          form.classList.add('d-none');
          showAlert('success', body.message);
          return;
        }
        showAlert('danger', body.message || 'Unable to send the reset link. Please try again.');
      });
  });
})();
</script>
<?php $this->endSection() ?>
