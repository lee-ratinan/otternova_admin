<?php
/**
 * One invoice with its payments; a payment waiting for review can be approved or rejected here.
 * See App\Controllers\ClientBilling::invoiceView()
 * @var array $invoice From LINK_INTERNAL_API v1/staff/subscriptions/invoice, with its payments oldest first
 */

use App\Controllers\ClientBilling as B;

$this->extend('_layout');
$i        = $invoice;
$payments = (array) ($i['payments'] ?? []);
$money    = static fn ($amount): string => esc(B::money($i['currency_code'] ?? null, $amount));
$rows     = [
    'Invoice No.'  => '<strong>' . esc((string) ($i['invoice_no'] ?? '')) . '</strong>',
    'Status'       => B::badge(B::INVOICE_STATUSES, $i['invoice_status'] ?? null),
    'Business'     => esc(B::businessLabel($i)) . ' <span class="text-secondary">· ' . esc((string) ($i['business_country_code'] ?? '')) . '</span>',
    'Subscription' => '<a href="' . esc(base_url('admin/clients/subscriptions/view/' . rawurlencode((string) ($i['subscription_public_id'] ?? ''))), 'attr') . '">'
        . esc(($i['plan_code'] ?? '') . ' · ' . (B::BILLING_CYCLES[$i['billing_cycle'] ?? ''] ?? '')) . '</a> '
        . B::badge(B::SUBSCRIPTION_STATUSES, $i['subscription_status'] ?? null),
    'Period'       => B::period($i['period_start'] ?? null, $i['period_end'] ?? null),
    'List price'   => $money($i['list_price'] ?? null),
    'Discount'     => empty($i['discount_code']) ? '<span class="text-muted">&mdash;</span>'
        : '− ' . $money($i['discount_amount'] ?? null) . ' <code>' . esc($i['discount_code']) . '</code>',
    'Amount due'   => '<strong>' . $money($i['amount_due'] ?? null) . '</strong>',
];
$isTopUp  = 'W' === ($i['invoice_type'] ?? 'S');
$withheld = ! empty($i['wht_amount']) && 0.0 !== (float) $i['wht_amount'];
if ($isTopUp) {
    $rows = ['Top-up of' => '<span class="badge bg-danger">Withholding tax top-up</span> of invoice '
        . '<a href="' . esc(base_url('admin/clients/invoices/view/' . rawurlencode((string) ($i['parent_invoice_public_id'] ?? ''))), 'attr') . '">'
        . esc((string) ($i['parent_invoice_no'] ?? '')) . '</a>, whose certificate never came'] + $rows;
    unset($rows['Period'], $rows['List price'], $rows['Discount']);
}
if ($withheld) {
    $rows['Withholding tax'] = '− ' . $money($i['wht_amount']) . ' <span class="text-secondary">(' . esc((string) ($i['wht_rate'] ?? '')) . '%)</span>';
    $rows['To pay']          = '<strong>' . $money($i['amount_payable'] ?? null) . '</strong>';
    $rows['Certificate']     = 'N' === ($i['wht_cert_status'] ?? 'N') && 'P' === ($i['invoice_status'] ?? '')
        ? '<span class="text-secondary">Wanted once paid</span>'
        : B::badge(B::CERT_STATUSES, $i['wht_cert_status'] ?? null)
            . (empty($i['wht_cert_due_at']) ? '' : ' <span class="text-secondary">due</span> ' . B::time($i['wht_cert_due_at']));
}
$rows['Due'] = B::time($i['due_at'] ?? null);
$certificates = (array) ($i['certificates'] ?? []);
if (! empty($i['paid_at'])) {
    $rows['Paid'] = B::time($i['paid_at']);
}
if (! empty($i['voided_at'])) {
    $rows['Voided'] = B::time($i['voided_at']);
}
$rows['Issued']    = B::time($i['created_at'] ?? null);
$rows['Documents'] = B::documentLinks($i)
    . '<div class="small text-secondary">The same pages the business gets; open one to print it or save it as PDF</div>';
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Invoice Detail</h5>
                        <p class="card-subtitle">Every time is in your timezone</p>
                    </div>
                    <div class="card-body">
                        <?php foreach ($rows as $label => $html): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end"><?= esc($label) ?></div>
                                <div class="col-sm-8"><?= $html ?></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <?php foreach ($payments as $payment): ?>
                    <?php if ('P' !== ($payment['txn_status'] ?? '')) { continue; } ?>
                    <?php $slipUrl = base_url('admin/clients/invoices/slip/' . rawurlencode($payment['public_id'])); ?>
                    <div class="card mb-4 border-info" data-review="<?= esc($payment['public_id'], 'attr') ?>">
                        <div class="card-header">
                            <h5 class="card-title">Payment Waiting for Review</h5>
                            <p class="card-subtitle">
                                <?= esc(B::money($payment['currency_code'] ?? null, $payment['amount'] ?? null)) ?> by <code><?= esc((string) ($payment['method_code'] ?? '')) ?></code>,
                                sent <?= B::time($payment['created_at'] ?? null) ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <?php if (! empty($payment['has_slip'])): ?>
                                <div class="mb-3">
                                    <a href="<?= esc($slipUrl, 'attr') ?>" target="_blank" rel="noopener">
                                        <img src="<?= esc($slipUrl, 'attr') ?>" alt="Payment slip" class="img-fluid rounded border" style="max-height: 420px"
                                             onerror="this.replaceWith(Object.assign(document.createElement('span'), {className: 'text-secondary', textContent: 'The slip cannot be shown here (a PDF?).'}))">
                                    </a>
                                    <div class="mt-2"><a href="<?= esc($slipUrl, 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open the slip</a></div>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary">No slip was sent with this payment.</p>
                            <?php endif ?>
                            <?php if (! empty($payment['provider_ref'])): ?>
                                <p>Provider reference: <code><?= esc($payment['provider_ref']) ?></code></p>
                            <?php endif ?>
                            <p class="mb-2">Check that <strong><?= $money($i['amount_payable'] ?? $i['amount_due'] ?? null) ?></strong> arrived<?= $withheld ? ' (the amount due less the tax withheld)' : '' ?>, then:</p>
                            <div class="mb-3">
                                <label class="form-label" for="reason-<?= esc($payment['public_id'], 'attr') ?>">Reason, if rejected</label>
                                <textarea class="form-control" id="reason-<?= esc($payment['public_id'], 'attr') ?>" rows="2" maxlength="255" data-reason
                                          placeholder="Shown to the business, e.g. The amount on the slip does not match"></textarea>
                                <div class="invalid-feedback" data-reason-error></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-danger" data-action="reject"><i class="bi bi-x-circle"></i> Reject Payment</button>
                                <button type="button" class="btn btn-success" data-action="approve"><i class="bi bi-check-circle"></i> Approve Payment</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Payments</h5>
                        <p class="card-subtitle">Every payment sent for this invoice, oldest first</p>
                    </div>
                    <div class="card-body">
                        <?php if ([] === $payments): ?>
                            <p class="text-secondary mb-0">No payment has been sent yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                    <tr>
                                        <th>Sent</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Reviewed</th>
                                        <th>Slip</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?= B::time($payment['created_at'] ?? null) ?></td>
                                            <td><code><?= esc((string) ($payment['method_code'] ?? '')) ?></code></td>
                                            <td class="text-end text-nowrap"><?= esc(B::money($payment['currency_code'] ?? null, $payment['amount'] ?? null)) ?></td>
                                            <td>
                                                <?= B::badge(B::PAYMENT_STATUSES, $payment['txn_status'] ?? null) ?>
                                                <?php if (! empty($payment['failure_reason'])): ?>
                                                    <div class="small text-secondary"><?= esc($payment['failure_reason']) ?></div>
                                                <?php endif ?>
                                            </td>
                                            <td>
                                                <?php if (! empty($payment['confirmed_at'])): ?>
                                                    <?= esc((string) ($payment['confirmed_by_name'] ?? 'Automatic')) ?><div class="small"><?= B::time($payment['confirmed_at']) ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif ?>
                                            </td>
                                            <td>
                                                <?php if (! empty($payment['has_slip'])): ?>
                                                    <a href="<?= esc(base_url('admin/clients/invoices/slip/' . rawurlencode($payment['public_id'])), 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-image"></i> Open</a>
                                                <?php else: ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php endif ?>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif ?>
                    </div>
                </div>

                <?php if ([] !== $certificates): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Withholding Tax Certificates</h5>
                        <p class="card-subtitle">Every certificate sent for this invoice, oldest first; review them under <a href="<?= esc(base_url('admin/clients/withholding'), 'attr') ?>">Withholding Tax</a></p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                <tr><th>Sent</th><th>No.</th><th class="text-end">Withheld</th><th>Status</th><th>Reviewed</th><th>File</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($certificates as $cert): ?>
                                    <tr>
                                        <td><?= B::time($cert['created_at'] ?? null) ?></td>
                                        <td><?= esc((string) ($cert['certificate_no'] ?? '')) ?><div class="small text-secondary"><?= esc((string) ($cert['issued_on'] ?? '')) ?></div></td>
                                        <td class="text-end text-nowrap"><?= $money($cert['wht_amount'] ?? null) ?></td>
                                        <td>
                                            <?= B::badge(B::CERTIFICATE_STATUSES, $cert['cert_status'] ?? null) ?>
                                            <?php if (! empty($cert['rejection_reason'])): ?><div class="small text-secondary"><?= esc($cert['rejection_reason']) ?></div><?php endif ?>
                                        </td>
                                        <td>
                                            <?php if (! empty($cert['reviewed_at'])): ?>
                                                <?= esc((string) ($cert['reviewed_by_name'] ?? '')) ?><div class="small"><?= B::time($cert['reviewed_at']) ?></div>
                                            <?php else: ?><span class="text-muted">&mdash;</span><?php endif ?>
                                        </td>
                                        <td>
                                            <?php if (! empty($cert['has_file'])): ?>
                                                <a href="<?= esc(base_url('admin/clients/withholding/certificate/' . rawurlencode((string) $cert['public_id'])), 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> Open</a>
                                            <?php else: ?><span class="text-muted">&mdash;</span><?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif ?>
            </div>
        </div>
    </section>

    <script>
    (function () {
      'use strict';
      var urls = {
        approve: <?= json_encode(base_url('admin/clients/invoices/payment/approve'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>,
        reject: <?= json_encode(base_url('admin/clients/invoices/payment/reject'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>
      };
      var csrf = { name: <?= json_encode(csrf_token(), JSON_HEX_TAG) ?>, hash: <?= json_encode(csrf_hash(), JSON_HEX_TAG) ?> };

      function notify(type, message) {
        if (window.toastr) { window.toastr[type](message); } else { window.alert(message); }
      }

      async function confirmAction(text, button) {
        if (!window.Swal) { return window.confirm(text); }
        var result = await window.Swal.fire({ icon: 'question', text: text, showCancelButton: true, confirmButtonText: button, cancelButtonText: 'Go Back' });
        return result.isConfirmed;
      }

      document.querySelectorAll('[data-review]').forEach(function (card) {
        var publicId = card.getAttribute('data-review');
        var reason = card.querySelector('[data-reason]');
        var reasonError = card.querySelector('[data-reason-error]');
        var buttons = card.querySelectorAll('[data-action]');

        reason.addEventListener('input', function () { reason.classList.remove('is-invalid'); });

        buttons.forEach(function (button) {
          button.addEventListener('click', async function () {
            var action = button.getAttribute('data-action');
            var text = reason.value.trim();
            if (action === 'reject' && text === '') {
              reasonError.textContent = 'Please give the reason the business will see.';
              reason.classList.add('is-invalid');
              reason.focus();
              return;
            }
            var ok = await confirmAction(action === 'approve'
              ? 'Approve this payment? The invoice will be marked paid and the subscription moves on.'
              : 'Reject this payment? The business will see the reason and can pay again.',
              action === 'approve' ? 'Approve' : 'Reject');
            if (!ok) { return; }

            var body = new FormData();
            body.append('public_id', publicId);
            if (action === 'reject') { body.append('failure_reason', text); }
            body.append(csrf.name, csrf.hash);
            buttons.forEach(function (b) { b.disabled = true; });
            try {
              var response = await fetch(urls[action], { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
              var data = await response.json();
              if (data.csrf_hash) { csrf.hash = data.csrf_hash; }
              if (response.ok && data.success) {
                notify('success', data.message);
                setTimeout(function () { window.location.reload(); }, 800);
                return;
              }
              if (data.errors && data.errors.failure_reason) {
                reasonError.textContent = data.errors.failure_reason;
                reason.classList.add('is-invalid');
              }
              notify('error', data.message || 'Could not save. Please try again.');
            } catch (e) {
              notify('error', 'Could not save. Please try again.');
            }
            buttons.forEach(function (b) { b.disabled = false; });
          });
        });
      });
    })();
    </script>
<?= $this->endSection() ?>
