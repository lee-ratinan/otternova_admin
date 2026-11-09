<?php
/**
 * The notification centre: every notification of the signed-in staff member, newest first; see
 * App\Controllers\Notifications. Filled by assets/js/notification-center.js, which the layout loads.
 */
$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="card" data-notification-page>
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <h5 class="card-title">All Notifications</h5>
                    <p class="card-subtitle" data-notification-summary>&nbsp;</p>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Show">
                    <button type="button" class="btn btn-outline-primary active" data-notification-status="">All</button>
                    <button type="button" class="btn btn-outline-primary" data-notification-status="unread">Unread</button>
                    <button type="button" class="btn btn-outline-primary" data-notification-status="read">Read</button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-notification-action="mark-all-read">
                    <i class="bi bi-check2-all"></i> Mark All Read
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-notification-action="delete-read">
                    <i class="bi bi-trash"></i> Delete Read
                </button>
            </div>
            <div class="card-body p-0">
                <div data-notification-rows></div>
                <div class="text-center p-3">
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" data-notification-more>Load More</button>
                </div>
            </div>
            <div class="card-footer small text-secondary">
                Read notifications are deleted automatically after a while.
            </div>
        </div>
    </section>
<?php $this->endSection() ?>
