<!DOCTYPE html>
<html lang="en-US">
<head>
    <?php
    $page_title = $page_title ?? '[UNTITLED PAGE TITLE]';
    // the signed-in staff member, as the sign-in response put them in the session; see App\Controllers\Home::createSession()
    helper('user');
    $sessionUser = (array) (session('user') ?? []);
    $userName    = userDisplayName($sessionUser);
    $userEmail   = trim((string) ($sessionUser['email_address'] ?? ''));
    $userRole    = trim((string) ($sessionUser['job_title'] ?? ''));
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title . ' | ' . SITE_TITLE ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <link href="<?= assets_url() ?>" rel="preconnect">
    <!-- Favicons -->
    <link href="<?= assets_url('img/logo.webp') ?>" rel="icon">
    <link href="<?= assets_url('img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&family=Ubuntu+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="<?= assets_url('vendor/bootstrap5.3.8/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/bootstrap-icons1.13.1/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/toastr/toastr.min.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/datatables3.1.4/datatables.min.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/flag-icons/css/flag-icons.min.css') ?>" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="<?= assets_url('themes/admin/css/main.css') ?>" rel="stylesheet">
    <!-- =======================================================
      * Template Name: FlexAdmin - Bootstrap Admin Template
      * Template URL: https://bootstrapmade.com/flex-admin-bootstrap-template/
      * Updated: Jun 09 2026 with Bootstrap v5.3.8
      * Author: BootstrapMade.com
      * License: https://bootstrapmade.com/license/
      ======================================================== -->
</head>
<body>
<header class="header">
    <div class="header-left">
        <a href="<?= base_url('admin/dashboard') ?>" class="header-logo">
            <img src="<?= assets_url('img/logo.webp') ?>" alt="<?= SITE_TITLE ?>">
            <span><?= SITE_TITLE ?></span>
        </a>
    </div>
    <button class="sidebar-toggle" title="Toggle Sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div class="header-right">
        <div class="header-actions-desktop">
            <div class="header-action-cluster">
                <!-- LINKS -->
