<?php
/**
 * The website's or the marketplace's traffic; see App\Controllers\Traffic. Every card is filled by
 * assets/js/traffic.js from admin/traffic/data/*, so the page shows at once and each card loads (or fails) apart.
 * @var string $site     www or mart
 * @var string $from     YYYY-MM-DD or ''
 * @var string $to       YYYY-MM-DD or ''
 * @var string $business a business_slug or ''
 */
$isMart = 'mart' === $site;
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
<link href="<?= base_url('assets/css/traffic.css') . '?v=' . @filemtime(FCPATH . 'assets/css/traffic.css') ?>" rel="stylesheet">
<div class="page-dashboard page-traffic" id="traffic"
     data-site="<?= esc($site, 'attr') ?>"
     data-from="<?= esc($from, 'attr') ?>"
     data-to="<?= esc($to, 'attr') ?>"
     data-business="<?= esc($business, 'attr') ?>"
     data-url="<?= esc(base_url('admin/traffic/data/'), 'attr') ?>">

    <!-- filters -->
    <div class="tr-toolbar mb-3">
        <div class="btn-group" role="group" aria-label="Site">
            <a href="<?= base_url('admin/traffic/www') ?>" class="btn btn-sm <?= $isMart ? 'btn-outline-secondary' : 'btn-primary' ?>"><i class="bi bi-globe2 me-1"></i>Website</a>
            <a href="<?= base_url('admin/traffic/mart') ?>" class="btn btn-sm <?= $isMart ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="bi bi-shop me-1"></i>Marketplace</a>
        </div>
        <div class="tr-filters">
            <?php if ($isMart) : ?>
                <div class="tr-business">
                    <select id="tr-business" aria-label="Business">
                        <option value="">All businesses</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="btn-group" role="group" aria-label="Period">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-days="7">7 days</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-days="30">30 days</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-days="90">90 days</button>
            </div>
            <div class="tr-range">
                <i class="bi bi-calendar3"></i>
                <input type="text" id="tr-range" class="form-control form-control-sm" placeholder="Choose dates" aria-label="Period" readonly>
            </div>
        </div>
    </div>

    <!-- headline numbers -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="tr-kpis fx-kpi-grid" id="tr-kpis">
                <?php foreach (['visits' => 'Visits', 'visitors' => 'Visitors', 'new_visitors' => 'New Visitors', 'pageviews' => 'Pageviews', 'bounce_rate' => 'Bounce Rate', 'avg_visit_s' => 'Avg. Visit'] as $key => $label) : ?>
                    <div class="fx-kpi-item" data-kpi="<?= $key ?>">
                        <span class="fx-kpi-label"><?= $label ?></span>
                        <span class="fx-kpi-value"><span class="tr-skeleton"></span></span>
                        <span class="fx-kpi-trend">&nbsp;</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="tr-note mt-2" id="tr-period-note">&nbsp;</div>
        </div>
    </div>

    <!-- day by day -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Day by Day</h5>
            <div class="card-actions">
                <div class="btn-group" role="group" aria-label="Measure" id="tr-measures">
                    <button type="button" class="btn btn-sm btn-outline-secondary active" data-tr-measure="visits">Visits</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-measure="visitors">Visitors</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-measure="pageviews">Pageviews</button>
                    <?php if ($isMart) : ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-measure="orders">Orders</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-measure="revenue">Revenue</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="tr-chart" class="tr-chart" data-tr-card="series"></div>
        </div>
    </div>

    <?php if ($isMart) : ?>
        <div class="row g-3 mb-3">
            <!-- shopping funnel -->
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Shopping Funnel</h5>
                        <div class="card-actions tr-note" id="tr-funnel-summary"></div>
                    </div>
                    <div class="card-body" id="tr-funnel" data-tr-card="funnel"></div>
                </div>
            </div>
            <!-- the busiest businesses: only for all businesses -->
            <div class="col-xl-6" id="tr-businesses-col">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Busiest Businesses</h5>
                        <div class="card-actions tr-note">Click a business to see its traffic</div>
                    </div>
                    <div class="card-body p-0" id="tr-businesses" data-tr-card="businesses"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- breakdowns -->
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Pages</h5>
                </div>
                <div class="card-body p-0" id="tr-pages" data-tr-card="path"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Where They Came From</h5>
                    <div class="card-actions">
                        <div class="btn-group" role="group" aria-label="Sources or sites" id="tr-source-mode">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" data-tr-source="source">Sources</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-source="referrer">Sites</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0" id="tr-sources" data-tr-card="source"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Countries</h5>
                </div>
                <div class="card-body p-0" id="tr-countries" data-tr-card="country"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Devices</h5>
                </div>
                <div class="card-body" id="tr-devices" data-tr-card="device"></div>
            </div>
        </div>
    </div>

    <!-- visits -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Visits</h5>
            <div class="card-actions tr-note" id="tr-visits-note"></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 tr-visits">
                    <thead>
                    <tr>
                        <th>When</th>
                        <?php if ($isMart) : ?><th>Business</th><?php endif; ?>
                        <th>Came From</th>
                        <th>Pages</th>
                        <th>Where</th>
                        <th class="text-end">Time</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="tr-visits" data-tr-card="visits"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer tr-pager">
            <span class="tr-note" id="tr-visits-count"></span>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="tr-visits-prev" aria-label="Newer visits"><i class="bi bi-chevron-left"></i> Newer</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="tr-visits-next" aria-label="Older visits">Older <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
<script src="<?= base_url('assets/js/traffic.js') . '?v=' . @filemtime(FCPATH . 'assets/js/traffic.js') ?>" defer></script>
<?= $this->endSection() ?>
