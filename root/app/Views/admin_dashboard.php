<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="page-dashboard">
        <div class="row g-3 mb-3">
            <div class="col-xxl-8">
                <div class="card fx-hero-card h-100">
                    <div class="card-body">
                        <div class="fx-hero-head">
                            <div>
                                <div class="fx-overline">Daily Briefing</div>
                                <h4 class="fx-hero-title">[PERFORMANCE] today</h4>
                            </div>
                        </div>
                        <div class="fx-kpi-grid">
                            <div class="fx-kpi-item">
                                <span class="fx-kpi-label">Paid Businesses</span>
                                <span class="fx-kpi-value">[####]</span>
                                <span class="fx-kpi-trend negative">-###%</span>
                            </div>
                            <div class="fx-kpi-item">
                                <span class="fx-kpi-label">Active Businesses</span>
                                <span class="fx-kpi-value">[####]</span>
                                <span class="fx-kpi-trend positive">+###%</span>
                            </div>
                            <div class="fx-kpi-item">
                                <span class="fx-kpi-label">Monthly Revenue</span>
                                <span class="fx-kpi-value">[####]</span>
                                <span class="fx-kpi-trend positive">+###%</span>
                            </div>
                            <a class="fx-kpi-item text-decoration-none" id="dashboard-website-visits" href="<?= base_url('admin/traffic/www') ?>"
                               data-url="<?= esc(base_url('admin/traffic/data/overview'), 'attr') ?>" title="The last 30 days, against the 30 before">
                                <span class="fx-kpi-label">Website Visits</span>
                                <span class="fx-kpi-value">–</span>
                                <span class="fx-kpi-trend">&nbsp;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Unread Messages</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">General Inquiry</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>">[##]</a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Technical Support</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>">[##]</a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Sales Question</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>">[##]</a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Latest Contracts</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/clients/contracts') ?>" class="btn btn-sm btn-outline-primary">View
                                All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Business</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>#1234</td>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>฿1,290</td>
                                    <td><span class="badge badge-soft-success">Completed</span></td>
                                </tr>
                                <tr>
                                    <td>#1234</td>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>฿1,290</td>
                                    <td><span class="badge badge-soft-success">Completed</span></td>
                                </tr>
                                <tr>
                                    <td>#1234</td>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>฿1,290</td>
                                    <td><span class="badge badge-soft-success">Completed</span></td>
                                </tr>
                                <tr>
                                    <td>#1234</td>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>฿1,290</td>
                                    <td><span class="badge badge-soft-warning">Pending</span></td>
                                </tr>
                                <tr>
                                    <td>#1234</td>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>฿1,290</td>
                                    <td><span class="badge badge-soft-success">Completed</span></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">New Businesses</h5>
                        <div class="card-actions">
                            <a href="<?= base_url('admin/clients/businesses') ?>"
                               class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>Spa / Massage</td>
                                    <td><span class="badge badge-soft-success">LIVE</span></td>
                                </tr>
                                <tr>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>Spa / Massage</td>
                                    <td><span class="badge badge-soft-danger">OFFLINE</span></td>
                                </tr>
                                <tr>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>Spa / Massage</td>
                                    <td><span class="badge badge-soft-success">LIVE</span></td>
                                </tr>
                                <tr>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>Spa / Massage</td>
                                    <td><span class="badge badge-soft-success">LIVE</span></td>
                                </tr>
                                <tr>
                                    <td><a href="#">Some Business</a></td>
                                    <td>Nov 15, 2026</td>
                                    <td>Spa / Massage</td>
                                    <td><span class="badge badge-soft-success">LIVE</span></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
    // the website's visits of the last 30 days (the API's default period), against the 30 before; see App\Controllers\Traffic
    (function () {
        const tile = document.getElementById('dashboard-website-visits');
        if (!tile) {
            return;
        }
        fetch(tile.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ site: 'www' })
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
    })();
</script>
<?php $this->endSection() ?>