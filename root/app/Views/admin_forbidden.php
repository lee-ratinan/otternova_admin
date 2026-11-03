<?php
/**
 * Shown by App\Filters\DepartmentFilter when the signed-in staff member's department may not open the page.
 * Expects: $message
 */
$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="card">
            <div class="card-body py-5 text-center">
                <i class="bi bi-shield-lock fs-1 text-secondary"></i>
                <p class="mt-3 mb-4"><?= esc($message) ?></p>
                <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    </section>
<?= $this->endSection() ?>
