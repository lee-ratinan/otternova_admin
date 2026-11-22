<?php
/**
 * One log file's entries, newest first, with a filter by level and a search; see App\Controllers\ErrorLogs::file()
 * @var string $site
 * @var array $info label, env, icon
 * @var string $name The file name
 * @var int $size Bytes
 * @var string $modified UTC
 * @var bool $truncated Whether only the end of the file is shown
 * @var list<array{level: string, time: string, message: string, details: string}> $entries Newest first
 * @var array<string, int> $counts level => entries, most severe first
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ErrorLogs as E;

$this->extend('_layout');
$download = base_url('admin/logs/errors/' . $site . '/' . $name) . '?download=1';
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-start">
                        <div class="me-auto">
                            <h5 class="card-title"><i class="bi <?= esc($info['icon'], 'attr') ?> me-1"></i> <?= esc($info['label']) ?> · <code><?= esc($name) ?></code></h5>
                            <p class="card-subtitle"><?= esc(E::size($size)) ?> · last written <?= B::time($modified) ?> · <?= number_format(count($entries)) ?> entries, newest first</p>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= esc($download, 'attr') ?>"><i class="bi bi-download"></i> Download</a>
                    </div>
                    <div class="card-body">
                        <?php if ($truncated): ?>
                            <div class="alert alert-warning py-2"><i class="bi bi-scissors"></i> This file is larger than <?= esc(E::size(E::MAX_BYTES)) ?>, so only its end is shown. Download it to read all of it.</div>
                        <?php endif ?>
                        <?php if ([] === $entries): ?>
                            <p class="text-secondary mb-0">This file has no log entries.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3" id="errorLogFilters">
                                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Level">
                                    <button type="button" class="btn btn-outline-secondary active" data-level="">All <span class="badge bg-secondary"><?= number_format(count($entries)) ?></span></button>
                                    <?php foreach ($counts as $level => $count): ?>
                                        <button type="button" class="btn btn-outline-secondary" data-level="<?= esc($level, 'attr') ?>"><?= esc(ucfirst(strtolower($level))) ?> <span class="badge <?= esc(E::LEVELS[$level], 'attr') ?>"><?= number_format($count) ?></span></button>
                                    <?php endforeach ?>
                                </div>
                                <input type="search" class="form-control form-control-sm ms-md-auto" style="max-width:20rem" placeholder="Search the entries" aria-label="Search the entries" id="errorLogSearch">
                            </div>
                            <p class="text-secondary small d-none" id="errorLogNone">No entry matches.</p>
                            <div class="list-group" id="errorLogEntries">
                                <?php foreach ($entries as $entry): ?>
                                    <div class="list-group-item" data-entry-level="<?= esc($entry['level'], 'attr') ?>">
                                        <div class="d-flex flex-wrap gap-2 align-items-baseline">
                                            <span class="badge <?= esc(E::LEVELS[$entry['level']], 'attr') ?>"><?= esc($entry['level']) ?></span>
                                            <small class="text-secondary text-nowrap"><?= B::time($entry['time']) ?></small>
                                        </div>
                                        <div class="font-monospace small mt-1 text-break" style="white-space:pre-wrap"><?= esc($entry['message']) ?></div>
                                        <?php if ('' !== $entry['details']): ?>
                                            <details class="mt-1">
                                                <summary class="small text-secondary">Details (<?= number_format(substr_count($entry['details'], "\n") + 1) ?> lines)</summary>
                                                <pre class="small mb-0 mt-1 p-2 bg-body-tertiary rounded" style="max-height:24rem;overflow:auto"><?= esc($entry['details']) ?></pre>
                                            </details>
                                        <?php endif ?>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php if ([] !== $entries): ?>
    <script>
    (function () {
      'use strict';
      var buttons = document.querySelectorAll('#errorLogFilters [data-level]');
      var search  = document.getElementById('errorLogSearch');
      var items   = Array.prototype.slice.call(document.querySelectorAll('#errorLogEntries [data-entry-level]'));
      var none    = document.getElementById('errorLogNone');
      var level   = '';
      var timer   = null;

      function apply() {
        var term  = search.value.trim().toLowerCase();
        var shown = 0;
        items.forEach(function (item) {
          var match = ('' === level || item.getAttribute('data-entry-level') === level)
            && ('' === term || item.textContent.toLowerCase().indexOf(term) !== -1);
          item.classList.toggle('d-none', !match);
          if (match) { shown++; }
        });
        none.classList.toggle('d-none', shown !== 0);
      }

      buttons.forEach(function (button) {
        button.addEventListener('click', function () {
          buttons.forEach(function (b) { b.classList.remove('active'); });
          button.classList.add('active');
          level = button.getAttribute('data-level');
          apply();
        });
      });
      search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(apply, 200);
      });
    })();
    </script>
    <?php endif ?>
<?= $this->endSection() ?>
