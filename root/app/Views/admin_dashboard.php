<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="main-content page-dashboard">
        <div class="page-dashboard">
            <div class="page-header fx-page-header">
                <div>
                    <h1 class="page-title">Growth Command Center</h1>
                    <p class="fx-subtitle">Live commercial performance, delivery health, and engagement signals in one control surface.</p>
                </div>
                <div class="page-header-actions">
                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>Export</button>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Report</button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xxl-8">
                    <div class="card fx-hero-card h-100">
                        <div class="card-body">
                            <div class="fx-hero-head">
                                <div>
                                    <div class="fx-overline">Daily Briefing</div>
                                    <h4 class="fx-hero-title">Momentum is strong today</h4>
                                    <p class="fx-hero-text">Revenue is up <strong>12.5%</strong> vs yesterday. Conversion quality improved in paid campaigns and support SLA remains healthy.</p>
                                </div>
                                <span class="badge badge-soft-success fx-hero-badge"><i class="bi bi-arrow-up-right"></i> 8.2% QoQ</span>
                            </div>

                            <div class="fx-kpi-grid">
                                <div class="fx-kpi-item">
                                    <span class="fx-kpi-label">Net Revenue</span>
                                    <span class="fx-kpi-value">$48,295</span>
                                    <span class="fx-kpi-trend positive">+12.5%</span>
                                </div>
                                <div class="fx-kpi-item">
                                    <span class="fx-kpi-label">Active Users</span>
                                    <span class="fx-kpi-value">5,432</span>
                                    <span class="fx-kpi-trend positive">+5.8%</span>
                                </div>
                                <div class="fx-kpi-item">
                                    <span class="fx-kpi-label">Orders</span>
                                    <span class="fx-kpi-value">1,248</span>
                                    <span class="fx-kpi-trend negative">-3.1%</span>
                                </div>
                                <div class="fx-kpi-item">
                                    <span class="fx-kpi-label">Conversion</span>
                                    <span class="fx-kpi-value">3.24%</span>
                                    <span class="fx-kpi-trend positive">+1.2%</span>
                                </div>
                            </div>

                            <div class="fx-hero-footer">
                                <div class="fx-chip-list">
                                    <span class="fx-chip"><i class="bi bi-lightning-charge"></i> Campaign ROI strong</span>
                                    <span class="fx-chip"><i class="bi bi-shield-check"></i> SLA 98.1%</span>
                                    <span class="fx-chip"><i class="bi bi-graph-up"></i> Retention improving</span>
                                </div>
                                <a href="dashboard-analytics.html" class="fx-link">Open analytics board <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4">
                    <div class="card fx-pulse-card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Team Pulse</h5>
                        </div>
                        <div class="card-body">
                            <div class="fx-pulse-stat">
                                <span class="fx-pulse-label">Open conversations</span>
                                <span class="fx-pulse-value">27</span>
                            </div>
                            <div class="fx-pulse-stat">
                                <span class="fx-pulse-label">Avg. response time</span>
                                <span class="fx-pulse-value">11m</span>
                            </div>
                            <div class="fx-pulse-stat">
                                <span class="fx-pulse-label">Critical tickets</span>
                                <span class="fx-pulse-value">3</span>
                            </div>

                            <div class="fx-live-list">
                                <a href="apps-chat.html" class="fx-live-item">
                                    <img src="<?= base_url('assets/img/avatars/avatar-2.webp') ?>" alt="" class="fx-live-avatar">
                                    <div>
                                        <div class="fx-live-title">Mia Rodriguez</div>
                                        <div class="fx-live-text">Need approval on revised onboarding copy.</div>
                                    </div>
                                    <span class="fx-live-time">2m</span>
                                </a>
                                <a href="apps-chat.html" class="fx-live-item">
                                    <img src="<?= base_url('assets/img/avatars/avatar-3.webp') ?>" alt="" class="fx-live-avatar">
                                    <div>
                                        <div class="fx-live-title">Dev Channel</div>
                                        <div class="fx-live-text">Build passed and staging checks are complete.</div>
                                    </div>
                                    <span class="fx-live-time">9m</span>
                                </a>
                                <a href="apps-chat.html" class="fx-live-item">
                                    <img src="<?= base_url('assets/img/avatars/avatar-4.webp') ?>" alt="" class="fx-live-avatar">
                                    <div>
                                        <div class="fx-live-title">Support Ops</div>
                                        <div class="fx-live-text">Queue volume is trending above baseline.</div>
                                    </div>
                                    <span class="fx-live-time">14m</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="card fx-mini-stat h-100">
                        <div class="card-body">
                            <span class="fx-mini-icon revenue"><i class="bi bi-currency-dollar"></i></span>
                            <span class="fx-mini-label">MRR</span>
                            <span class="fx-mini-value">$128.4K</span>
                            <span class="fx-mini-meta positive"><i class="bi bi-arrow-up"></i> 7.1%</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card fx-mini-stat h-100">
                        <div class="card-body">
                            <span class="fx-mini-icon churn"><i class="bi bi-exclamation-triangle"></i></span>
                            <span class="fx-mini-label">Churn Risk</span>
                            <span class="fx-mini-value">2.8%</span>
                            <span class="fx-mini-meta"><i class="bi bi-dash"></i> Stable</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card fx-mini-stat h-100">
                        <div class="card-body">
                            <span class="fx-mini-icon nps"><i class="bi bi-emoji-smile"></i></span>
                            <span class="fx-mini-label">NPS Score</span>
                            <span class="fx-mini-value">58</span>
                            <span class="fx-mini-meta positive"><i class="bi bi-arrow-up"></i> +4</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card fx-mini-stat h-100">
                        <div class="card-body">
                            <span class="fx-mini-icon refund"><i class="bi bi-arrow-counterclockwise"></i></span>
                            <span class="fx-mini-label">Refund Rate</span>
                            <span class="fx-mini-value">0.9%</span>
                            <span class="fx-mini-meta positive"><i class="bi bi-arrow-down"></i> -0.2%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">Revenue Flow</h5>
                            <div class="card-actions">
                                <div class="dash-chart-tabs">
                                    <button class="dash-chart-tab active" data-period="monthly">Monthly</button>
                                    <button class="dash-chart-tab" data-period="weekly">Weekly</button>
                                    <button class="dash-chart-tab" data-period="daily">Daily</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="dash-chart-summary fx-summary-grid">
                                <div class="dash-chart-summary-item">
                                    <span class="dash-chart-summary-dot" style="background: var(--accent-color);"></span>
                                    <span class="dash-chart-summary-label">Revenue</span>
                                    <span class="dash-chart-summary-value" id="summaryRevenue">$48,295</span>
                                </div>
                                <div class="dash-chart-summary-item">
                                    <span class="dash-chart-summary-dot" style="background: var(--success-color);"></span>
                                    <span class="dash-chart-summary-label">Expenses</span>
                                    <span class="dash-chart-summary-value" id="summaryExpenses">$28,450</span>
                                </div>
                                <div class="dash-chart-summary-item">
                                    <span class="dash-chart-summary-dot" style="background: var(--warning-color);"></span>
                                    <span class="dash-chart-summary-label">Profit</span>
                                    <span class="dash-chart-summary-value" id="summaryProfit">$19,845</span>
                                </div>
                            </div>
                            <div class="chart-container" id="revenueChart"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title">Traffic Sources</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" id="trafficChart"></div>
                            <div class="dash-traffic-legend">
                                <div class="dash-traffic-item"><span class="dash-traffic-dot" style="background: var(--accent-color);"></span><span class="dash-traffic-label">Direct</span><span class="dash-traffic-value">42%</span></div>
                                <div class="dash-traffic-item"><span class="dash-traffic-dot" style="background: var(--success-color);"></span><span class="dash-traffic-label">Organic</span><span class="dash-traffic-value">28%</span></div>
                                <div class="dash-traffic-item"><span class="dash-traffic-dot" style="background: var(--warning-color);"></span><span class="dash-traffic-label">Referral</span><span class="dash-traffic-value">18%</span></div>
                                <div class="dash-traffic-item"><span class="dash-traffic-dot" style="background: var(--info-color);"></span><span class="dash-traffic-label">Social</span><span class="dash-traffic-value">12%</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Pipeline Health</h5>
                        </div>
                        <div class="card-body">
                            <div class="fx-pipeline-item">
                                <div class="fx-pipeline-head"><span>Lead</span><span>$124,500</span></div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 100%; background: var(--accent-color);"></div>
                                </div>
                            </div>
                            <div class="fx-pipeline-item">
                                <div class="fx-pipeline-head"><span>Qualified</span><span>$98,200</span></div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 75%;"></div>
                                </div>
                            </div>
                            <div class="fx-pipeline-item">
                                <div class="fx-pipeline-head"><span>Proposal</span><span>$72,800</span></div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 55%;"></div>
                                </div>
                            </div>
                            <div class="fx-pipeline-item">
                                <div class="fx-pipeline-head"><span>Negotiation</span><span>$48,500</span></div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: 35%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">Recent Transactions</h5>
                            <div class="card-actions">
                                <a href="invoice-list.html" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Transaction</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td class="fw-medium">#TXN-2048</td>
                                        <td>
                                            <div class="table-user"><img src="<?= base_url('assets/img/avatars/avatar-1.webp') ?>" alt="" class="table-user-avatar">
                                                <div class="table-user-name">Alex Thompson</div>
                                            </div>
                                        </td>
                                        <td class="text-muted">Jan 29, 2026</td>
                                        <td class="fw-medium">$1,250.00</td>
                                        <td><span class="badge badge-soft-success">Completed</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">#TXN-2047</td>
                                        <td>
                                            <div class="table-user"><img src="<?= base_url('assets/img/avatars/avatar-2.webp') ?>" alt="" class="table-user-avatar">
                                                <div class="table-user-name">Sarah Wilson</div>
                                            </div>
                                        </td>
                                        <td class="text-muted">Jan 28, 2026</td>
                                        <td class="fw-medium">$890.00</td>
                                        <td><span class="badge badge-soft-warning">Pending</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">#TXN-2046</td>
                                        <td>
                                            <div class="table-user"><img src="<?= base_url('assets/img/avatars/avatar-3.webp') ?>" alt="" class="table-user-avatar">
                                                <div class="table-user-name">Mike Johnson</div>
                                            </div>
                                        </td>
                                        <td class="text-muted">Jan 27, 2026</td>
                                        <td class="fw-medium">$2,340.00</td>
                                        <td><span class="badge badge-soft-success">Completed</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">#TXN-2045</td>
                                        <td>
                                            <div class="table-user"><img src="<?= base_url('assets/img/avatars/avatar-4.webp') ?>" alt="" class="table-user-avatar">
                                                <div class="table-user-name">Emily Davis</div>
                                            </div>
                                        </td>
                                        <td class="text-muted">Jan 26, 2026</td>
                                        <td class="fw-medium">$560.00</td>
                                        <td><span class="badge badge-soft-danger">Failed</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">#TXN-2044</td>
                                        <td>
                                            <div class="table-user"><img src="<?= base_url('assets/img/avatars/avatar-5.webp') ?>" alt="" class="table-user-avatar">
                                                <div class="table-user-name">David Chen</div>
                                            </div>
                                        </td>
                                        <td class="text-muted">Jan 25, 2026</td>
                                        <td class="fw-medium">$3,120.00</td>
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
                            <h5 class="card-title">Execution Board</h5>
                            <div class="card-actions">
                                <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Task</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="fx-task-item">
                                <input type="checkbox" id="task-a">
                                <label for="task-a">Review dashboard design mockups <span>Today</span></label>
                                <span class="badge badge-soft-danger">High</span>
                            </div>
                            <div class="fx-task-item done">
                                <input type="checkbox" id="task-b" checked>
                                <label for="task-b">Team standup and blocker sync <span>10:00 AM</span></label>
                                <span class="badge badge-soft-warning">Medium</span>
                            </div>
                            <div class="fx-task-item">
                                <input type="checkbox" id="task-c">
                                <label for="task-c">Prepare quarterly report draft <span>Tomorrow</span></label>
                                <span class="badge badge-soft-primary">Normal</span>
                            </div>
                            <div class="fx-task-item">
                                <input type="checkbox" id="task-d">
                                <label for="task-d">Update product docs and release notes <span>Jan 31</span></label>
                                <span class="badge badge-soft-success">Low</span>
                            </div>
                            <div class="fx-task-item done">
                                <input type="checkbox" id="task-e" checked>
                                <label for="task-e">Fix authentication bug in staging <span>Completed</span></label>
                                <span class="badge badge-soft-danger">High</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="dash-activity">
                                <div class="dash-activity-item">
                                    <div class="dash-activity-dot" style="background: var(--success-color);"></div>
                                    <div class="dash-activity-content">
                                        <p class="dash-activity-text"><strong>Alex Thompson</strong> completed a purchase</p><span class="dash-activity-time">5 min ago</span>
                                    </div>
                                </div>
                                <div class="dash-activity-item">
                                    <div class="dash-activity-dot" style="background: var(--accent-color);"></div>
                                    <div class="dash-activity-content">
                                        <p class="dash-activity-text"><strong>Sarah Wilson</strong> submitted a support ticket</p><span class="dash-activity-time">28 min ago</span>
                                    </div>
                                </div>
                                <div class="dash-activity-item">
                                    <div class="dash-activity-dot" style="background: var(--warning-color);"></div>
                                    <div class="dash-activity-content">
                                        <p class="dash-activity-text">Server storage reached <strong>85%</strong> capacity</p><span class="dash-activity-time">1 hour ago</span>
                                    </div>
                                </div>
                                <div class="dash-activity-item">
                                    <div class="dash-activity-dot" style="background: var(--info-color);"></div>
                                    <div class="dash-activity-content">
                                        <p class="dash-activity-text"><strong>Mike Johnson</strong> deployed v2.4.1 to production</p><span class="dash-activity-time">2 hours ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">Team Members</h5>
                            <div class="card-actions"><a href="users.html" class="fx-link">View All</a></div>
                        </div>
                        <div class="card-body">
                            <div class="dash-team">
                                <div class="dash-team-member"><img src="<?= base_url('assets/img/avatars/avatar-1.webp') ?>" alt="" class="dash-team-avatar">
                                    <div class="dash-team-info"><span class="dash-team-name">Alex Thompson</span><span class="dash-team-role">Lead Developer</span></div><span class="dash-team-status online"></span>
                                </div>
                                <div class="dash-team-member"><img src="<?= base_url('assets/img/avatars/avatar-2.webp') ?>" alt="" class="dash-team-avatar">
                                    <div class="dash-team-info"><span class="dash-team-name">Sarah Wilson</span><span class="dash-team-role">Product Designer</span></div><span class="dash-team-status online"></span>
                                </div>
                                <div class="dash-team-member"><img src="<?= base_url('assets/img/avatars/avatar-3.webp') ?>" alt="" class="dash-team-avatar">
                                    <div class="dash-team-info"><span class="dash-team-name">Mike Johnson</span><span class="dash-team-role">DevOps Engineer</span></div><span class="dash-team-status away"></span>
                                </div>
                                <div class="dash-team-member"><img src="<?= base_url('assets/img/avatars/avatar-4.webp') ?>" alt="" class="dash-team-avatar">
                                    <div class="dash-team-info"><span class="dash-team-name">Emily Davis</span><span class="dash-team-role">Marketing Lead</span></div><span class="dash-team-status offline"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">Sales by Region</h5>
                        </div>
                        <div class="card-body">
                            <div class="region-list">
                                <div class="region-item">
                                    <div class="region-info"><span class="region-flag">🇺🇸</span><span class="region-name">United States</span></div>
                                    <div class="region-stats"><span class="region-value">$45,820</span>
                                        <div class="progress region-progress flex-grow-1">
                                            <div class="progress-bar" style="width: 65%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="region-item">
                                    <div class="region-info"><span class="region-flag">🇬🇧</span><span class="region-name">United Kingdom</span></div>
                                    <div class="region-stats"><span class="region-value">$28,450</span>
                                        <div class="progress region-progress flex-grow-1">
                                            <div class="progress-bar bg-success" style="width: 45%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="region-item">
                                    <div class="region-info"><span class="region-flag">🇩🇪</span><span class="region-name">Germany</span></div>
                                    <div class="region-stats"><span class="region-value">$21,380</span>
                                        <div class="progress region-progress flex-grow-1">
                                            <div class="progress-bar bg-warning" style="width: 35%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="region-item">
                                    <div class="region-info"><span class="region-flag">🇫🇷</span><span class="region-name">France</span></div>
                                    <div class="region-stats"><span class="region-value">$18,240</span>
                                        <div class="progress region-progress flex-grow-1">
                                            <div class="progress-bar bg-info" style="width: 28%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim();
        const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim();
        const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim();
        const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim();
        const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
        const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim();
        const surfaceColor = getComputedStyle(document.documentElement).getPropertyValue('--surface-color').trim();
        // Revenue data sets per period
        const revenueData = {
            monthly: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                revenue: [4200, 5800, 4900, 6200, 5100, 7400, 6800, 8100, 7200, 9500, 8900, 10200],
                expenses: [2800, 3200, 2900, 3400, 3100, 3800, 3500, 4200, 3900, 4800, 4200, 5100],
                totals: {
                    revenue: '$48,295',
                    expenses: '$28,450',
                    profit: '$19,845'
                }
            },
            weekly: {
                categories: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                revenue: [8450, 9820, 7630, 10350],
                expenses: [5200, 6100, 4800, 6450],
                totals: {
                    revenue: '$36,250',
                    expenses: '$22,550',
                    profit: '$13,700'
                }
            },
            daily: {
                categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                revenue: [1850, 2100, 1620, 2340, 2780, 1200, 960],
                expenses: [1100, 1350, 980, 1420, 1680, 750, 580],
                totals: {
                    revenue: '$12,850',
                    expenses: '$7,860',
                    profit: '$4,990'
                }
            }
        };

        function formatCurrency(value) {
            return '$' + value.toLocaleString();
        }

        function updateSummary(period) {
            var totals = revenueData[period].totals;
            document.getElementById('summaryRevenue').textContent = totals.revenue;
            document.getElementById('summaryExpenses').textContent = totals.expenses;
            document.getElementById('summaryProfit').textContent = totals.profit;
        }
        // Revenue Overview Chart
        var revenueOptions = {
            series: [{
                name: 'Revenue',
                data: revenueData.monthly.revenue
            }, {
                name: 'Expenses',
                data: revenueData.monthly.expenses
            }],
            chart: {
                type: 'bar',
                height: 320,
                fontFamily: 'inherit',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                },
                stacked: false,
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 500
                }
            },
            colors: [accentColor, successColor],
            plotOptions: {
                bar: {
                    columnWidth: '50%',
                    borderRadius: 4,
                    borderRadiusApplication: 'end'
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: revenueData.monthly.categories,
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: mutedColor,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: mutedColor,
                        fontSize: '12px'
                    },
                    formatter: function(value) {
                        return '$' + (value / 1000).toFixed(0) + 'k';
                    }
                }
            },
            grid: {
                borderColor: borderColor,
                strokeDashArray: 4,
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            legend: {
                show: false
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return formatCurrency(value);
                    }
                }
            }
        };
        var revenueChart = new ApexCharts(document.querySelector('#revenueChart'), revenueOptions);
        revenueChart.render();
        // Period tab switching
        document.querySelectorAll('.dash-chart-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.dash-chart-tab').forEach(function(t) {
                    t.classList.remove('active');
                });
                tab.classList.add('active');
                var period = tab.getAttribute('data-period');
                var data = revenueData[period];
                revenueChart.updateOptions({
                    xaxis: {
                        categories: data.categories
                    },
                });
                revenueChart.updateSeries([{
                    name: 'Revenue',
                    data: data.revenue
                },
                    {
                        name: 'Expenses',
                        data: data.expenses
                    }
                ]);
                updateSummary(period);
            });
        });
        // Traffic Sources Donut Chart
        var trafficOptions = {
            series: [42, 28, 18, 12],
            chart: {
                type: 'donut',
                height: 260,
                fontFamily: 'inherit'
            },
            colors: [accentColor, successColor, warningColor, infoColor],
            labels: ['Direct', 'Organic', 'Referral', 'Social'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                fontSize: '13px',
                                color: mutedColor
                            },
                            value: {
                                fontSize: '22px',
                                fontWeight: 700,
                                formatter: function(val) {
                                    return val + '%';
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total Visits',
                                fontSize: '13px',
                                color: mutedColor,
                                formatter: function(w) {
                                    return w.globals.seriesTotals.reduce(function(a, b) {
                                        return a + b;
                                    }, 0) + '%';
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                show: false
            },
            stroke: {
                width: 3,
                colors: [surfaceColor]
            }
        };
        var trafficChart = new ApexCharts(document.querySelector('#trafficChart'), trafficOptions);
        trafficChart.render();
        // Update charts on theme change
        document.addEventListener('themeChanged', function() {
            var newBorderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
            var newMutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim();
            var newSurfaceColor = getComputedStyle(document.documentElement).getPropertyValue('--surface-color').trim();
            var newAccentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim();
            var newSuccessColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim();
            var newWarningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim();
            var newInfoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim();
            revenueChart.updateOptions({
                colors: [newAccentColor, newSuccessColor],
                grid: {
                    borderColor: newBorderColor
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: newMutedColor
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: newMutedColor
                        }
                    }
                }
            });
            trafficChart.updateOptions({
                colors: [newAccentColor, newSuccessColor, newWarningColor, newInfoColor],
                stroke: {
                    colors: [newSurfaceColor]
                }
            });
        });
    });
</script>
<?php $this->endSection() ?>