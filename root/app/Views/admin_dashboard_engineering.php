<?php
/**
 * The Engineering department's dashboard; see App\Controllers\Admin::dashboard() and the API's
 * App\Libraries\EngineeringDashboard. The website's and the marketplace's visits load from
 * admin/traffic/data/overview once the page is open.
 * @var array|null  $engineering The API's data, null when it could not be loaded
 * @var string|null $error
 */
$levelBadges = ['EMERGENCY' => 'danger', 'ALERT' => 'danger', 'CRITICAL' => 'danger', 'ERROR' => 'warning'];
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="page-dashboard">
        <?php if ($engineering === null) : ?>
        <div class="alert alert-warning" role="alert">The Engineering figures could not be loaded: <?= esc($error) ?></div>
        <?php else : ?>
        <?php
            $errors   = $engineering['errors'];
            $tracker  = $engineering['tracker'];
            $usage    = $engineering['usage'];
            $count    = (int) $errors['last_24h'];
            $previous = (int) $errors['previous_24h'];
            if ($previous > 0) {
                $change     = round(($count - $previous) / $previous * 100, 1);
                $errorTrend = ($change > 0 ? '+' : ($change < 0 ? '−' : '±')) . number_format(abs($change), 1) . '% vs day before';
            } else {
                $errorTrend = $count > 0 ? 'none the day before' : 'none the day before either';
            }
            // minutes the oldest event has waited for the merge job
            $lag = $tracker['oldest_unprocessed_at'] === null ? null
                : max(0, intdiv(strtotime($engineering['as_of'] . ' UTC') - strtotime($tracker['oldest_unprocessed_at'] . ' UTC'), 60));
            $lagText = $lag === null ? 'Up to date' : ($lag < 120 ? 'oldest ' . $lag . ' min' : 'oldest ' . intdiv($lag, 60) . ' h');
        ?>
        <div class="row g-3 mb-3">
            <div class="col-xxl-8">
                <div class="card fx-hero-card h-100">
                    <div class="card-body">
                        <div class="fx-hero-head">
                            <div>
                                <div class="fx-overline">Platform Health · Last 24 Hours</div>
                                <h4 class="fx-hero-title"><?= $count === 0 ? 'No API errors' : esc(number_format($count)) . ' API error' . ($count === 1 ? '' : 's') ?></h4>
                            </div>
                        </div>
                        <div class="fx-kpi-grid">
                            <div class="fx-kpi-item" title="Errors the API logged: emergency, alert, critical and error">
                                <span class="fx-kpi-label">API Errors</span>
                                <span class="fx-kpi-value"><?= esc(number_format($count)) ?></span>
                                <span class="fx-kpi-trend <?= $count > $previous ? 'negative' : ($count < $previous ? 'positive' : '') ?>"><?= esc($errorTrend) ?></span>
                            </div>
                            <div class="fx-kpi-item" title="Emails the API could not send">
                                <span class="fx-kpi-label">Failed Emails</span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $errors['email_failures']['last_24h'])) ?></span>
                                <span class="fx-kpi-trend<?= $errors['email_failures']['last_7d'] > 0 ? ' negative' : '' ?>"><?= esc(number_format((int) $errors['email_failures']['last_7d'])) ?> in 7 days</span>
                            </div>
                            <div class="fx-kpi-item" title="Visitor events waiting for the tracker's merge job">
                                <span class="fx-kpi-label">Tracker Queue</span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $tracker['unprocessed'])) ?></span>
                                <span class="fx-kpi-trend <?= $lag === null ? 'positive' : ($lag > 60 ? 'negative' : '') ?>"><?= esc($lagText) ?></span>
                            </div>
                            <div class="fx-kpi-item" title="Orders placed on the marketplace and at the counter">
                                <span class="fx-kpi-label">Orders</span>
                                <span class="fx-kpi-value"><?= esc(number_format((int) $usage['orders_24h'])) ?></span>
                                <span class="fx-kpi-trend"><?= esc(number_format((int) $usage['orders_7d'])) ?> in 7 days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Errors by Level</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($errors['by_level'] as $level => $levelCount) : ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label"><?= esc(ucfirst(strtolower($level))) ?></span>
                            <span class="fx-pulse-value<?= $levelCount > 0 ? ' text-' . $levelBadges[$level] : '' ?>"><?= esc(number_format((int) $levelCount)) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Most Frequent Errors</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($errors['top'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">No errors in the last 24 hours.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Message</th><th>Level</th><th class="text-end">Count</th><th>Last Seen</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($errors['top'] as $top) : ?>
                                <tr>
                                    <td><code class="text-break"><?= esc($top['message']) ?></code></td>
                                    <td><span class="badge badge-soft-<?= $levelBadges[$top['level']] ?? 'secondary' ?>"><?= esc(ucfirst(strtolower($top['level']))) ?></span></td>
                                    <td class="text-end"><?= esc(number_format((int) $top['count'])) ?></td>
                                    <td class="text-nowrap"><?= localTimeTag($top['last_seen']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <p class="text-secondary small px-3 py-2 mb-0 border-top">From the API's own log. E-mail addresses are masked and numbers shown as #, so the same error is counted together.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Platform Use</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (['www' => 'Website visits, 30 days', 'mart' => 'Marketplace visits, 30 days'] as $site => $label) : ?>
                        <div class="fx-pulse-stat" data-traffic-site="<?= $site ?>">
                            <span class="fx-pulse-label"><?= $label ?> <span class="small" data-traffic-trend></span></span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/traffic/' . $site) ?>" data-traffic-value>–</a></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Marketplace orders, 7 days</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $usage['orders_7d_by_source']['mart'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Counter orders, 7 days</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $usage['orders_7d_by_source']['counter'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Businesses with a website</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $usage['websites'])) ?> / <?= esc(number_format((int) $usage['businesses'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Tracker events, 24 hours</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $tracker['received_24h'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">New messages</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>"><?= esc(number_format((int) $usage['inbox_new'])) ?></a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php if ($engineering !== null) : ?>
<script>
    // each site's visits of the last 30 days (the API's default period), against the 30 before; see App\Controllers\Traffic
    document.querySelectorAll('[data-traffic-site]').forEach(function (stat) {
        fetch(<?= json_encode(base_url('admin/traffic/data/overview')) ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ site: stat.dataset.trafficSite })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (json.status !== 'success') {
                return;
            }
            const now = json.data.totals.visits;
            const before = json.data.previous.visits;
            stat.querySelector('[data-traffic-value]').textContent = new Intl.NumberFormat('en-US').format(now);
            if (before > 0) {
                const change = Math.round(((now - before) / before) * 1000) / 10;
                const trend = stat.querySelector('[data-traffic-trend]');
                trend.textContent = '(' + (change > 0 ? '+' : change < 0 ? '−' : '±') + Math.abs(change).toFixed(1) + '%)';
                trend.classList.add(change < 0 ? 'text-danger' : 'text-success');
            }
        }).catch(function () {
            // the stat keeps its dash
        });
    });
</script>
<?php endif; ?>
<?= $this->endSection() ?>
