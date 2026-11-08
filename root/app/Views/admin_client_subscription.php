<?php
/**
 * One business's subscription with its invoices; see App\Controllers\ClientBilling::subscriptionView()
 * @var array $subscription From LINK_INTERNAL_API v1/staff/subscriptions/subscription
 * @var list<array> $invoices Newest first, each with its payments
 */

use App\Controllers\ClientBilling as B;

$this->extend('_layout');
$s    = $subscription;
$rows = [
    'Business'      => esc(B::businessLabel($s)) . ' <span class="text-secondary">· ' . esc((string) ($s['business_country_code'] ?? '')) . '</span>',
    'Status'        => B::badge(B::SUBSCRIPTION_STATUSES, $s['subscription_status'] ?? null)
        . ('Y' === ($s['cancel_at_period_end'] ?? 'N') ? ' <span class="badge bg-secondary">Cancels at period end</span>' : ''),
    'Plan'          => esc(($s['plan_code'] ?? '') . ' · ' . (B::BILLING_CYCLES[$s['billing_cycle'] ?? ''] ?? '')),
    'Price'         => esc(B::money($s['currency_code'] ?? null, $s['price'] ?? null)),
    'Changes to'    => empty($s['next_plan_code']) ? '<span class="text-muted">&mdash;</span>'
        : esc($s['next_plan_code'] . ' · ' . (B::BILLING_CYCLES[$s['next_billing_cycle'] ?? ''] ?? '')) . ' <span class="text-secondary">at the next renewal</span>',
    'Billing'       => esc(B::BILLING_MODES[$s['billing_mode'] ?? ''] ?? (string) ($s['billing_mode'] ?? ''))
        . (null === ($s['billing_anchor_day'] ?? null) ? '' : ' <span class="text-secondary">· renews on day ' . (int) $s['billing_anchor_day'] . '</span>'),
    'Activated'     => B::time($s['activated_at'] ?? null),
    'Current period' => B::period($s['current_period_start'] ?? null, $s['current_period_end'] ?? null),
    'Next billing'  => B::time($s['next_billing_at'] ?? null),
];
foreach (['cancel_requested_at' => 'Cancel requested', 'suspended_at' => 'Suspended', 'cancelled_at' => 'Cancelled', 'ended_at' => 'Ended'] as $field => $label) {
    if (! empty($s[$field])) {
        $rows[$label] = B::time($s[$field]);
    }
}
$rows['Created'] = B::time($s['created_at'] ?? null);

