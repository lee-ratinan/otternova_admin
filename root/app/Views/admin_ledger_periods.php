<?php
/**
 * Closing a ledger entity's books month by month; see App\Controllers\LedgerReports::periods() and the API's
 * App\Libraries\LedgerPeriods
 * @var array<string, string> $entities  public_id => name
 * @var array|null            $entity    The entity shown
 * @var array|null            $overview  The API's months, null when they could not be loaded
 * @var array<string, string> $snapshots report_type => title
 * @var bool                  $canReopen Whether this staff member is in the Executive Office
 * @var array|null            $posting   The billing's posting: pending (events waiting, with why) and last_posted_at
 * @var array|null            $notice    [bootstrap colour, message, details] after a close or reopen
 * @var string|null           $error
 */
$monthName = static fn (string $date): string => date('F Y', strtotime($date));
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <?php if ($notice !== null) : ?>
    <div class="alert alert-<?= esc($notice[0], 'attr') ?>" role="alert">
        <?= esc($notice[1]) ?>
        <?php if ($notice[2] !== []) : ?>
        <ul class="mb-0 mt-1"><?php foreach ($notice[2] as $detail) : ?><li><?= esc($detail) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($entities !== []) : ?>
    <form method="get" action="<?= base_url('admin/ledger/periods') ?>" class="row g-2 align-items-end mb-3">
        <div class="col-sm-4">
            <label class="form-label" for="periods-entity">Entity</label>
            <select class="form-select" id="periods-entity" name="entity" onchange="this.form.submit()">
                <?php foreach ($entities as $publicId => $name) : ?>
                <option value="<?= esc($publicId, 'attr') ?>"<?= $publicId === ($entity['public_id'] ?? '') ? ' selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= base_url('admin/ledger/reports/profit-loss') . '?entity=' . rawurlencode($entity['public_id'] ?? '') ?>"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></div>
    </form>
    <?php endif; ?>

    <?php if ($error !== null) : ?>
    <div class="alert alert-warning" role="alert"><?= esc($error) ?></div>
    <?php elseif ($entities === []) : ?>
    <div class="alert alert-info" role="alert">There is no entity yet. <a href="<?= base_url('admin/ledger/entities/create') ?>">Add the legal entity</a> whose books the ledger keeps.</div>
    <?php elseif ($overview !== null) : ?>
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Next to Close</h5></div>
                <div class="card-body">
                    <p class="text-secondary small">Months close in order, once they are over. A closed month's entries cannot change, and its reports are kept as they were, for filing.</p>
                    <p>Closed through: <strong><?= $overview['closed_through'] === null ? 'nothing yet' : esc(formatDate($overview['closed_through'], LOCALE)) ?></strong></p>
                    <?php if ($overview['next'] === null) : ?>
                    <p class="text-secondary mb-0">There are no entries to close yet.</p>
                    <?php else : ?>
                    <h5><?= esc($monthName($overview['next']['period_start'])) ?></h5>
                    <?php if ($overview['next']['problems'] !== []) : ?>
                    <div class="alert alert-warning small mb-0" role="alert">
                        It cannot close yet:
                        <ul class="mb-0"><?php foreach ($overview['next']['problems'] as $problem) : ?><li><?= esc($problem) ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php else : ?>
                    <form method="post" action="<?= base_url('admin/ledger/periods/close') ?>"
                          onsubmit="return confirm(<?= esc(json_encode('Close ' . $monthName($overview['next']['period_start']) . '? Its entries will no longer change, and its reports will be kept as they are now.'), 'attr') ?>)">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entity" value="<?= esc($entity['public_id'], 'attr') ?>">
                        <input type="hidden" name="period_start" value="<?= esc($overview['next']['period_start'], 'attr') ?>">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-lock"></i> Close <?= esc($monthName($overview['next']['period_start'])) ?></button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($overview['closed_through'] !== null && $canReopen) : ?>
                    <hr>
                    <form method="post" action="<?= base_url('admin/ledger/periods/reopen') ?>"
                          onsubmit="return confirm(<?= esc(json_encode('Reopen ' . $monthName($overview['closed_through']) . '? Its entries can change again; the reports kept when it closed stay as history.'), 'attr') ?>)">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entity" value="<?= esc($entity['public_id'], 'attr') ?>">
                        <label class="form-label" for="reopen-reason">Reopen <?= esc($monthName($overview['closed_through'])) ?></label>
                        <input class="form-control mb-2" id="reopen-reason" name="reason" maxlength="255" required placeholder="Why, such as a late bill">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-unlock"></i> Reopen</button>
                    </form>
                    <?php elseif ($overview['closed_through'] !== null) : ?>
                    <p class="text-secondary small mt-3 mb-0">Only the Executive Office can reopen a closed month.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($posting !== null) : ?>
            <div class="card mt-3">
                <div class="card-header"><h5 class="card-title mb-0">Subscription Billing</h5></div>
                <div class="card-body">
                    <p class="text-secondary small">Invoices, payments and the revenue earned each month are posted by the <a href="<?= base_url('admin/ledger/posting-rules') ?>">posting rules</a> every hour, and before a month closes.</p>
                    <p class="small">Last posted: <?= $posting['last_posted_at'] === null ? 'never' : localTimeTag($posting['last_posted_at']) ?></p>
                    <?php if ($posting['pending'] !== []) : ?>
                    <div class="alert alert-warning small" role="alert">
                        <?= esc(number_format(count($posting['pending']))) ?> billing event<?= count($posting['pending']) === 1 ? '' : 's' ?> waiting:
                        <ul class="mb-0">
                            <?php foreach (array_slice($posting['pending'], 0, 10) as $event) : ?>
                            <li><?= esc($event['invoice_no']) ?>: <?= esc($event['message']) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($posting['pending']) > 10) : ?><li>and <?= esc(number_format(count($posting['pending']) - 10)) ?> more</li><?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <form method="post" action="<?= base_url('admin/ledger/posting/sync') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="entity" value="<?= esc($entity['public_id'], 'attr') ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Post billing now</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Months</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Month</th><th>Status</th><th>Reports kept when it closed</th></tr></thead>
                        <tbody>
                        <?php foreach ($overview['months'] as $month) : ?>
                            <tr>
                                <td class="text-nowrap"><?= esc($monthName($month['period_start'])) ?></td>
                                <td>
                                    <?php if ($month['closed']) : ?>
                                    <span class="badge bg-success">Closed</span>
                                    <div class="small text-secondary"><?= localTimeTag($month['history'][0]['closed_at'] ?? null) ?> <?= esc($month['history'][0]['closed_by_name'] ?? '') ?></div>
                                    <?php else : ?>
                                    <span class="badge bg-secondary">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($month['history'] as $i => $close) : ?>
                                    <div class="small<?= $close['period_status'] !== 'C' ? ' text-secondary' : '' ?>">
                                        <?php if ($close['period_status'] !== 'C') : ?>
                                        <span class="badge text-bg-light border">Reopened</span> <?= localTimeTag($close['reopened_at']) ?> <?= esc($close['reopened_by_name'] ?? '') ?>: <?= esc($close['reopen_reason'] ?? '') ?><br>
                                        <?php endif; ?>
                                        <a class="me-2 text-nowrap" href="<?= base_url('admin/ledger/periods/' . rawurlencode($close['public_id']) . '/tax-pack') ?>" title="Every report of the month, as kept, in one ZIP"><i class="bi bi-file-earmark-zip"></i> Tax pack</a>
                                        <?php foreach ($snapshots as $type => $title) : ?>
                                        <span class="text-nowrap me-2"><a href="<?= base_url('admin/ledger/periods/' . rawurlencode($close['public_id']) . '/' . $type) ?>" title="<?= esc($title, 'attr') ?>"><?= esc($type) ?></a><a class="ms-1" href="<?= base_url('admin/ledger/periods/' . rawurlencode($close['public_id']) . '/' . $type . '/csv') ?>" title="<?= esc($title . ' as CSV', 'attr') ?>"><i class="bi bi-download"></i></a></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($overview['months'] === []) : ?>
                            <tr><td colspan="3" class="text-secondary">No entries yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-secondary">
                    <?php foreach ($snapshots as $type => $title) : ?><span class="me-3"><strong><?= esc($type) ?></strong> <?= esc($title) ?></span><?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?= $this->endSection() ?>