<!--                <a class="header-action" href="--><?php //= getenv('LINK_WWW') ?><!--" target="_blank" title="Public Website"><i class="bi bi-globe"></i></a>-->
<!--                <a class="header-action" href="--><?php //= getenv('LINK_CLIENT') ?><!--" target="_blank" title="Client System"><i class="bi bi-people"></i></a>-->
<!--                <a class="header-action" href="--><?php //= getenv('LINK_MART') ?><!--" target="_blank" title="Marketplace"><i class="bi bi-bag-check"></i></a>-->
                <!-- MODE -->
                <button class="header-action theme-toggle" title="Toggle Theme">
                    <i class="bi bi-moon theme-icon-dark"></i>
                    <i class="bi bi-brightness-high theme-icon-light"></i>
                </button>
                <!-- NOTIFICATIONS: filled by assets > admin/js/notification-center.js; see App\Controllers\Notifications -->
                <?php
                $notificationCenter = [
                    'layout' => 'admin',
                    'poll'   => 60,
                    'urls'   => [
                        'latest'     => base_url('admin/notifications/latest'),
                        'list'       => base_url('admin/notifications/list'),
                        'open'       => base_url('admin/notifications/open'),
                        'readAll'    => base_url('admin/notifications/read-all'),
                        'delete'     => base_url('admin/notifications/delete'),
                        'deleteRead' => base_url('admin/notifications/delete-read'),
                    ],
                    'text'   => [
                        'unread'        => '{0} unread',
                        'allCaughtUp'   => 'All caught up',
                        'empty'         => 'No notifications',
                        'loadFailed'    => 'Could not load the notifications. Please try again.',
                        'delete'        => 'Delete',
                        'deleteConfirm' => 'Delete this notification?',
                    ],
                ];
                ?>
                <div class="header-action-wrap dropdown notification-dropdown" data-notification-center="<?= esc(json_encode($notificationCenter, JSON_UNESCAPED_SLASHES), 'attr') ?>">
                    <button class="header-action dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="header-badge d-none" data-notification-badge>0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-menu">
                        <div class="notification-header">
                            <div>
                                <h6>Notifications</h6>
                                <span class="notification-count" data-notification-count>&nbsp;</span>
                            </div>
                            <a href="#" class="notification-mark-read" data-notification-action="mark-all-read">Mark all read</a>
                        </div>
                        <div class="notification-list" data-notification-list></div>
                        <div class="notification-footer">
                            <a href="<?= base_url('admin/notifications') ?>">Open notification center <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <span class="header-divider"></span>
            <div class="header-action-wrap dropdown user-dropdown">
                <button class="dropdown-toggle user-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= esc(avatarUrl(session('user')['avatar_path'] ?? null), 'attr') ?>" alt="User Avatar" class="user-avatar">
                    <div class="user-brief">
                        <span class="user-name"><?= esc('' === $userName ? $userEmail : $userName) ?></span>
                        <?php if ('' !== $userRole) : ?><span class="user-role"><?= esc($userRole) ?></span><?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-down user-chevron"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end user-menu">
                    <div class="user-menu-header">
                        <img src="<?= esc(avatarUrl(session('user')['avatar_path'] ?? null), 'attr') ?>" alt="User Avatar" class="user-menu-avatar">
                        <div class="user-menu-info">
                            <div class="user-menu-name"><?= esc('' === $userName ? $userEmail : $userName) ?></div>
                            <div class="user-menu-email"><?= esc($userEmail) ?></div>
                        </div>
                    </div>
                    <div class="user-menu-body">
                        <a class="user-menu-item" href="<?= base_url('admin/profile') ?>">
                            <span class="user-menu-icon"><i class="bi bi-sliders"></i></span>
                            <span>Profile</span>
                        </a>
                        <a class="user-menu-item" href="<?= base_url('admin/notifications') ?>">
                            <span class="user-menu-icon"><i class="bi bi-bell"></i></span>
                            <span>Notifications</span>
                        </a>
                    </div>
                    <div class="user-menu-footer">
                        <a class="user-menu-logout" href="<?= base_url('logout') ?>">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="header-actions-mobile">
            <button class="header-action mobile-menu-toggle" title="More">
                <i class="bi bi-three-dots"></i>
            </button>
        </div>
    </div>
</header>
<!-- Mobile Menu -->
<div class="mobile-header-menu">
    <div class="mobile-header-menu-content">
        <?php  /* <a class="mobile-menu-item" href="<?= getenv('LINK_WWW') ?>" target="_blank" title="Public Website">
            <i class="bi bi-globe"></i>
            <span class="mobile-menu-label">Public</span>
        </a>
        <a class="mobile-menu-item" href="<?= getenv('LINK_CLIENT') ?>" target="_blank" title="Client System">
            <i class="bi bi-people"></i>
            <span class="mobile-menu-label">Client</span>
        </a>
        <a class="mobile-menu-item" href="<?= getenv('LINK_MART') ?>" target="_blank" title="Marketplace">
            <i class="bi bi-bag-check"></i>
            <span class="mobile-menu-label">Marketplace</span>
        </a> */ ?>
        <button class="mobile-menu-item theme-toggle" title="Toggle Theme">
            <i class="bi bi-moon theme-icon-dark"></i>
            <i class="bi bi-brightness-high theme-icon-light"></i>
            <span class="mobile-menu-label">Theme</span>
        </button>
        <a href="<?= base_url('admin/notifications') ?>" class="mobile-menu-item">
            <i class="bi bi-bell"></i>
            <span class="badge d-none" data-notification-badge>0</span><!-- the unread count, from notification-center.js -->
            <span class="mobile-menu-label">Notifications</span>
        </a>
        <a href="<?= base_url('admin/profile') ?>" class="mobile-menu-item">
            <i class="bi bi-sliders"></i>
            <span class="mobile-menu-label">Profile</span>
        </a>
        <a href="<?= base_url('logout') ?>" class="mobile-menu-item mobile-menu-item-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span class="mobile-menu-label">Sign Out</span>
        </a>
    </div>
