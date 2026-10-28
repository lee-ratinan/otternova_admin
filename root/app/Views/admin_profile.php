<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="main-content page-profile">
        <div class="page-profile">
            <div class="card profile-shell mb-4">
                <div class="profile-hero">
                    <div class="profile-hero-main">
                        <div class="profile-avatar-wrap">
                            <img src="<?= base_url('assets/img/profile-img.webp') ?>" alt="[EIKIN RIKIKAWA]" class="profile-avatar">
                        </div>
                        <div class="profile-hero-copy">
                            <div class="profile-eyebrow">[OTTER CEO]</div>
                            <h1 class="profile-name">[EIKIN RIKIKAWA]</h1>
                            <p class="profile-role">[USER ROLE]</p>
                            <div class="profile-meta-row">
                                <span><i class="bi bi-geo-alt"></i> [LOCATION]</span>
                                <span><i class="bi bi-calendar3"></i> Joined [JOINED DATE]</span>
                            </div>
                        </div>
                    </div>
                    <div class="profile-hero-actions">
                        <a href="<?= base_url('admin/profile/edit') ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit Profile</a>
                        <a href="<?= base_url('admin/public-profile/eikin-rikikawa') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-share me-1"></i> Share</a>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title">About</h5>
                        </div>
                        <div class="card-body">
                            <p class="profile-about">[SHORT INTRO]</p>
                            <div class="profile-contact-list">
                                <div class="profile-contact-item"><i class="bi bi-envelope"></i> [eikin@otternova.com]</div>
                                <div class="profile-contact-item"><i class="bi bi-telephone"></i> [<?= formatPhoneNumber('+6597754577') ?>]</div>
                                <div class="profile-contact-item"><i class="bi bi-house-door"></i> [Singapore]</div>
                                <div class="profile-contact-item"><i class="bi bi-building"></i> [Bangkok, Thailand]</div>
                                <div class="profile-contact-item"><i class="bi bi-globe"></i> [lee.ratinan.com]</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="profile-info-grid">
                                <div class="profile-info-item"><span class="profile-info-label">Department</span><span class="profile-info-value">Product Design</span></div>
                                <div class="profile-info-item"><span class="profile-info-label">Team</span><span class="profile-info-value">Experience Platform</span></div>
                                <div class="profile-info-item"><span class="profile-info-label">Manager</span><span class="profile-info-value">Chris Thompson</span></div>
                                <div class="profile-info-item"><span class="profile-info-label">Location</span><span class="profile-info-value">New York HQ</span></div>
                                <div class="profile-info-item"><span class="profile-info-label">Employment Type</span><span class="profile-info-value">Full-time</span></div>
                                <div class="profile-info-item"><span class="profile-info-label">Timezone</span><span class="profile-info-value">UTC-5 (EST)</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endSection() ?>