<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="main-content page-staff">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="card-title mb-0">Staff’s Profiles</h5>
                    <div class="small text-secondary"><?= count($staff) ?> staff member<?= 1 === count($staff) ? '' : 's' ?></div>
                </div>
                <?php if (count($staff) > 0) : ?>
                <input type="search" id="staff-search" class="form-control form-control-sm staff-search" placeholder="Find by name" aria-label="Find a staff member by name">
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (0 === count($staff)) : ?>
                    <p class="text-secondary mb-0">There are no staff profiles to show yet.</p>
                <?php else : ?>
                    <div class="staff-gallery" id="staff-gallery">
                        <?php foreach ($staff as $person) : ?>
                            <a class="staff-tile" href="<?= base_url('admin/public-profile/' . rawurlencode($person['uid'])) ?>"
                               data-name="<?= esc(mb_strtolower($person['full_name']), 'attr') ?>"
                               title="<?= esc(trim($person['full_name'] . ($person['job_title'] ? ' · ' . $person['job_title'] : '')), 'attr') ?>">
                                <img src="<?= esc($person['avatar'], 'attr') ?>" alt="" class="staff-tile-avatar" loading="lazy" width="112" height="112">
                                <span class="staff-tile-name"><?= esc($person['full_name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-secondary mb-0 d-none" id="staff-no-match">No staff member matches that name.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (count($staff) > 0) : ?>
    <script>
        (function () {
            const input = document.getElementById('staff-search');
            const tiles = document.querySelectorAll('#staff-gallery .staff-tile');
            const none  = document.getElementById('staff-no-match');
            input.addEventListener('input', function () {
                const q = input.value.trim().toLowerCase();
                let shown = 0;
                tiles.forEach(function (tile) {
                    const match = '' === q || tile.dataset.name.includes(q);
                    tile.classList.toggle('d-none', !match);
                    shown += match ? 1 : 0;
                });
                none.classList.toggle('d-none', shown > 0);
            });
        })();
    </script>
    <?php endif; ?>
<?php $this->endSection() ?>
