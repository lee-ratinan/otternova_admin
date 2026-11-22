<?php
/**
 * One app's log files, newest first; see App\Controllers\ErrorLogs::files()
 * @var string $site
 * @var array $info label, env, icon
 * @var bool $readable Whether the folder in .env can be read
 * @var list<array{name: string, date: string, size: int, modified: string}> $files
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ErrorLogs as E;

$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi <?= esc($info['icon'], 'attr') ?> me-1"></i> <?= esc($info['label']) ?> Log Files</h5>
                        <p class="card-subtitle">Newest first; every time is UTC in the file, shown here in your timezone</p>
                    </div>
                    <div class="card-body px-0">
                        <?php if (! $readable): ?>
                            <p class="text-danger mx-3 mb-0"><i class="bi bi-exclamation-triangle"></i> The folder in <code><?= esc($info['env']) ?></code> cannot be read.</p>
                        <?php elseif ([] === $files): ?>
                            <p class="text-secondary mx-3 mb-0">There are no log files.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:1%"><span class="visually-hidden">View</span></th>
                                        <th>File</th>
                                        <th>Date</th>
                                        <th class="text-end">Size</th>
                                        <th>Last Written</th>
                                        <th style="width:1%"><span class="visually-hidden">Download</span></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($files as $file): $url = base_url('admin/logs/errors/' . $site . '/' . $file['name']) ?>
                                        <tr>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= esc($url, 'attr') ?>" title="View"><i class="bi bi-eye"></i></a></td>
                                            <td><code><?= esc($file['name']) ?></code></td>
                                            <td class="text-nowrap"><?= esc($file['date']) ?></td>
                                            <td class="text-end text-nowrap"><?= esc(E::size($file['size'])) ?></td>
                                            <td class="text-nowrap"><?= B::time($file['modified']) ?></td>
                                            <td><a class="btn btn-sm btn-outline-secondary" href="<?= esc($url . '?download=1', 'attr') ?>" title="Download"><i class="bi bi-download"></i></a></td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?= $this->endSection() ?>
