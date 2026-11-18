<?php
/**
 * The Finance department's dashboard; see App\Controllers\Admin::dashboard() and the API's App\Libraries\FinanceDashboard
 * @var array|null  $finance The API's data, null when it could not be loaded
 * @var string|null $error
 */
$money = formatMoneyHtml(...);
$cycles = ['M' => 'Monthly', 'Y' => 'Yearly'];
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="page-dashboard">
        <?php if ($finance === null) : ?>
        <div class="alert alert-warning" role="alert">The Finance figures could not be loaded: <?= esc($error) ?></div>
        <?php else : ?>
        <?php
            $queue  = $finance['queue'];
            $tiles  = [
                ['Payments to Review', $queue['payments_to_review'], 'admin/clients/invoices?review=Y','Payment slips waiting to be approved or rejected'],
                ['Certificates to Review', $queue['certificates_to_review'], 'admin/clients/withholding', 'Withholding tax certificates waiting for review'],
                ['Withholding Requests', $queue['withholding_requests'], 'admin/clients/withholding', 'Businesses asking to withhold tax'],
                ['Overdue Invoices', $queue['invoices_overdue'], 'admin/clients/invoices', 'Unpaid invoices past their due date'],
                ['Due in ' . (int) $finance['soon_days'] . ' Days', $queue['invoices_due_soon'], 'admin/clients/invoices', 'Unpaid invoices falling due soon'],
            ];
            $waiting = array_sum(array_column($tiles, 1));
        ?>
        <div class="row g-3 mb-3">
            <div class="col-xxl-8">
                <div class="card fx-hero-card h-100">
                    <div class="card-body">
                        <div class="fx-hero-head">
                            <div>
                                <div class="fx-overline">Finance · Needs Your Action</div>
                                <h4 class="fx-hero-title"><?= $waiting === 0 ? 'All clear today' : esc(number_format($waiting)) . ' item' . ($waiting === 1 ? '' : 's') . ' waiting' ?></h4>
                            </div>
                        </div>
                        <div class="fx-kpi-grid">
                            <?php foreach ($tiles as [$label, $count, $path, $title]) : ?>
                            <a class="fx-kpi-item text-decoration-none" href="<?= base_url($path) ?>" title="<?= esc($title, 'attr') ?>">
                                <span class="fx-kpi-label"><?= esc($label) ?></span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $count)) ?></span>
                                <span class="fx-kpi-trend <?= $count > 0 ? 'negative' : 'positive' ?>"><?= $count > 0 ? 'To do' : 'Done' ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Subscriptions</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Renewing in <?= (int) $finance['soon_days'] ?> days</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/clients/subscriptions') ?>"><?= esc(number_format((int) $finance['subscriptions']['renewals_due'])) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Cancelled this month</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/clients/subscriptions') ?>"><?= esc(number_format((int) $finance['subscriptions']['cancelled_this_month'])) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Upcoming price changes</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/plan-prices') ?>"><?= esc(number_format(count($finance['price_changes']))) ?></a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Collections This Month</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/clients/invoices') ?>" class="btn btn-sm btn-outline-primary">View Invoices</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($finance['collections'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No invoices this month and nothing unpaid.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Currency</th>
                                    <th class="text-end">Invoiced</th>
                                    <th class="text-end">Collected</th>
                                    <th class="text-end" title="Tax withheld by the businesses on what they paid this month">Withheld</th>
                                    <th class="text-end" title="Every unpaid invoice, whenever issued">Outstanding</th>
                                    <th class="text-end">Overdue</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($finance['collections'] as $row) : ?>
                                <tr>
                                    <td><?= esc($row['currency_code']) ?></td>
                                    <td class="text-end"><?= $money($row['invoiced'], $row['currency_code']) ?></td>
                                    <td class="text-end"><?= $money($row['collected'], $row['currency_code']) ?></td>
                                    <td class="text-end"><?= $money($row['withheld'], $row['currency_code']) ?></td>
                                    <td class="text-end"><?= $money($row['outstanding'], $row['currency_code']) ?></td>
                                    <td class="text-end<?= (float) $row['overdue'] > 0 ? ' text-danger' : '' ?>"><?= $money($row['overdue'], $row['currency_code']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Ledger</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/ledger') ?>" class="btn btn-sm btn-outline-primary">View Ledger</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="d-flex gap-4 px-3 pt-3">
                            <div><div class="text-secondary small">Entries this month</div><div class="fs-5"><?= esc(number_format((int) $finance['ledger']['entries_this_month'])) ?></div></div>
                            <div><div class="text-secondary small">Missing a receipt</div><div class="fs-5<?= $finance['ledger']['missing_receipts'] > 0 ? ' text-danger' : '' ?>"><?= esc(number_format((int) $finance['ledger']['missing_receipts'])) ?></div></div>
                        </div>
                        <?php if ($finance['ledger']['accounts'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No internal accounts yet.</p>
                        <?php else : ?>
                        <div class="table-responsive mt-2">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">In</th>
                                    <th class="text-end">Out</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($finance['ledger']['accounts'] as $account) : ?>
                                <tr>
                                    <td><a href="<?= base_url('admin/ledger/accounts/edit/' . rawurlencode($account['public_id'])) ?>"><?= esc($account['account_name']) ?></a></td>
                                    <td class="text-end"><?= $money($account['money_in'], $account['currency_code']) ?></td>
                                    <td class="text-end"><?= $money($account['money_out'], $account['currency_code']) ?></td>
                                    <td class="text-end fw-semibold<?= (float) $account['balance'] < 0 ? ' text-danger' : '' ?>"><?= $money($account['balance'], $account['currency_code']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Payments Waiting Longest</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($finance['pending_payments'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No payments to review.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Invoice</th><th>Business</th><th>Sent</th><th class="text-end">Amount</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($finance['pending_payments'] as $payment) : ?>
                                <tr>
                                    <td><a href="<?= base_url('admin/clients/invoices/view/' . rawurlencode($payment['invoice_public_id'])) ?>"><?= esc($payment['invoice_no']) ?></a></td>
                                    <td><?= esc($payment['business_name']) ?></td>
                                    <td><?= localTimeTag($payment['created_at']) ?></td>
                                    <td class="text-end"><?= $money($payment['amount'], $payment['currency_code']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Overdue Invoices</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($finance['overdue_invoices'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No overdue invoices.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Invoice</th><th>Business</th><th>Due</th><th class="text-end">Payable</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($finance['overdue_invoices'] as $invoice) : ?>
                                <tr>
                                    <td><a href="<?= base_url('admin/clients/invoices/view/' . rawurlencode($invoice['public_id'])) ?>"><?= esc($invoice['invoice_no']) ?></a></td>
                                    <td><?= esc($invoice['business_name']) ?></td>
                                    <td class="text-danger"><?= localTimeTag($invoice['due_at'], 'date') ?></td>
                                    <td class="text-end"><?= $money($invoice['amount_payable'], $invoice['currency_code']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($finance['price_changes'] !== []) : ?>
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Upcoming Price Changes</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/plan-prices') ?>" class="btn btn-sm btn-outline-primary">View Prices</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Plan</th><th>Country</th><th>Billing</th><th class="text-end">New Price</th><th>Takes Effect</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($finance['price_changes'] as $price) : ?>
                                <tr>
                                    <td><a href="<?= base_url('admin/plan-prices/view/' . rawurlencode($price['public_id'])) ?>"><?= esc(ucfirst($price['plan_code'])) ?></a></td>
                                    <td><?= esc($price['country_code']) ?></td>
                                    <td><?= esc($cycles[$price['billing_cycle']] ?? $price['billing_cycle']) ?></td>
                                    <td class="text-end"><?= $money($price['price'], $price['currency_code']) ?></td>
                                    <td><?= localTimeTag($price['effective_from']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
<?= $this->endSection() ?>
