<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $page_title = $page_title ?? '[UNTITLED PAGE TITLE]';
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title . ' | ' . SITE_TITLE ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <!-- Favicons -->
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="icon">
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts - Manrope + Sora + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&family=Ubuntu+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.snow.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.bubble.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/choices.js/choices.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/flatpickr/flatpickr.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/toastr/toastr.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/datatables/datatables.min.css') ?>" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet">
    <!-- =======================================================
      * Template Name: FlexAdmin - Bootstrap Admin Template
      * Template URL: https://bootstrapmade.com/flex-admin-bootstrap-template/
      * Updated: Jun 09 2026 with Bootstrap v5.3.8
      * Author: BootstrapMade.com
      * License: https://bootstrapmade.com/license/
      ======================================================== -->
</head>
<body>
<!-- Header -->
<header class="header">
    <div class="header-left">
        <a href="<?= base_url('admin/dashboard') ?>" class="header-logo">
            <img src="<?= base_url('assets/img/logo.webp') ?>" alt="<?= SITE_TITLE ?>">
            <span><?= SITE_TITLE ?></span>
        </a>
    </div>
    <button class="sidebar-toggle" title="Toggle Sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div class="header-right">
        <div class="header-actions-desktop">
            <div class="header-action-cluster">
                <!-- LINKS -->
                <a class="header-action" href="<?= getenv('LINK_WWW') ?>" target="_blank" title="Public Website"><i class="bi bi-globe"></i></a>
                <a class="header-action" href="<?= getenv('LINK_CLIENT') ?>" target="_blank" title="Client System"><i class="bi bi-people"></i></a>
                <a class="header-action" href="<?= getenv('LINK_MART') ?>" target="_blank" title="Marketplace"><i class="bi bi-bag-check"></i></a>
                <!-- MODE -->
                <button class="header-action theme-toggle" title="Toggle Theme">
                    <i class="bi bi-moon theme-icon-dark"></i>
                    <i class="bi bi-brightness-high theme-icon-light"></i>
                </button>
                <!-- NOTIFICATIONS -->
                <div class="header-action-wrap dropdown notification-dropdown">
                    <button class="header-action dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="header-badge">4</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-menu">
                        <div class="notification-header">
                            <div>
                                <h6>Notifications</h6>
                                <span class="notification-count">4 unread</span>
                            </div>
                            <a href="#" class="notification-mark-read" data-notification-action="mark-all-read">Mark all read</a>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
                                <span class="notification-dot"></span>
                                <div class="notification-icon info"><i class="bi bi-rocket-takeoff"></i></div>
                                <div class="notification-content">
                                    <div class="notification-title">Deploy Ready</div>
                                    <div class="notification-text">Sprint 24 release has passed QA checks.</div>
                                    <span class="notification-time">5m ago</span>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <span class="notification-dot"></span>
                                <img src="<?= base_url('assets/img/avatars/avatar-2.webp') ?>" alt="" class="notification-avatar">
                                <div class="notification-content">
                                    <div class="notification-title">Mia sent feedback</div>
                                    <div class="notification-text">Please review the updated dashboard card hierarchy.</div>
                                    <span class="notification-time">21m ago</span>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <span class="notification-dot"></span>
                                <div class="notification-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
                                <div class="notification-content">
                                    <div class="notification-title">Storage threshold</div>
                                    <div class="notification-text">Media bucket reached 81% utilization.</div>
                                    <span class="notification-time">58m ago</span>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <span class="notification-dot"></span>
                                <div class="notification-icon success"><i class="bi bi-check2-circle"></i></div>
                                <div class="notification-content">
                                    <div class="notification-title">Payment received</div>
                                    <div class="notification-text">Invoice #INV-3921 was settled successfully.</div>
                                    <span class="notification-time">2h ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="<?= base_url('admin/notifications') ?>">Open notification center <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <span class="header-divider"></span>
            <div class="header-action-wrap dropdown user-dropdown">
                <button class="dropdown-toggle user-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= base_url('assets/img/profile-img.webp') ?>" alt="User Avatar" class="user-avatar">
                    <div class="user-brief">
                        <span class="user-name">[EIKIN RIKIKAWA]</span>
                        <span class="user-role">[OTTER CEO]</span>
                    </div>
                    <i class="bi bi-chevron-down user-chevron"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end user-menu">
                    <div class="user-menu-header">
                        <img src="<?= base_url('assets/img/profile-img.webp') ?>" alt="User Avatar" class="user-menu-avatar">
                        <div class="user-menu-info">
                            <div class="user-menu-name">[EIKIN RIKIKAWA]</div>
                            <div class="user-menu-email">[eikin@otternova.com]</div>
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
                        <a class="user-menu-logout" href="<?= base_url() ?>">
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
        <a class="mobile-menu-item" href="<?= getenv('LINK_WWW') ?>" target="_blank" title="Public Website">
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
        </a>
        <button class="mobile-menu-item theme-toggle" title="Toggle Theme">
            <i class="bi bi-moon theme-icon-dark"></i>
            <i class="bi bi-brightness-high theme-icon-light"></i>
            <span class="mobile-menu-label">Theme</span>
        </button>
        <a href="<?= base_url('admin/notifications') ?>" class="mobile-menu-item">
            <i class="bi bi-bell"></i>
            <span class="badge">4</span>
            <span class="mobile-menu-label">Notifications</span>
        </a>
        <a href="<?= base_url('admin/profile') ?>" class="mobile-menu-item">
            <i class="bi bi-sliders"></i>
            <span class="mobile-menu-label">Profile</span>
        </a>
        <a href="<?= base_url() ?>" class="mobile-menu-item mobile-menu-item-danger">
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
            <li class="nav-heading"><span>Otternauts</span></li>
            <li class="nav-item"><a class="nav-link <?= ('otternauts' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/otternauts') ?>"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Otternauts</span></a></li>
            <!-- CONTRACTS -->
            <li class="nav-heading"><span>Clients</span></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-contracts' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/contracts') ?>"><span class="nav-icon"><i class="bi bi-file-earmark-ruled"></i></span><span class="nav-text">Contracts</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-businesses' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/businesses') ?>"><span class="nav-icon"><i class="bi bi-suitcase-lg"></i></span><span class="nav-text">Businesses</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-users' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/users') ?>"><span class="nav-icon"><i class="bi bi-person-gear"></i></span><span class="nav-text">Users</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('clients-reports' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/clients/reports') ?>"><span class="nav-icon"><i class="bi bi-file-bar-graph"></i></span><span class="nav-text">Reports</span></a></li>
            <!-- INBOX -->
            <li class="nav-heading"><span>Inbox</span></li>
            <li class="nav-item"><a class="nav-link <?= ('inbox' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/inbox') ?>"><span class="nav-icon"><i class="bi bi-inbox"></i></span><span class="nav-text">Inbox</span></a></li>
            <!-- FINANCE -->
            <li class="nav-heading"><span>Finance</span></li>
            <li class="nav-item"><a class="nav-link <?= ('finance-ledger' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/finance/ledger') ?>"><span class="nav-icon"><i class="bi bi-list-columns"></i></span><span class="nav-text">Ledger</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('finance-ledger' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/finance/report') ?>"><span class="nav-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="nav-text">Report</span></a></li>
            <!-- LOGS -->
            <li class="nav-heading"><span>Maintenance</span></li>
            <li class="nav-item"><a class="nav-link <?= ('logs-error' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/logs/errors') ?>"><span class="nav-icon"><i class="bi bi-bug"></i></span><span class="nav-text">Error Logs</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('logs-emails' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/logs/emails') ?>"><span class="nav-icon"><i class="bi bi-envelope"></i></span><span class="nav-text">Email Logs</span></a></li>
            <!-- SETTINGS -->
            <li class="nav-heading"><span>Settings</span></li>
            <li class="nav-item"><a class="nav-link <?= ('settings-languages' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/settings/languages') ?>"><span class="nav-icon"><i class="bi bi-translate"></i></span><span class="nav-text">Languages</span></a></li>
            <li class="nav-item"><a class="nav-link <?= ('settings-countries' == $slug ? 'active' : '') ?>" href="<?= base_url('admin/settings/countries') ?>"><span class="nav-icon"><i class="bi bi-geo"></i></span><span class="nav-text">Countries</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-footer-user">
            <a href="<?= base_url('admin/profile') ?>" class="sidebar-footer-profile">
                <img src="<?= base_url('assets/img/profile-img.webp') ?>" alt="User Avatar" class="sidebar-footer-avatar">
                <div class="sidebar-footer-info">
                    <div class="sidebar-footer-name">[EIKIN RIKIKAWA]</div>
                    <div class="sidebar-footer-role">[OTTER CEO]</div>
                </div>
            </a>
            <div class="sidebar-footer-actions">
                <a href="<?= base_url('admin/notifications') ?>" class="sidebar-footer-action" title="Notifications">
                    <i class="bi bi-bell"></i>
                </a>
                <a href="<?= base_url() ?>" class="sidebar-footer-action sidebar-footer-logout" title="Logout">
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
<!-- JS Files -->
<script src="<?= base_url('assets/vendor/jquery/jquery-4.0.0.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/apexcharts/apexcharts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables/datatables.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/chart.js/chart.umd.js') ?>"></script>
<script src="<?= base_url('assets/vendor/echarts/echarts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/quill/quill.js') ?>"></script>
<script src="<?= base_url('assets/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/choices.js/choices.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/php-email-form/validate.js') ?>"></script>
<script src="<?= base_url('assets/vendor/toastr/toastr.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/sweetalert/sweetalert2.min.js') ?>"></script>
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
<script src="<?= base_url('assets/js/apps-sidebar-toggle.js') ?>"></script>
</body>
</html>