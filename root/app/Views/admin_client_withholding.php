<?php
/**
 * Withholding tax: the businesses asking to withhold, to approve or turn down, and the certificates (50 ทวิ) sent for
 * the tax withheld from their invoices, to accept or reject. See App\Controllers\ClientBilling::withholding()
 * @var string     $requestStatus The businesses listed: R, A or N
 * @var string     $certStatus    The certificates listed: P, A or R
 * @var array|null $requests      From LINK_INTERNAL_API v1/staff/subscriptions/withholding; null when it could not be loaded
 * @var array|null $certificates  From LINK_INTERNAL_API v1/staff/subscriptions/certificates; null when it could not be loaded
 */

use App\Controllers\ClientBilling as B;

$this->extend('_layout');
$link = static fn (array $query): string => base_url('admin/clients/withholding') . '?' . http_build_query($query + ['requests' => $requestStatus, 'certificates' => $certStatus]);
?>
<?= $this->section('content') ?>
    <section class="section" id="withholding-page">
        <div class="row g-4">

            <?php // ---- the businesses' requests ---- ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="card-title">Businesses</h5>
                            <p class="card-subtitle">Approve a business only when its tax ID belongs to a juristic person (in Thailand, 13 digits starting with 0). Once approved, its unpaid invoices are reduced by the tax.</p>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Businesses shown">
                            <?php foreach (B::WHT_STATUSES as $code => $badge): ?>
                                <a class="btn <?= $code === $requestStatus ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc($link(['requests' => $code]), 'attr') ?>"><?= esc($badge['label']) ?></a>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (null === $requests): ?>
                            <div class="alert alert-danger mb-0">Could not load the businesses. Please reload the page.</div>
                        <?php elseif ([] === $requests): ?>
                            <p class="text-secondary mb-0">No business here.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Business</th>
                                        <th>Registered Name</th>
                                        <th>Tax ID</th>
                                        <th>Branch</th>
                                        <th>Rule</th>
                                        <th>Status</th>
                                        <th class="text-end"><?= 'R' === $requestStatus ? 'Decision' : 'Reviewed' ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($requests as $row): ?>
                                        <tr data-business="<?= esc((string) $row['business_slug'], 'attr') ?>">
                                            <td><?= esc(B::businessLabel($row)) ?> <span class="text-secondary">· <?= esc((string) ($row['country_code'] ?? '')) ?></span></td>
                                            <td><?= esc((string) ($row['legal_name'] ?? '')) ?></td>
                                            <td class="font-monospace"><?= '' === (string) ($row['tax_id'] ?? '') ? '<span class="text-danger">None</span>' : esc((string) $row['tax_id']) ?></td>
                                            <td class="font-monospace"><?= esc((string) ($row['tax_branch_code'] ?? '')) ?></td>
                                            <td><code><?= esc((string) ($row['wht_rule_code'] ?? '')) ?></code></td>
                                            <td><?= B::badge(B::WHT_STATUSES, $row['wht_status'] ?? null) ?></td>
                                            <td class="text-end text-nowrap">
                                                <?php if ('R' === ($row['wht_status'] ?? '')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-wht="N"><i class="bi bi-x-circle"></i> Turn Down</button>
                                                    <button type="button" class="btn btn-sm btn-success" data-wht="A"><i class="bi bi-check-circle"></i> Approve</button>
                                                <?php elseif (! empty($row['wht_reviewed_at'])): ?>
                                                    <?= esc((string) ($row['wht_reviewed_by_name'] ?? '')) ?><div class="small"><?= B::time($row['wht_reviewed_at']) ?></div>
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
            </div>

            <?php // ---- the certificates ---- ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="card-title">Certificates</h5>
                            <p class="card-subtitle">Check that the certificate names the business, our tax ID and the amounts of the invoice. Oldest first.</p>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Certificates shown">
                            <?php foreach (B::CERTIFICATE_STATUSES as $code => $badge): ?>
                                <a class="btn <?= $code === $certStatus ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc($link(['certificates' => $code]), 'attr') ?>"><?= esc($badge['label']) ?></a>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (null === $certificates): ?>
                            <div class="alert alert-danger mb-0">Could not load the certificates. Please reload the page.</div>
                        <?php elseif ([] === $certificates): ?>
                            <p class="text-secondary mb-0">No certificate here.</p>
                        <?php else: ?>
                            <?php foreach ($certificates as $row):
                                $fileUrl  = base_url('admin/clients/withholding/certificate/' . rawurlencode((string) $row['public_id']));
                                $mismatch = (string) $row['wht_amount'] !== (string) $row['invoice_wht_amount'] || (string) $row['base_amount'] !== (string) $row['invoice_amount_due'];
                                ?>
                                <div class="border rounded p-3 mb-3" data-certificate="<?= esc((string) $row['public_id'], 'attr') ?>">
                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <?php if (! empty($row['has_file'])): ?>
                                                <a href="<?= esc($fileUrl, 'attr') ?>" target="_blank" rel="noopener">
                                                    <img src="<?= esc($fileUrl, 'attr') ?>" alt="Certificate" class="img-fluid rounded border" style="max-height: 320px"
                                                         onerror="this.replaceWith(Object.assign(document.createElement('span'), {className: 'text-secondary', textContent: 'The certificate cannot be shown here (a PDF?).'}))">
                                                </a>
                                                <div class="mt-2"><a href="<?= esc($fileUrl, 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open the certificate</a></div>
                                            <?php else: ?>
                                                <p class="text-secondary">No file.</p>
                                            <?php endif ?>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                                <div>
                                                    <strong><?= esc(B::businessLabel($row)) ?></strong>
                                                    <?php if (! empty($row['legal_name'])): ?><span class="text-secondary">· <?= esc((string) $row['legal_name']) ?></span><?php endif ?>
                                                </div>
                                                <div><?= B::badge(B::CERTIFICATE_STATUSES, $row['cert_status'] ?? null) ?></div>
                                            </div>
                                            <table class="table table-sm mb-3">
                                                <thead><tr><th></th><th class="text-end">On the certificate</th><th class="text-end">On invoice
                                                    <a href="<?= esc(base_url('admin/clients/invoices/view/' . rawurlencode((string) $row['invoice_public_id'])), 'attr') ?>"><?= esc((string) $row['invoice_no']) ?></a></th></tr></thead>
                                                <tbody>
                                                <tr><th scope="row">Amount paid (before VAT)</th><td class="text-end"><?= esc((string) $row['base_amount']) ?></td><td class="text-end"><?= esc((string) $row['invoice_amount_due']) ?></td></tr>
                                                <tr><th scope="row">Tax withheld (<?= esc((string) $row['invoice_wht_rate']) ?>%)</th><td class="text-end"><?= esc((string) $row['wht_amount']) ?></td><td class="text-end"><?= esc((string) $row['invoice_wht_amount']) ?></td></tr>
                                                <tr><th scope="row">Book / number</th><td class="text-end" colspan="2"><?= esc((string) ($row['certificate_no'] ?? '—')) ?></td></tr>
                                                <tr><th scope="row">Date on the certificate</th><td class="text-end" colspan="2"><?= esc((string) ($row['issued_on'] ?? '')) ?></td></tr>
                                                <tr><th scope="row">Sent</th><td class="text-end" colspan="2"><?= B::time($row['created_at'] ?? null) ?></td></tr>
                                                <tr><th scope="row">Due</th><td class="text-end" colspan="2"><?= B::time($row['wht_cert_due_at'] ?? null) ?></td></tr>
                                                </tbody>
                                            </table>
                                            <?php if ($mismatch): ?>
                                                <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> The amounts on the certificate differ from the invoice.</div>
                                            <?php endif ?>

                                            <?php if ('P' === ($row['cert_status'] ?? '')): ?>
                                                <div class="mb-3">
                                                    <label class="form-label" for="reason-<?= esc((string) $row['public_id'], 'attr') ?>">Reason, if rejected</label>
                                                    <textarea class="form-control" id="reason-<?= esc((string) $row['public_id'], 'attr') ?>" rows="2" maxlength="255" data-reason
                                                              placeholder="Shown to the business, e.g. The tax withheld does not match the invoice"></textarea>
                                                    <div class="invalid-feedback" data-reason-error></div>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <button type="button" class="btn btn-outline-danger" data-action="reject"><i class="bi bi-x-circle"></i> Reject Certificate</button>
                                                    <button type="button" class="btn btn-success" data-action="accept"><i class="bi bi-check-circle"></i> Accept Certificate</button>
                                                </div>
                                            <?php else: ?>
                                                <p class="mb-0 small text-secondary">
                                                    Reviewed by <?= esc((string) ($row['reviewed_by_name'] ?? '')) ?> <?= B::time($row['reviewed_at'] ?? null) ?>
                                                    <?php if (! empty($row['rejection_reason'])): ?> · <?= esc((string) $row['rejection_reason']) ?><?php endif ?>
                                                </p>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    (function () {
      'use strict';
      var urls = {
        review: <?= json_encode(base_url('admin/clients/withholding/review'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>,
        accept: <?= json_encode(base_url('admin/clients/withholding/certificate/accept'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>,
        reject: <?= json_encode(base_url('admin/clients/withholding/certificate/reject'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>
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

      /** POST the fields; reload on success, or resolve with the answer */
      async function send(url, fields, buttons, delay) {
        var body = new FormData();
        Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
        body.append(csrf.name, csrf.hash);
        buttons.forEach(function (b) { b.disabled = true; });
        try {
          var response = await fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
          var data = await response.json();
          if (data.csrf_hash) { csrf.hash = data.csrf_hash; }
          if (response.ok && data.success) {
            notify('success', data.message);
            setTimeout(function () { window.location.reload(); }, delay || 800);
            return data;
          }
          notify('error', data.message || 'Could not save. Please try again.');
          buttons.forEach(function (b) { b.disabled = false; });
          return data;
        } catch (e) {
          notify('error', 'Could not save. Please try again.');
          buttons.forEach(function (b) { b.disabled = false; });
          return {};
        }
      }

      // approve or turn down a request
      document.querySelectorAll('[data-business]').forEach(function (row) {
        var buttons = row.querySelectorAll('[data-wht]');
        buttons.forEach(function (button) {
          button.addEventListener('click', async function () {
            var decision = button.getAttribute('data-wht');
            var ok = await confirmAction(decision === 'A'
              ? 'Approve withholding tax for this business? Its unpaid invoices are reduced by the tax, and it sends a certificate for each one it pays.'
              : 'Turn down this request? The business pays its invoices in full.',
              decision === 'A' ? 'Approve' : 'Turn Down');
            if (ok) { send(urls.review, { business_slug: row.getAttribute('data-business'), wht_status: decision }, buttons); }
          });
        });
      });

      // accept or reject a certificate
      document.querySelectorAll('[data-certificate]').forEach(function (card) {
        var reason = card.querySelector('[data-reason]');
        if (!reason) { return; }
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
            var ok = await confirmAction(action === 'accept'
              ? 'Accept this certificate? A top-up billed for this invoice is voided if it is unpaid.'
              : 'Reject this certificate? The business will see the reason and can send another.',
              action === 'accept' ? 'Accept' : 'Reject');
            if (!ok) { return; }
            var fields = { public_id: card.getAttribute('data-certificate') };
            if (action === 'reject') { fields.rejection_reason = text; }
            // a paid top-up to refund is named in the message: leave it on screen longer
            var data = await send(urls[action], fields, buttons, action === 'accept' ? 2500 : 800);
            if (data.errors && data.errors.rejection_reason) {
              reasonError.textContent = data.errors.rejection_reason;
              reason.classList.add('is-invalid');
            }
          });
        });
      });
    })();
    </script>
<?= $this->endSection() ?>
