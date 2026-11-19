<?php
/**
 * The Client Experience department's dashboard; see App\Controllers\Admin::dashboard() and the API's
 * App\Libraries\ClientExperienceDashboard. Response times are clock time: nights, weekends and holidays included.
 * @var array|null  $clientExperience The API's data, null when it could not be loaded
 * @var string|null $error
 */

use App\Controllers\Admin;

?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="page-dashboard">
        <?php if ($clientExperience === null) : ?>
        <div class="alert alert-warning" role="alert">The Client Experience figures could not be loaded: <?= esc($error) ?></div>
        <?php else : ?>
        <?php
            $cx       = $clientExperience;
            $inbox    = $cx['inbox'];
            $response = $cx['response'];
            $orders   = $cx['orders'];
            $reviews  = $cx['reviews'];
            $onboard  = $cx['onboarding'];
            $days     = (int) $cx['period_days'];
            $recent   = (int) $cx['recent_days'];
            $asOf     = strtotime($cx['as_of'] . ' UTC');
            // how long ago a UTC time was, as of the figures
            $age      = static fn (?string $utc): string => $utc === null ? '—' : formatDuration(max(0, $asOf - strtotime($utc . ' UTC')));
            $open     = (int) $inbox['open'];
            $oldest   = $inbox['oldest_open_at'] === null ? null : $asOf - strtotime($inbox['oldest_open_at'] . ' UTC');

            $median   = $response['median_seconds'];
            $before   = $response['previous_median_seconds'];
            if ($median === null) {
                $medianTrend = 'none done in ' . $days . ' days';
                $medianClass = '';
            } elseif ($before === null) {
                $medianTrend = 'none the ' . $days . ' days before';
                $medianClass = '';
            } else {
                $medianTrend = 'was ' . formatDuration((int) $before);
                $medianClass = $median < $before ? ' positive' : ($median > $before ? ' negative' : '');
            }
            $resolved    = (int) $response['resolved'];
            $withinDay   = $resolved > 0 ? round((int) $response['within_day'] / $resolved * 100) : null;
            $received    = (int) $inbox['received'];
            $categories  = $cx['categories'];
            $categorised = array_sum($categories) - (int) $categories['none'];
        ?>
        <div class="row g-3 mb-3">
            <div class="col-xxl-8">
                <div class="card fx-hero-card h-100">
                    <div class="card-body">
                        <div class="fx-hero-head">
                            <div>
                                <div class="fx-overline">Contact Form · Last <?= $days ?> Days</div>
                                <h4 class="fx-hero-title"><?= $open === 0 ? 'Inbox clear' : esc(number_format($open)) . ' message' . ($open === 1 ? '' : 's') . ' waiting' ?></h4>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/inbox') ?>">Open Inbox</a>
                        </div>
                        <div class="fx-kpi-grid">
                            <div class="fx-kpi-item" title="Messages new or read, not done yet">
                                <span class="fx-kpi-label">Waiting</span>
                                <span class="fx-kpi-value"><?= esc(number_format($open)) ?></span>
                                <span class="fx-kpi-trend <?= $oldest === null ? 'positive' : ($oldest > 86400 ? 'negative' : '') ?>"><?= $oldest === null ? 'Nothing open' : 'oldest ' . esc(formatDuration($oldest)) ?></span>
                            </div>
                            <div class="fx-kpi-item" title="Median time from receiving a message to marking it done, for the messages done in the period">
                                <span class="fx-kpi-label">Time to Done</span>
                                <span class="fx-kpi-value"><?= esc(formatDuration($median)) ?></span>
                                <span class="fx-kpi-trend<?= $medianClass ?>"><?= esc($medianTrend) ?></span>
                            </div>
                            <div class="fx-kpi-item" title="Share of the period's done messages that were done within 24 hours">
                                <span class="fx-kpi-label">Done Within a Day</span>
                                <span class="fx-kpi-value"><?= $withinDay === null ? '—' : esc((string) $withinDay) . '%' ?></span>
                                <span class="fx-kpi-trend"><?= esc(number_format((int) $response['within_day'])) ?> of <?= esc(number_format($resolved)) ?> done</span>
                            </div>
                            <div class="fx-kpi-item" title="Median time from receiving a message to someone first opening it, for the period's messages">
                                <span class="fx-kpi-label">Time to First Read</span>
                                <span class="fx-kpi-value"><?= esc(formatDuration($response['first_read_median_seconds'])) ?></span>
                                <span class="fx-kpi-trend"><?= esc(number_format($received)) ?> received, <?= esc(number_format((int) $inbox['received_previous'])) ?> before</span>
                            </div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2">Clock time, nights, weekends and holidays included. Spam is left out.</p>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">What Messages Turned Out to Be</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (Admin::MESSAGE_CATEGORIES as $code => $category) : ?>
                        <div class="fx-pulse-stat" title="<?= esc($category['help'], 'attr') ?>">
                            <span class="fx-pulse-label"><span class="badge <?= esc($category['class'], 'attr') ?>"><?= esc($category['label']) ?></span></span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) ($categories[$code] ?? 0))) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Not categorised yet</span>
                            <span class="fx-pulse-value<?= (int) $categories['none'] > 0 ? ' text-warning' : '' ?>"><?= esc(number_format((int) $categories['none'])) ?></span>
                        </div>
                        <p class="text-secondary small mb-0"><?= esc(number_format($categorised)) ?> of <?= esc(number_format($received)) ?> messages of the last <?= $days ?> days categorised</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title">Oldest Open Messages</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($cx['queue'] === []) : ?>
                        <p class="text-secondary p-3 mb-0">Every message is done.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr><th>Subject</th><th>Language</th><th>Status</th><th>Received</th><th class="text-end">Waiting</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cx['queue'] as $message) : ?>
                                <?php $status = Admin::MESSAGE_STATUSES[$message['status']] ?? ['label' => $message['status'], 'class' => 'bg-secondary']; ?>
                                <tr>
                                    <td>
                                        <a class="fw-semibold" href="<?= base_url('admin/inbox/view/' . (int) $message['id']) ?>"><?= esc($message['subject']) ?></a>
                                        <div class="small text-secondary"><?= esc($message['name']) ?><?= empty($message['handled_by_name']) ? '' : ' · opened by ' . esc($message['handled_by_name']) ?></div>
                                    </td>
                                    <td><code><?= esc($message['locale']) ?></code></td>
                                    <td><span class="badge <?= esc($status['class'], 'attr') ?>"><?= esc($status['label']) ?></span></td>
                                    <td class="text-nowrap"><?= localTimeTag($message['created_at']) ?></td>
                                    <td class="text-end text-nowrap"><?= esc($age($message['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Inbox</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">New, not opened yet</span>
                            <span class="fx-pulse-value"><a href="<?= base_url('admin/inbox') ?>"><?= esc(number_format((int) $inbox['new'])) ?></a></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Spam caught, <?= $recent ?> days</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $inbox['spam_recent'])) ?></span>
                        </div>
                        <?php foreach ($inbox['by_locale'] as $locale) : ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">In <code><?= esc($locale['locale']) ?></code>, <?= $days ?> days</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $locale['messages'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Trouble · <?= $recent ?> Days</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Orders cancelled</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $orders['cancelled'])) ?></span>
                        </div>
                        <?php foreach ($orders['top_reasons'] as $reason) : ?>
                        <div class="fx-pulse-stat ps-3">
                            <span class="fx-pulse-label small">“<?= esc($reason['reason']) ?>”</span>
                            <span class="fx-pulse-value small"><?= esc(number_format((int) $reason['orders'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">No-shows</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $orders['no_shows'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Failed payments</span>
                            <span class="fx-pulse-value<?= (int) $orders['failed_payments'] > 0 ? ' text-warning' : '' ?>"><?= esc(number_format((int) $orders['failed_payments'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat" title="Cancelled orders of any time that the business still owes money back on">
                            <span class="fx-pulse-label">Refunds still owed</span>
                            <span class="fx-pulse-value<?= (int) $orders['refunds_owed'] > 0 ? ' text-danger' : '' ?>"><?= esc(number_format((int) $orders['refunds_owed'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Reviews</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat" title="Reviews waiting for their business to approve them">
                            <span class="fx-pulse-label">Waiting for approval</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $reviews['pending'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat" title="Ratings of 1 or 2 stars, not hidden, that the business has not replied to">
                            <span class="fx-pulse-label">Low ratings with no reply</span>
                            <span class="fx-pulse-value<?= (int) $reviews['low_unanswered'] > 0 ? ' text-danger' : '' ?>"><?= esc(number_format((int) $reviews['low_unanswered'])) ?></span>
                        </div>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Average rating, <?= $days ?> days</span>
                            <span class="fx-pulse-value"><?= $reviews['average'] === null ? '—' : esc($reviews['average']) . ' ★' ?> <span class="small text-secondary">(<?= esc(number_format((int) $reviews['reviews'])) ?>)</span></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card fx-pulse-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">New Businesses · <?= $days ?> Days</h5>
                    </div>
                    <div class="card-body">
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label">Signed up</span>
                            <span class="fx-pulse-value"><?= esc(number_format((int) $onboard['new'])) ?></span>
                        </div>
                        <?php foreach (['without_website' => 'No website yet', 'without_store' => 'No store yet', 'without_payment_method' => 'No way to be paid yet'] as $key => $label) : ?>
                        <div class="fx-pulse-stat">
                            <span class="fx-pulse-label"><?= esc($label) ?></span>
                            <span class="fx-pulse-value<?= (int) $onboard[$key] > 0 ? ' text-warning' : '' ?>"><?= esc(number_format((int) $onboard[$key])) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <p class="text-secondary small mb-0">The ones to reach out to and help get started.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?= $this->endSection() ?>
