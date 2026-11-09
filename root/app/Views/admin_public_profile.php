<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
<?php
/** @var array $person work-facing fields only; see App\Controllers\Admin::publicProfile() */
$showPreferred = '' !== $person['preferred_name'] && $person['preferred_name'] !== $person['full_name'];
?>
    <div class="main-content page-profile">
        <div class="mb-3">
            <a href="<?= base_url('admin/public-profiles') ?>" class="btn btn-link btn-sm px-0"><i class="bi bi-arrow-left me-1"></i> All staff</a>
        </div>
        <div class="card profile-shell mb-4">
            <div class="profile-hero">
                <div class="profile-hero-main">
                    <div class="profile-avatar-wrap">
                        <img src="<?= esc($person['avatar'], 'attr') ?>" alt="<?= esc($person['full_name'], 'attr') ?>" class="profile-avatar">
                    </div>
                    <div class="profile-hero-copy">
                        <?php if ('' !== $person['department']) : ?>
                            <div class="profile-eyebrow"><?= esc($person['department']) ?></div>
                        <?php endif; ?>
                        <h1 class="profile-name"><?= esc($person['full_name']) ?></h1>
                        <?php if ('' !== $person['job_title']) : ?>
                            <p class="profile-role"><?= esc($person['job_title']) ?></p>
                        <?php endif; ?>
                        <div class="profile-meta-row">
                            <?php if ($showPreferred) : ?>
                                <span><i class="bi bi-person"></i> Goes by <?= esc($person['preferred_name']) ?></span>
                            <?php endif; ?>
                            <?php if ('' !== $person['work_country']) : ?>
                                <span><i class="bi bi-geo-alt"></i> <?= esc($person['work_country']) ?></span>
                            <?php endif; ?>
                            <?php if ('' !== $person['start_date']) : ?>
                                <span><i class="bi bi-calendar3"></i> Joined <?= esc(formatDate($person['start_date'], LOCALE)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($person['own']) : ?>
                    <div class="profile-hero-actions">
                        <a href="<?= base_url('admin/profile') ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit My Profile</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title">About</h5>
                    </div>
                    <div class="card-body">
                        <?php if ('' !== $person['status_message']) : ?>
                            <p class="fw-semibold mb-2"><i class="bi bi-chat-quote me-1"></i> <?= esc($person['status_message']) ?></p>
                        <?php endif; ?>
                        <?php if ('' !== $person['bio']) : ?>
                            <p class="profile-about"><?= nl2br(esc($person['bio'])) ?></p>
                        <?php elseif ('' === $person['status_message']) : ?>
                            <p class="profile-about text-secondary"><?= esc($showPreferred ? $person['preferred_name'] : $person['full_name']) ?> has not written a bio yet.</p>
                        <?php endif; ?>
                        <div class="profile-contact-list">
                            <?php if ('' !== $person['work_email']) : ?>
                                <div class="profile-contact-item"><i class="bi bi-envelope"></i> <a href="mailto:<?= esc($person['work_email'], 'attr') ?>"><?= esc($person['work_email']) ?></a></div>
                            <?php endif; ?>
                            <?php if ('' !== $person['work_telephone']) : ?>
                                <div class="profile-contact-item"><i class="bi bi-telephone"></i> <a href="tel:<?= esc($person['work_telephone'], 'attr') ?>"><?= esc(formatPhoneNumber($person['work_telephone'])) ?></a></div>
                            <?php endif; ?>
                            <?php if ('' !== $person['work_country']) : ?>
                                <div class="profile-contact-item"><i class="bi bi-building"></i> <?= esc($person['work_country']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="profile-info-grid">
                            <div class="profile-info-item"><span class="profile-info-label">Department</span><span class="profile-info-value"><?= esc($person['department'] ?: '—') ?></span></div>
                            <div class="profile-info-item"><span class="profile-info-label">Job Title</span><span class="profile-info-value"><?= esc($person['job_title'] ?: '—') ?></span></div>
                            <div class="profile-info-item"><span class="profile-info-label">Line Manager</span><span class="profile-info-value">
                                <?php if (null === $person['manager']) : ?>
                                    —
                                <?php elseif (null !== $person['manager']['uid']) : ?>
                                    <a href="<?= base_url('admin/public-profile/' . rawurlencode($person['manager']['uid'])) ?>"><?= esc($person['manager']['name']) ?></a>
                                <?php else : ?>
                                    <?= esc($person['manager']['name']) ?>
                                <?php endif; ?>
                            </span></div>
                            <div class="profile-info-item"><span class="profile-info-label">Employment Type</span><span class="profile-info-value"><?= esc($person['employment_type'] ?: '—') ?></span></div>
                            <div class="profile-info-item"><span class="profile-info-label">Work Country</span><span class="profile-info-value"><?= esc($person['work_country'] ?: '—') ?></span></div>
                            <div class="profile-info-item"><span class="profile-info-label">Joined</span><span class="profile-info-value"><?= '' !== $person['start_date'] ? esc(formatDate($person['start_date'], LOCALE)) : '—' ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endSection() ?>
