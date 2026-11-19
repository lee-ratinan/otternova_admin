<?php
/**
 * One message of the public contact form; see App\Controllers\Admin::inboxView(). The staff member reads it, replies by
 * email, marks it done, spam, or new again, and sets what it turned out to be (its category), which saves on change.
 * @var array $message From LINK_INTERNAL_API v1/staff/inbox/message
 */

use App\Controllers\Admin;
use App\Controllers\ClientBilling as B;

$this->extend('_layout');
$m      = $message;
$status = (string) ($m['status'] ?? '');
$badge  = Admin::MESSAGE_STATUSES[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
$email  = (string) ($m['email_address'] ?? '');
$reply  = 'mailto:' . str_replace(['?', '&', '#'], '', $email) . '?subject=' . rawurlencode('Re: ' . ($m['subject'] ?? ''));
$rows   = [
    'From'     => esc((string) ($m['name'] ?? '')) . ' &lt;<a href="' . esc($reply, 'attr') . '">' . esc($email) . '</a>&gt;',
    'Phone'    => empty($m['phone_number']) ? '<span class="text-muted">&mdash;</span>'
        : '<a href="tel:' . esc((string) $m['phone_number'], 'attr') . '">' . esc((string) $m['phone_number']) . '</a>',
    'Language' => '<code>' . esc((string) ($m['locale'] ?? '')) . '</code>',
    'Received' => B::time($m['created_at'] ?? null),
    'Status'   => '<span class="badge ' . esc($badge['class'], 'attr') . '">' . esc($badge['label']) . '</span>'
        . (empty($m['spam_reason']) ? '' : ' <span class="text-secondary small">flagged by the form: ' . esc((string) $m['spam_reason']) . '</span>'),
    'Handled'  => empty($m['handled_at']) ? '<span class="text-muted">&mdash;</span>'
        : esc((string) ($m['handled_by_name'] ?? '')) . ' <span class="text-secondary">·</span> ' . B::time($m['handled_at']),
    'First Read' => empty($m['first_read_at']) ? '<span class="text-muted">&mdash;</span>' : B::time($m['first_read_at']),
    'Resolved'   => empty($m['resolved_at']) ? '<span class="text-muted">&mdash;</span>'
        : B::time($m['resolved_at']) . ' <span class="text-secondary">· in ' . esc(formatDuration(isset($m['response_seconds']) ? (int) $m['response_seconds'] : null)) . '</span>',
];
$category = (string) ($m['message_category'] ?? '');
// the statuses this message can be marked with, besides its own; opening it already marked it read
$actions = array_filter([
    'D' => ['label' => 'Mark Done', 'icon' => 'bi-check2-circle', 'class' => 'btn-success'],
    'S' => ['label' => 'Mark Spam', 'icon' => 'bi-shield-exclamation', 'class' => 'btn-outline-secondary'],
    'R' => ['label' => 'Not Spam', 'icon' => 'bi-envelope-open', 'class' => 'btn-outline-primary'],
    'N' => ['label' => 'Mark Unread', 'icon' => 'bi-envelope', 'class' => 'btn-outline-primary'],
], static fn (string $code): bool => $code !== $status && ('R' !== $code || 'S' === $status), ARRAY_FILTER_USE_KEY);
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title"><?= esc((string) ($m['subject'] ?? '')) ?></h5>
                        <p class="card-subtitle">Message #<?= (int) ($m['id'] ?? 0) ?></p>
                    </div>
                    <div class="card-body">
                        <div class="border rounded p-3 bg-body-tertiary" style="white-space: pre-wrap; overflow-wrap: anywhere"><?= esc((string) ($m['message'] ?? '')) ?></div>
                    </div>
                    <div class="card-footer d-flex flex-wrap gap-2 justify-content-end" data-message="<?= (int) ($m['id'] ?? 0) ?>">
                        <a class="btn btn-primary me-auto" href="<?= esc($reply, 'attr') ?>"><i class="bi bi-reply"></i> Reply by Email</a>
                        <?php foreach ($actions as $code => $action): ?>
                            <button type="button" class="btn <?= esc($action['class'], 'attr') ?>" data-status="<?= esc($code, 'attr') ?>">
                                <i class="bi <?= esc($action['icon'], 'attr') ?>"></i> <?= esc($action['label']) ?>
                            </button>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Detail</h5>
                        <p class="card-subtitle">Every time is in your timezone</p>
                    </div>
                    <div class="card-body">
                        <?php foreach ($rows as $label => $html): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end"><?= esc($label) ?></div>
                                <div class="col-sm-8"><?= $html ?></div>
                            </div>
                        <?php endforeach ?>
                        <div class="row mb-3">
                            <label class="col-sm-4 text-sm-end" for="messageCategory">Category</label>
                            <div class="col-sm-8">
                                <select class="form-select form-select-sm" id="messageCategory" data-category>
                                    <option value=""<?= $category === '' ? ' selected' : '' ?>>Not categorised</option>
                                    <?php foreach (Admin::MESSAGE_CATEGORIES as $code => $option): ?>
                                        <option value="<?= esc($code, 'attr') ?>" title="<?= esc($option['help'], 'attr') ?>"<?= $code === $category ? ' selected' : '' ?>><?= esc($option['label']) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <div class="form-text">What the message turned out to be; saved when changed</div>
                            </div>
                        </div>
                        <hr>
                        <p class="small text-secondary mb-1">Kept for spam review only</p>
                        <div class="small">IP address: <code><?= esc((string) ($m['ip_address'] ?? '')) ?></code></div>
                        <div class="small text-break">Browser: <?= esc((string) ($m['user_agent'] ?? '—')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    (function () {
      'use strict';
      var url = <?= json_encode(base_url('admin/inbox/status'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
      var categoryUrl = <?= json_encode(base_url('admin/inbox/category'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
      var list = <?= json_encode(base_url('admin/inbox'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
      var csrf = { name: <?= json_encode(csrf_token(), JSON_HEX_TAG) ?>, hash: <?= json_encode(csrf_hash(), JSON_HEX_TAG) ?> };
      var bar = document.querySelector('[data-message]');
      var buttons = bar.querySelectorAll('[data-status]');

      function notify(type, message) {
        if (window.toastr) { window.toastr[type](message); } else { window.alert(message); }
      }

      buttons.forEach(function (button) {
        button.addEventListener('click', async function () {
          var status = button.getAttribute('data-status');
          var body = new FormData();
          body.append('id', bar.getAttribute('data-message'));
          body.append('status', status);
          body.append(csrf.name, csrf.hash);
          buttons.forEach(function (b) { b.disabled = true; });
          try {
            var response = await fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            var data = await response.json();
            if (data.csrf_hash) { csrf.hash = data.csrf_hash; }
            if (response.ok && data.success) {
              notify('success', data.message);
              // done, spam and unread leave the message; back to the inbox for the next one
              setTimeout(function () { if (status === 'R') { window.location.reload(); } else { window.location.href = list; } }, 800);
              return;
            }
            notify('error', data.message || 'Could not update the message. Please try again.');
          } catch (e) {
            notify('error', 'Could not update the message. Please try again.');
          }
          buttons.forEach(function (b) { b.disabled = false; });
        });
      });

      var category = document.querySelector('[data-category]');
      var saved = category.value;
      category.addEventListener('change', async function () {
        var body = new FormData();
        body.append('id', bar.getAttribute('data-message'));
        body.append('category', category.value);
        body.append(csrf.name, csrf.hash);
        category.disabled = true;
        try {
          var response = await fetch(categoryUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
          var data = await response.json();
          if (data.csrf_hash) { csrf.hash = data.csrf_hash; }
          if (response.ok && data.success) {
            saved = category.value;
            notify('success', data.message);
          } else {
            category.value = saved;
            notify('error', data.message || 'Could not update the message. Please try again.');
          }
        } catch (e) {
          category.value = saved;
          notify('error', 'Could not update the message. Please try again.');
        }
        category.disabled = false;
      });
    })();
    </script>
<?= $this->endSection() ?>