</div>
<!-- Sidebar -->
<aside class="sidebar">
    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item"><a class="nav-link <?= ('dashboard' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/dashboard') ?>"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a></li>
            <!-- USERS -->
            <li class="nav-heading"><span>People</span></li>
            <?php if (inDepartment(EXECUTIVE_DEPARTMENT)) : ?>
            <li class="nav-item"><a class="nav-link <?= ('accounts' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/accounts') ?>"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Manage Accounts</span></a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link <?= ('public-profiles' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/public-profiles') ?>"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Staff’s Profiles</span></a></li>
            <!-- BUSINESS -->
            <li class="nav-heading"><span>Businesses</span></li>
            <li class="nav-item"><a class="nav-link <?= ('business-types' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/business-types') ?>"><span class="nav-icon"><i class="bi bi-ui-radios"></i></span><span class="nav-text">Business Types</span></a></li>
            <?php if (inDepartment(PLAN_DEPARTMENTS)) : // the Executive Office and Finance only; see App\Filters\DepartmentFilter ?>
            <li class="nav-item"><a class="nav-link <?= ('plans' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/plans') ?>"><span class="nav-icon"><i class="bi bi-currency-dollar"></i></span><span class="nav-text">Plans</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('plan-prices' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/plan-prices') ?>"><span class="nav-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="nav-text">Plan Prices</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('payment-methods' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/payment-methods') ?>"><span class="nav-icon"><i class="bi bi-cash-coin"></i></span><span class="nav-text">Payment Methods</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('discounts' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/discounts') ?>"><span class="nav-icon"><i class="bi bi-percent"></i></span><span class="nav-text">Discounts</span></a></li>
            <?php endif; ?>
            <!-- CONTRACTS -->
            <li class="nav-heading"><span>Clients</span></li>
            <?php if (inDepartment(PLAN_DEPARTMENTS)) : // the Executive Office and Finance only; see App\Filters\DepartmentFilter ?>
            <li class="nav-item"><a class="nav-link <?= ('clients-subscriptions' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/subscriptions') ?>"><span class="nav-icon"><i class="bi bi-file-earmark-ruled"></i></span><span class="nav-text">Subscriptions</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-invoices' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/invoices') ?>"><span class="nav-icon"><i class="bi bi-receipt"></i></span><span class="nav-text">Invoices &amp; Payments</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-withholding' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/withholding') ?>"><span class="nav-icon"><i class="bi bi-percent"></i></span><span class="nav-text">Withholding Tax</span></a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link <?= ('clients-businesses' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/businesses') ?>"><span class="nav-icon"><i class="bi bi-suitcase-lg"></i></span><span class="nav-text">Businesses</span></a></li>
            <!-- LEGAL -->
            <?php if (inDepartment(LEGAL_DEPARTMENTS)) : // the Executive Office and Client Experience only; see App\Filters\DepartmentFilter ?>
            <li class="nav-heading"><span>Legal</span></li>
            <li class="nav-item"><a class="nav-link <?= ('legal-versions' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/legal/versions') ?>"><span class="nav-icon"><i class="bi bi-file-earmark-text"></i></span><span class="nav-text">Terms &amp; Privacy</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('legal-acceptances' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/legal/acceptances') ?>"><span class="nav-icon"><i class="bi bi-check2-square"></i></span><span class="nav-text">Acceptances</span></a></li>
            <?php endif; ?>
            <!-- INBOX -->
            <li class="nav-heading"><span>Inbox</span></li>
            <li class="nav-item"><a class="nav-link <?= ('inbox' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/inbox') ?>"><span class="nav-icon"><i class="bi bi-inbox"></i></span><span class="nav-text">Inbox</span></a></li>
            <!-- TRAFFIC -->
            <li class="nav-heading"><span>Traffic</span></li>
            <li class="nav-item"><a class="nav-link <?= ('traffic-www' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/traffic/www') ?>"><span class="nav-icon"><i class="bi bi-globe2"></i></span><span class="nav-text">Website</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('traffic-mart' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/traffic/mart') ?>"><span class="nav-icon"><i class="bi bi-shop"></i></span><span class="nav-text">Marketplace</span></a></li>
            <!-- FINANCE -->
            <?php if (inDepartment(LEDGER_DEPARTMENTS)) : // the Executive Office and Finance only; see App\Filters\DepartmentFilter ?>
            <li class="nav-heading"><span>Finance</span></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-reports' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/reports') ?>"><span class="nav-icon"><i class="bi bi-file-earmark-bar-graph"></i></span><span class="nav-text">Financial Reports</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-periods' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/periods') ?>"><span class="nav-icon"><i class="bi bi-lock"></i></span><span class="nav-text">Closing the Books</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-entries' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger') ?>"><span class="nav-icon"><i class="bi bi-list-columns"></i></span><span class="nav-text">Ledger</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-entities' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/entities') ?>"><span class="nav-icon"><i class="bi bi-briefcase"></i></span><span class="nav-text">Entities</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-accounts' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/accounts') ?>"><span class="nav-icon"><i class="bi bi-bank"></i></span><span class="nav-text">Ledger Accounts</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-tax-codes' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/tax-codes') ?>"><span class="nav-icon"><i class="bi bi-percent"></i></span><span class="nav-text">Tax Codes</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-posting-rules' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/posting-rules') ?>"><span class="nav-icon"><i class="bi bi-arrow-left-right"></i></span><span class="nav-text">Posting Rules</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-categories' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/categories') ?>"><span class="nav-icon"><i class="bi bi-tags"></i></span><span class="nav-text">Categories</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('ledger-counterparties' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/ledger/counterparties') ?>"><span class="nav-icon"><i class="bi bi-building"></i></span><span class="nav-text">Counterparties</span></a></li>
            <?php endif; ?>
            <!-- LOGS -->
            <li class="nav-heading"><span>Maintenance</span></li>
            <?php if (inDepartment(LOG_DEPARTMENTS)) : // the Executive Office and Engineering only; see App\Filters\DepartmentFilter ?>
            <li class="nav-item"><a class="nav-link <?= ('logs-activities' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/logs/activities') ?>"><span class="nav-icon"><i class="bi bi-list-stars"></i></span><span class="nav-text">Activity Logs</span></a></li>
            <?php endif; ?>
            <?php if (inDepartment(ERROR_LOG_DEPARTMENTS)) : // the Executive Office and Engineering only; see App\Filters\DepartmentFilter ?>
            <li class="nav-item"><a class="nav-link <?= ('logs-error' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/logs/errors') ?>"><span class="nav-icon"><i class="bi bi-bug"></i></span><span class="nav-text">Error Logs</span></a></li>
            <?php endif; ?>
            <?php if (inDepartment(EMAIL_LOG_DEPARTMENTS)) : // the Executive Office, Client Experience and Engineering only; see App\Filters\DepartmentFilter ?>
            <li class="nav-item"><a class="nav-link <?= ('logs-emails' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/logs/emails') ?>"><span class="nav-icon"><i class="bi bi-envelope"></i></span><span class="nav-text">Email Logs</span></a></li>
            <?php endif; ?>
            <!-- SETTINGS -->
            <li class="nav-heading"><span>Settings</span></li>
            <li class="nav-item"><a class="nav-link <?= ('settings-languages' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/settings/languages') ?>"><span class="nav-icon"><i class="bi bi-translate"></i></span><span class="nav-text">Languages</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('settings-countries' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/settings/countries') ?>"><span class="nav-icon"><i class="bi bi-geo"></i></span><span class="nav-text">Countries</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-footer-user">
            <a href="<?= base_url('admin/profile') ?>" class="sidebar-footer-profile">
                <img src="<?= esc(avatarUrl(session('user')['avatar_path'] ?? null), 'attr') ?>" alt="User Avatar" class="sidebar-footer-avatar">
                <div class="sidebar-footer-info">
                    <div class="sidebar-footer-name"><?= esc('' === $userName ? $userEmail : $userName) ?></div>
                    <?php if ('' !== $userRole) : ?><div class="sidebar-footer-role"><?= esc($userRole) ?></div><?php endif; ?>
                </div>
            </a>
            <div class="sidebar-footer-actions">
                <a href="<?= base_url('admin/notifications') ?>" class="sidebar-footer-action" title="Notifications">
                    <i class="bi bi-bell"></i>
                </a>
                <a href="<?= base_url('logout') ?>" class="sidebar-footer-action sidebar-footer-logout" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</aside>
<div class="sidebar-overlay"></div>
<main class="main">
    <div class="main-content page-tables-datatables">
        <div class="page-header">
            <h1 class="page-title"><?= $page_title ?></h1>
            <nav class="breadcrumb">
                <a href="<?= base_url('admin/dashboard') ?>" class="breadcrumb-item">Dashboard</a>
                <?php if (isset($breadcrumbs)) : ?>
                    <?php foreach ($breadcrumbs as $key => $value): ?>
                        <a href="<?= $key ?>" class="breadcrumb-item">&nbsp; <?= $value ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ('dashboard' != $slug) : ?>
                    <span class="breadcrumb-item active">&nbsp; <?= $page_title ?></span>
                <?php endif; ?>
            </nav>
        </div>
        <!-- PAGE CONTENTS GO HERE -->
        <?= $this->renderSection('content') ?>
    </div>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-credits">
                <div class="footer-copyright">
                    © 2025-<?= date('Y') ?> <a href="<?= getenv('LINK_WWW') ?>" target="_blank"><?= SITE_TITLE ?></a>
                </div>
            </div>
        </div>
    </footer>
</main>
<a href="#" class="back-to-top">
    <i class="bi bi-arrow-up"></i>
</a>
<!-- Ribbon -->
<?php $env = getenv('CI_ENVIRONMENT'); ?>
<?php if (in_array($env, ['beta', 'development'])): ?>
    <?php if ('development' == $env) { $env = 'dev'; } ?>
    <div class="corner-ribbon">
        <span><?= esc($env) ?></span>
    </div>
<?php endif; ?>
<!-- JS Files -->
<script src="<?= assets_url('vendor/jquery4.0.0/jquery-4.0.0.min.js') ?>"></script>
<script src="<?= assets_url('vendor/bootstrap5.3.8/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= assets_url('vendor/apexcharts/apexcharts.min.js') ?>"></script>
<script src="<?= assets_url('vendor/datatables3.1.4/datatables.min.js') ?>"></script>
<script src="<?= assets_url('vendor/chart.js/chart.umd.js') ?>"></script>
<script src="<?= assets_url('vendor/echarts/echarts.min.js') ?>"></script>
<script src="<?= assets_url('vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= assets_url('vendor/php-email-form/validate.js') ?>"></script>
<script src="<?= assets_url('vendor/toastr/toastr.min.js') ?>"></script>
<script src="<?= assets_url('vendor/sweetalert/sweetalert2.min.js') ?>"></script>
<script src="<?= assets_url('themes/admin/js/theme.js') ?>"></script>
<script src="<?= assets_url('themes/admin/js/main.js') ?>"></script>
<script src="<?= assets_url('themes/admin/js/apps-sidebar-toggle.js') ?>"></script>
<script src="<?= assets_url('themes/admin/js/notification-center.js') ?>"></script>
<script>
    // Ends the session when the JWT expires (session 'expires_at'): logout destroys the session and returns to the login page.
    // The deadline counts the seconds the server has left from this page's load, so a skewed client clock does not matter;
    // it is checked on a timer and whenever the tab comes back, since timers stall while a laptop sleeps.
    (function () {
        const logoutUrl = <?= json_encode(base_url('logout')) ?>;
        const deadline  = Date.now() + <?= max(0, (int) session('expires_at') - time()) ?> * 1000;
        let leaving     = false;
        function leave(url) {
            if (leaving) return;
            leaving = true;
            window.location.replace(url || logoutUrl);
        }
        function check() {
            if (Date.now() >= deadline) leave();
        }
        setTimeout(check, Math.min(Math.max(deadline - Date.now(), 0), 2147483647));
        setInterval(check, 15000);
        document.addEventListener('visibilitychange', check);
        window.addEventListener('focus', check);
        check();
        // AuthFilter answers AJAX with 401 once the session is gone (e.g. signed out in another tab)
        $(document).ajaxError(function (event, xhr) {
            if (401 === xhr.status) leave(xhr.responseJSON && xhr.responseJSON.redirect);
        });
    })();
</script>
</body>
</html>