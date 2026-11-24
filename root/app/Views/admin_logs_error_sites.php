<?php
/**
 * The apps whose error logs can be read; see App\Controllers\ErrorLogs::sites()
 * @var array<string, array> $sites site => label, env, icon, configured, readable, count, size, latest
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ErrorLogs as E;

$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <?php foreach ($sites as $site => $s): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi <?= esc($s['icon'], 'attr') ?> me-1"></i> <?= esc($s['label']) ?></h5>
                            <p class="card-subtitle"><code><?= esc($s['env']) ?></code></p>
                        </div>
                        <div class="card-body">
                            <?php if (! $s['readable']): ?>
                                <p class="text-danger mb-0">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?= $s['configured'] ? 'The folder in <code>' . esc($s['env']) . '</code> cannot be read.' : '<code>' . esc($s['env']) . '</code> is not set in this app\'s .env.' ?>
                                </p>
                            <?php else: ?>
                                <div class="row mb-2">
                                    <div class="col-6 text-secondary">Files</div>
                                    <div class="col-6"><?= number_format($s['count']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-secondary">Total size</div>
                                    <div class="col-6"><?= esc(E::size($s['size'])) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6 text-secondary">Latest</div>
                                    <div class="col-6"><?= null === $s['latest'] ? '<span class="text-muted">&mdash;</span>' : esc($s['latest']['date']) ?></div>
                                </div>
                                <?php if (null !== $s['latest']): ?>
                                    <div class="row">
                                        <div class="col-6 text-secondary">Last written</div>
                                        <div class="col-6 small"><?= B::time($s['latest']['modified']) ?></div>
                                    </div>
                                <?php endif ?>
                            <?php endif ?>
                        </div>
                        <?php if ($s['readable']): ?>
                            <div class="card-footer text-end">
                                <a class="btn btn-outline-primary btn-sm" href="<?= esc(base_url('admin/logs/errors/' . $site), 'attr') ?>">View files <i class="bi bi-arrow-right"></i></a>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </section>
<?= $this->endSection() ?>
