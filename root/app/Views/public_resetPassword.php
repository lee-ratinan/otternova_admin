<?php $this->extend('_layout_public') ?>
<?= $this->section('title') ?>Reset Password<?php $this->endSection() ?>
<?= $this->section('content') ?>
            <div class="fauth-card">
                <div class="fauth-card-head">
                    <h1 class="fauth-title">Reset your password</h1>
                    <?php if ($valid): ?>
                        <p class="fauth-subtitle">Choose a new password for your OtterNova Admin account.</p>
                    <?php endif; ?>
                </div>
                <div id="reset-alert" class="alert <?= $valid ? 'd-none' : 'alert-danger' ?>" role="alert"><?= $valid ? '' : esc($error) ?></div>
                <?php if ($valid): ?>
                    <form id="reset-form" class="fauth-form" action="<?= base_url('reset-password') ?>" method="post" novalidate>
                        <div class="fauth-field">
                            <label for="new_password" class="form-label">New password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter a new password" minlength="8" autocomplete="new-password" required>
                            <div class="invalid-feedback" data-feedback="new_password">The new password must be at least 8 characters.</div>
                        </div>
                        <div class="fauth-field">
                            <label for="confirm_password" class="form-label">Confirm new password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Enter the new password again" minlength="8" autocomplete="new-password" required>
                            <div class="invalid-feedback" data-feedback="confirm_password">The new passwords do not match.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                <?php endif; ?>
                <a id="reset-request-new" href="<?= base_url('forgot-password') ?>" class="btn btn-primary w-100<?= $valid ? ' d-none' : '' ?>">Request a new link</a>
                <a href="<?= base_url() ?>" class="btn btn-link w-100">Back to sign in</a>
            </div>
<?php $this->endSection() ?>
<?php if ($valid): ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('reset-form');
  var alertBox = document.getElementById('reset-alert');

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  // the default message of each field's .invalid-feedback, put back once the server's own message is cleared
  var defaults = {};
  Array.prototype.forEach.call(form.querySelectorAll('[data-feedback]'), function (el) {
    defaults[el.getAttribute('data-feedback')] = el.textContent;
  });

  function setFieldError(name, message) {
    var feedback = form.querySelector('[data-feedback="' + name + '"]');
    if (!form.elements[name] || !feedback) {
      return;
    }
    feedback.textContent = message || defaults[name];
    form.elements[name].classList.toggle('is-invalid', message !== null);
  }

  Array.prototype.forEach.call(form.querySelectorAll('input'), function (input) {
    input.addEventListener('input', function () {
      input.setCustomValidity('');
      setFieldError(input.name, null);
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var confirm = form.confirm_password;
    confirm.setCustomValidity(confirm.value === form.new_password.value ? '' : 'The new passwords do not match.');
    var valid = true;
    Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (input) {
      var ok = input.checkValidity();
      setFieldError(input.name, ok ? null : '');
      valid = valid && ok;
    });
    if (!valid) {
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
        return res.json().catch(function () { return {}; }).then(function (body) {
          return { code: res.status, body: body };
        });
      })
      .catch(function () {
        return { code: 0, body: { message: 'Network error. Please check your connection and try again.' } };
      })
      .then(function (res) {
        button.disabled = false;
        if (res.body.status === 'success' && res.body.redirect) {
          window.location.href = res.body.redirect;
          return;
        }
        // 410: the link is used up or expired, so offer a new one instead of the form
        if (res.code === 410) {
          form.classList.add('d-none');
          document.getElementById('reset-request-new').classList.remove('d-none');
        }
        Object.keys(res.body.errors || {}).forEach(function (name) {
          setFieldError(name, res.body.errors[name]);
        });
        showAlert('danger', res.body.message || 'Unable to reset your password. Please try again.');
      });
  });
})();
</script>
<?php $this->endSection() ?>
<?php endif; ?>