// the changes the API allows from this status: suspend an active one, reactivate a suspended one whose period has not
// ended, cancel any live one now
$status    = (string) ($s['subscription_status'] ?? '');
$periodEnd = (string) ($s['current_period_end'] ?? '');
$actions   = [];
if ('A' === $status) {
    $actions['S'] = ['label' => 'Suspend', 'icon' => 'bi-pause-circle', 'class' => 'btn-outline-warning',
        'confirm' => 'Suspend this subscription? The business loses its plan until it is reactivated.'];
}
if ('S' === $status && '' !== $periodEnd && $periodEnd > gmdate('Y-m-d H:i:s')) {
    $actions['A'] = ['label' => 'Reactivate', 'icon' => 'bi-play-circle', 'class' => 'btn-success',
        'confirm' => 'Reactivate this subscription? It runs again until the end of its current period.'];
}
if (in_array($status, ['P', 'A', 'S'], true)) {
    $actions['C'] = ['label' => 'Cancel Now', 'icon' => 'bi-x-octagon', 'class' => 'btn-outline-danger',
        'confirm' => 'Cancel this subscription now? It ends at once and its unpaid invoices are voided. This cannot be undone.'];
}
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Subscription Detail</h5>
                        <p class="card-subtitle"><code><?= esc((string) ($s['public_id'] ?? '')) ?></code></p>
                    </div>
                    <div class="card-body">
                        <?php foreach ($rows as $label => $html): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end"><?= esc($label) ?></div>
                                <div class="col-sm-8"><?= $html ?></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php if ([] !== $actions): ?>
                        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end" data-status-actions="<?= esc((string) ($s['public_id'] ?? ''), 'attr') ?>">
                            <span class="me-auto text-secondary small align-self-center">A payment waiting for review has to be approved or rejected first.</span>
                            <?php foreach ($actions as $code => $action): ?>
                                <button type="button" class="btn <?= esc($action['class'], 'attr') ?>" data-status="<?= esc($code, 'attr') ?>" data-confirm="<?= esc($action['confirm'], 'attr') ?>" data-label="<?= esc($action['label'], 'attr') ?>">
                                    <i class="bi <?= esc($action['icon'], 'attr') ?>"></i> <?= esc($action['label']) ?>
                                </button>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Invoices</h5>
                        <p class="card-subtitle">Newest first; open one to see its payments and review a slip</p>
                    </div>
                    <div class="card-body">
                        <?php if ([] === $invoices): ?>
                            <p class="text-secondary mb-0">No invoices yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th>Invoice No.</th>
                                        <th>Plan</th>
                                        <th>Period</th>
                                        <th class="text-end">To Pay</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Payments</th>
                                        <th>Documents</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <?php $waiting = count(array_filter((array) ($invoice['payments'] ?? []), static fn (array $p): bool => 'P' === ($p['txn_status'] ?? ''))); ?>
                                        <tr>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= esc(base_url('admin/clients/invoices/view/' . rawurlencode($invoice['public_id'])), 'attr') ?>" title="View"><i class="bi bi-eye"></i></a></td>
                                            <td class="text-nowrap"><?= esc($invoice['invoice_no']) ?></td>
                                            <td><?= esc($invoice['plan_code'] . ' · ' . (B::BILLING_CYCLES[$invoice['billing_cycle']] ?? $invoice['billing_cycle'])) ?></td>
                                            <td><?= B::period($invoice['period_start'] ?? null, $invoice['period_end'] ?? null) ?></td>
                                            <td class="text-end text-nowrap"><?= esc(B::money($invoice['currency_code'], $invoice['amount_payable'] ?? $invoice['amount_due'])) ?>
                                                <?php if ('W' === ($invoice['invoice_type'] ?? 'S')): ?><div class="small text-secondary">Tax top-up of <?= esc((string) ($invoice['parent_invoice_no'] ?? '')) ?></div>
                                                <?php elseif (! empty($invoice['wht_amount']) && 0.0 !== (float) $invoice['wht_amount']): ?><div class="small text-secondary"><?= esc(B::money($invoice['currency_code'], $invoice['wht_amount'])) ?> withheld · <?= esc(B::CERT_STATUSES[$invoice['wht_cert_status'] ?? '']['label'] ?? '') ?></div><?php endif ?></td>
                                            <td><?= B::time($invoice['due_at'] ?? null) ?></td>
                                            <td><?= B::badge(B::INVOICE_STATUSES, $invoice['invoice_status'] ?? null) ?></td>
                                            <td>
                                                <?= count((array) ($invoice['payments'] ?? [])) ?>
                                                <?php if ($waiting > 0): ?><span class="badge bg-info text-dark ms-1">To review</span><?php endif ?>
                                            </td>
                                            <td><?= B::documentLinks($invoice) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ([] !== $actions): ?>
    <script>
    (function () {
      'use strict';
      var url = <?= json_encode(base_url('admin/clients/subscriptions/status'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
      var csrf = { name: <?= json_encode(csrf_token(), JSON_HEX_TAG) ?>, hash: <?= json_encode(csrf_hash(), JSON_HEX_TAG) ?> };
      var bar = document.querySelector('[data-status-actions]');
      var buttons = bar.querySelectorAll('[data-status]');

      function notify(type, message) {
        if (window.toastr) { window.toastr[type](message); } else { window.alert(message); }
      }

      async function confirmAction(text, button) {
        if (!window.Swal) { return window.confirm(text); }
        var result = await window.Swal.fire({ icon: 'warning', text: text, showCancelButton: true, confirmButtonText: button, cancelButtonText: 'Go Back' });
        return result.isConfirmed;
      }

      buttons.forEach(function (button) {
        button.addEventListener('click', async function () {
          if (!(await confirmAction(button.getAttribute('data-confirm'), button.getAttribute('data-label')))) { return; }

          var body = new FormData();
          body.append('public_id', bar.getAttribute('data-status-actions'));
          body.append('subscription_status', button.getAttribute('data-status'));
          body.append(csrf.name, csrf.hash);
          buttons.forEach(function (b) { b.disabled = true; });
          try {
            var response = await fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            var data = await response.json();
            if (data.csrf_hash) { csrf.hash = data.csrf_hash; }
            if (response.ok && data.success) {
              notify('success', data.message);
              setTimeout(function () { window.location.reload(); }, 800);
              return;
            }
            notify('error', data.message || 'Could not change the subscription. Please try again.');
          } catch (e) {
            notify('error', 'Could not change the subscription. Please try again.');
          }
          buttons.forEach(function (b) { b.disabled = false; });
        });
      });
    })();
    </script>
    <?php endif ?>
<?= $this->endSection() ?>
