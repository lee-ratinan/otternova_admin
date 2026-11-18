<?php
/**
 * The Executive Office's dashboard; see App\Controllers\Admin::dashboard() and the API's App\Libraries\ExecutiveDashboard.
 * The website's and the marketplace's visits load from admin/traffic/data/overview once the page is open.
 * @var array|null  $executive The API's data, null when it could not be loaded
 * @var string|null $error
 */
$money = formatMoneyHtml(...);
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="page-dashboard">
        <?php if ($executive === null) : ?>
        <div class="alert alert-warning" role="alert">The dashboard figures could not be loaded: <?= esc($error) ?></div>
        <?php else : ?>
        <?php
            $days       = (int) $executive['period_days'];
            $businesses = $executive['businesses'];
            $mrr        = $executive['mrr'];
            $waiting    = array_sum($executive['finance_queue']);
            $planTotal  = max(1, array_sum(array_column($executive['plans'], 'businesses')));
        ?>
        <div class="row g-3 mb-3">
            <div class="col-xxl-8">
                <div class="card fx-hero-card h-100">
                    <div class="card-body">
                        <div class="fx-hero-head">
                            <div>
                                <div class="fx-overline">Executive Overview · Last <?= $days ?> Days</div>
                                <h4 class="fx-hero-title"><?= esc(number_format((int) $businesses['paid'])) ?> paying of <?= esc(number_format((int) $businesses['total'])) ?> businesses</h4>
                            </div>
                        </div>
                        <div class="fx-kpi-grid">
                            <div class="fx-kpi-item" title="Every business on the platform">
                                <span class="fx-kpi-label">Businesses</span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $businesses['total'])) ?></span>
                                <span class="fx-kpi-trend<?= $businesses['new'] > 0 ? ' positive' : '' ?>">+<?= esc(number_format((int) $businesses['new'])) ?> new</span>
                            </div>
                            <a class="fx-kpi-item text-decoration-none" href="<?= base_url('admin/clients/subscriptions') ?>" title="Businesses with an active subscription">
                                <span class="fx-kpi-label">Paid Businesses</span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $businesses['paid'])) ?></span>
                                <span class="fx-kpi-trend<?= $businesses['new_paid'] > 0 ? ' positive' : '' ?>">+<?= esc(number_format((int) $businesses['new_paid'])) ?> new</span>
                            </a>
                            <div class="fx-kpi-item" title="Monthly recurring revenue: the list price of every active subscription, a yearly one at a twelfth, before discounts and withholding tax">
                                <span class="fx-kpi-label">Monthly Revenue</span>
                                <span class="fx-kpi-value"><?= $mrr === [] ? '–' : $money($mrr[0]['mrr'], $mrr[0]['currency_code']) ?></span>
                                <span class="fx-kpi-trend"><?= count($mrr) > 1 ? '+' . (count($mrr) - 1) . ' more currenc' . (count($mrr) === 2 ? 'y' : 'ies') : '&nbsp;' ?></span>
                            </div>
                            <?php foreach (['www' => 'Website Visits', 'mart' => 'Marketplace Visits'] as $site => $label) : ?>
                            <a class="fx-kpi-item text-decoration-none" data-traffic-site="<?= $site ?>" href="<?= base_url('admin/traffic/' . $site) ?>"
                               title="The last 30 days, against the 30 before">
                                <span class="fx-kpi-label"><?= $label ?></span>
                                <span class="fx-kpi-value">–</span>
                                <span class="fx-kpi-trend">&nbsp;</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Needs Attention</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">New messages</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>"><?= esc(number_format((int) $executive['inbox']['new'])) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Read, not yet done</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>"><?= esc(number_format((int) $executive['inbox']['read'])) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Waiting for Finance</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/clients/invoices?review=Y') ?>" title="Payments and certificates to review, withholding requests, overdue invoices and invoices due soon"><?= esc(number_format($waiting)) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Suspended subscriptions</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/clients/subscriptions') ?>"><?= esc(number_format((int) $executive['movements']['suspended'])) ?></a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">New Businesses</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($executive['new_businesses'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No businesses yet.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Business</th><th>Joined</th><th>Type</th><th>Plan</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($executive['new_businesses'] as $business) : ?>
                                <tr>
                                    <td><span class="fi fi-<?= esc(strtolower($business['country_code']), 'attr') ?> me-1" title="<?= esc($business['country_code'], 'attr') ?>"></span><?= esc($business['business_name']) ?></td>
                                    <td><?= localTimeTag($business['created_at'], 'date') ?></td>
                                    <td><?= esc($business['type_name']) ?></td>
                                    <td><span class="badge badge-soft-<?= $business['plan_code'] === 'free' ? 'secondary' : 'success' ?>"><?= esc(ucfirst($business['plan_code'])) ?></span></td>
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
                        <h5 class="card-title">Plan Mix</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/plans') ?>" class="btn btn-sm btn-outline-primary">View Plans</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php foreach ($executive['plans'] as $plan) : ?>
                        <?php $share = round($plan['businesses'] / $planTotal * 100); ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= esc(ucfirst($plan['plan_code'])) ?></span>
                                <span class="text-secondary"><?= esc(number_format((int) $plan['businesses'])) ?> · <?= $share ?>%</span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="<?= esc(ucfirst($plan['plan_code']), 'attr') ?>" aria-valuenow="<?= $share ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px">
                                <div class="progress-bar" style="width: <?= $share ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="d-flex gap-4 pt-2 border-top">
                            <div><div class="text-secondary small">Cancelled, <?= $days ?> days</div><div class="fs-5<?= $executive['movements']['cancelled'] > 0 ? ' text-danger' : '' ?>"><?= esc(number_format((int) $executive['movements']['cancelled'])) ?></div></div>
                            <div><div class="text-secondary small">Plan changes scheduled</div><div class="fs-5"><?= esc(number_format((int) $executive['movements']['plan_changes'])) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Latest Activity</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/logs/activities') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($executive['activities'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No activity yet.</p>
                        <?php else : ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($executive['activities'] as $activity) : ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <span>
                                        <?php if ($activity['table_involved'] !== null && $activity['table_id_updated'] !== null) : ?>
                                        <a href="<?= base_url('admin/logs/activities?' . http_build_query(['table' => $activity['table_involved'], 'id' => $activity['table_id_updated']])) ?>"><?= esc($activity['activity_summary']) ?></a>
                                        <?php else : ?>
                                        <?= esc($activity['activity_summary']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-secondary small text-nowrap"><?= localTimeTag($activity['created_at']) ?></span>
                                </div>
                                <div class="text-secondary small"><?= esc($activity['actor_name'] ?? 'System') ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Revenue &amp; Cash</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/ledger') ?>" class="btn btn-sm btn-outline-primary">View Ledger</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Monthly revenue</th><th class="text-end">Subscriptions</th><th class="text-end">MRR</th></tr>
                                </thead>
                                <tbody>
                                <?php if ($mrr === []) : ?>
                                <tr><td colspan="3" class="text-secondary">No active subscriptions.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($mrr as $row) : ?>
                                <tr>
                                    <td><?= esc($row['currency_code']) ?></td>
                                    <td class="text-end"><?= esc(number_format((int) $row['subscriptions'])) ?></td>
                                    <td class="text-end fw-semibold"><?= $money($row['mrr'], $row['currency_code']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <thead>
                                <tr><th>Account</th><th></th><th class="text-end">Balance</th></tr>
                                </thead>
                                <tbody>
                                <?php if ($executive['ledger']['accounts'] === []) : ?>
                                <tr><td colspan="3" class="text-secondary">No internal accounts yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($executive['ledger']['accounts'] as $account) : ?>
                                <tr>
                                    <td colspan="2"><?= esc($account['account_name']) ?></td>
                                    <td class="text-end fw-semibold<?= (float) $account['balance'] < 0 ? ' text-danger' : '' ?>"><?= $money($account['balance'], $account['currency_code']) ?></td>
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
    </div>
<?php if ($executive !== null) : ?>
<script>
    // each site's visits of the last 30 days (the API's default period), against the 30 before; see App\Controllers\Traffic
    document.querySelectorAll('[data-traffic-site]').forEach(function (tile) {
        fetch(<?= json_encode(base_url('admin/traffic/data/overview')) ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ site: tile.dataset.trafficSite })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (json.status !== 'success') {
                return;
            }
            const now = json.data.totals.visits;
            const before = json.data.previous.visits;
            const trend = tile.querySelector('.fx-kpi-trend');
            tile.querySelector('.fx-kpi-value').textContent = new Intl.NumberFormat('en-US').format(now);
            if (before > 0) {
                const change = Math.round(((now - before) / before) * 1000) / 10;
                trend.textContent = (change > 0 ? '+' : change < 0 ? '−' : '±') + Math.abs(change).toFixed(1) + '%';
                if (change !== 0) {
                    trend.classList.add(change > 0 ? 'positive' : 'negative');
                }
            }
        }).catch(function () {
            // the tile keeps its dash
        });
    });
</script>
<?php endif; ?>
<?= $this->endSection() ?>
