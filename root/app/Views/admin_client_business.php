<?php
/**
 * One business's business_master details and everything under it, in tabs: its staff, its stores, and its products
 * and services; see App\Controllers\ClientBusinesses::businessView()
 * @var array $business       From LINK_INTERNAL_API v1/staff/businesses/business; tax_id is null for staff outside the
 *                            Executive Office and Finance
 * @var array $staff          {rows, error}: rows from v1/staff/businesses/staff
 * @var array $stores         {rows, error}: rows from v1/staff/businesses/stores
 * @var array $history        {rows, error}: rows from v1/staff/businesses/moderation, newest first
 * @var array $artifactConfig Table Builder configuration of the products and services
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ClientBusinesses as CB;

$this->extend('_layout');
helper(['list', 'auth', 'table']);
$b        = $business;
$currency = (string) ($b['currency_code'] ?? '');
$none     = '<span class="text-muted">&mdash;</span>';
$text     = static fn (mixed $v): string => null === $v || '' === (string) $v ? $none : esc((string) $v);
$long     = static fn (mixed $v): string => null === $v || '' === (string) $v ? $none : nl2br(esc((string) $v));
$enum     = static fn (array $map, mixed $v): string => null === $v || '' === (string) $v ? $none : esc($map[(string) $v] ?? (string) $v);
$amount   = static fn (mixed $v, mixed $unit): string => 'P' === $unit ? esc(rtrim(rtrim((string) $v, '0'), '.') . '%') : esc(B::money($currency, $v));
$notice   = static function (mixed $unit, mixed $value, mixed $time) use ($none): string {
    if (null === $value) {
        return $none;
    }
    return 'D' === $unit
        ? esc((int) $value . ' day(s) before, by ' . substr((string) $time, 0, 5))
        : esc((int) $value . ' minute(s) before the start');
};
$countries    = listSupportedCountries();
$country      = (string) ($b['country_code'] ?? '');
$subdivisions = listSupportedSubdivisions($country);
$languages    = listLanguages();
$audience     = [
    'lgbt_audience'     => ['FNDLY' => 'LGBT-friendly', 'LGBT' => 'LGBT only', 'GAY' => 'Gay only', 'LES' => 'Lesbian only', 'GEN' => 'General public'],
    'naturist_audience' => ['OPT' => 'Clothing-optional', 'NUD' => 'Nudity required in some areas', 'NAT' => 'Naturist only', 'GEN' => 'General public'],
    'age_restricted'    => ['Y' => 'Adults only', 'N' => 'General public'],
];
$periods  = ['W' => 'Weekly', 'S' => 'Semi-monthly', 'M' => 'Monthly'];
$datePart = ['N' => 'None', 'Y' => 'Year', 'M' => 'Year and month', 'D' => 'Year, month and day'];
$reset    = ['N' => 'Never', 'Y' => 'Yearly', 'M' => 'Monthly', 'D' => 'Daily'];

$address = array_filter([
    $b['contact_address_line_1'] ?? null,
    $b['contact_address_line_2'] ?? null,
    trim(($b['contact_city'] ?? '') . ' ' . ($b['contact_postal_code'] ?? '')),
    $subdivisions[$b['contact_subdivision_code'] ?? ''] ?? ($b['contact_subdivision_code'] ?? null),
], static fn ($line): bool => null !== $line && '' !== trim((string) $line));

// the subscription page is for the Executive Office and Finance only; see App\Filters\DepartmentFilter
$subscriptionStatus = $b['subscription_status'] ?? CB::NO_SUBSCRIPTION;
$subscription       = B::badge(CB::subscriptionStatuses(), $subscriptionStatus)
    . (empty($b['plan_code']) ? '' : ' <span class="text-secondary">· ' . esc((string) $b['plan_code']) . '</span>');
if (! empty($b['subscription_public_id']) && inDepartment(PLAN_DEPARTMENTS)) {
    $subscription .= ' <a class="ms-2" href="' . esc(base_url('admin/clients/subscriptions/view/' . $b['subscription_public_id']), 'attr') . '">View subscription <i class="bi bi-arrow-right"></i></a>';
}
$website = empty($b['contact_website']) ? $none
    : '<a href="' . esc((string) $b['contact_website'], 'attr') . '" target="_blank" rel="noopener noreferrer">' . esc((string) $b['contact_website']) . '</a>';
$email = empty($b['contact_email_address']) ? $none
    : '<a href="mailto:' . esc((string) $b['contact_email_address'], 'attr') . '">' . esc((string) $b['contact_email_address']) . '</a>';
$ratingCount = (int) ($b['rating_count'] ?? 0);

$sections = [
    'Business' => [
        'Business Name'   => $text($b['business_name'] ?? null),
        'Business URL Name' => '<code>' . esc((string) ($b['business_slug'] ?? '')) . '</code>',
        'Business Type'   => $text($b['type_code'] ?? null),
        'Description'     => $long($b['business_description'] ?? null),
        'Mood and Tone'   => $long($b['business_mood_and_tone'] ?? null),
        'LGBT Audience'   => $enum($audience['lgbt_audience'], $b['lgbt_audience'] ?? null),
        'Naturist Audience' => $enum($audience['naturist_audience'], $b['naturist_audience'] ?? null),
        'Age Restriction' => $enum($audience['age_restricted'], $b['age_restricted'] ?? null),
    ],
    'Status' => [
        'Marketplace'  => B::badge(CB::OPEN_STATUSES, $b['is_business_open'] ?? null),
        'Subscription' => $subscription,
        'Withholding'  => B::badge(B::WHT_STATUSES, $b['wht_status'] ?? null)
            . (empty($b['wht_rule_code']) ? '' : ' <span class="text-secondary">· ' . esc((string) $b['wht_rule_code']) . '</span>'),
        'Withholding Reviewed' => empty($b['wht_reviewed_at']) ? $none
            : B::time($b['wht_reviewed_at']) . (empty($b['wht_reviewed_by_name']) ? '' : ' <span class="text-secondary">by ' . esc((string) $b['wht_reviewed_by_name']) . '</span>'),
        'Rating'       => 0 === $ratingCount ? '<span class="text-secondary">No ratings yet</span>'
            : esc(number_format((float) ($b['rating_average'] ?? 0), 2) . ' / 5 from ' . $ratingCount . ' rating(s)'),
    ],
    'Contact' => [
        'Contact Person' => $text($b['contact_person_name'] ?? null),
        'Email Address'  => $email,
        'Phone Number'   => $text($b['contact_phone_number'] ?? null),
        'Website'        => $website,
        'Address'        => [] === $address ? $none : implode('<br>', array_map(static fn ($line): string => esc((string) $line), $address)),
    ],
    'Country & Tax' => [
        'Country'         => '<span class="fi fi-' . esc(strtolower($country), 'attr') . ' me-1"></span>' . esc($countries[$country] ?? $country),
        'Currency'        => esc($currency . ' - ' . mapCurrencyCode($currency)),
        'Legal Name'      => $text($b['legal_name'] ?? null),
        'Tax ID'          => inDepartment(PLAN_DEPARTMENTS) ? $text($b['tax_id'] ?? null)
            : '<span class="text-secondary">Shown to the Executive Office and Finance only</span>',
        'Tax Branch Code' => $text($b['tax_branch_code'] ?? null),
    ],
    'Tips & Charges' => [
        'Tipping'        => $enum(['D' => 'Disabled', 'OFF' => 'Off-system', 'O' => 'Optional at checkout', 'M' => 'Required at checkout'], $b['tip_policy'] ?? null),
        'Tip'            => 'D' === ($b['tip_policy'] ?? 'D') ? $none
            : $amount($b['tip_value'] ?? null, $b['tip_unit'] ?? null) . ' <span class="text-secondary">· ' . esc('M' === ($b['tip_value_kind'] ?? '') ? 'minimum' : 'suggested') . '</span>',
        'Service Charge' => $amount($b['service_charge_value'] ?? null, $b['service_charge_unit'] ?? null) . ' <span class="text-secondary">· on services</span>',
        'Commission'     => 'A' === ($b['commission_status'] ?? 'I')
            ? esc(($periods[$b['commission_period'] ?? ''] ?? '') . ', above ' . B::money($currency, $b['commission_threshold'] ?? null))
            : '<span class="text-secondary">Inactive</span>',
    ],
    'Booking & Cancellation' => [
        'Book Up To'         => esc((int) ($b['booking_horizon_days'] ?? 0) . ' day(s) ahead'),
        'Booking Notice'     => $notice($b['booking_notice_unit'] ?? null, $b['booking_notice_value'] ?? null, $b['booking_notice_time'] ?? null),
        'Free Cancellation'  => $notice($b['cancel_notice_unit'] ?? null, $b['cancel_notice_value'] ?? null, $b['cancel_notice_time'] ?? null),
        'Late Cancellation'  => 'Y' === ($b['late_cancel_allowed'] ?? 'Y')
            ? 'Allowed, with a fee of ' . $amount($b['late_cancel_fee_value'] ?? null, $b['late_cancel_fee_unit'] ?? null)
            : 'Not allowed online: contact the business',
        'No-Show Fee'        => $amount($b['no_show_fee_value'] ?? null, $b['no_show_fee_unit'] ?? null),
    ],
    'Order Numbers' => [
        'Prefix'      => '' === (string) ($b['order_prefix'] ?? '') ? $none : '<code>' . esc((string) $b['order_prefix']) . '</code>',
        'Date Part'   => $enum($datePart, $b['order_date_part'] ?? null),
        'Starts Again' => $enum($reset, $b['order_reset'] ?? null),
        'Digits'      => $text($b['order_digits'] ?? null),
    ],
    'Record' => [
        'Created' => B::time($b['created_at'] ?? null),
        'Updated' => B::time($b['updated_at'] ?? null),
    ],
];
$translations = (array) ($b['translations'] ?? []);
if ([] !== $translations) {
    $names = [];
    foreach ($translations as $language => $name) {
        $names[$languages[$language] ?? $language] = esc((string) $name);
    }
    $sections['Business'] = array_slice($sections['Business'], 0, 1, true) + ['Other Names' => implode('<br>', array_map(
        static fn (string $language, string $name): string => $name . ' <span class="text-secondary">· ' . esc($language) . '</span>', array_keys($names), $names))]
        + array_slice($sections['Business'], 1, null, true);
}
?>
<?php
$staffRows   = (array) ($staff['rows'] ?? []);
$historyRows = (array) ($history['rows'] ?? []);
$slugOf      = (string) ($b['business_slug'] ?? '');
$reviewOf    = (string) ($b['review_status'] ?? 'U');
$moderatedAs = (string) ($b['moderation_status'] ?? 'N');
// what the business was last told about itself: its last flag, block or removal
$notice = null;
foreach ($historyRows as $event) {
    if ('B' === ($event['subject_type'] ?? '') && in_array($event['event_action'] ?? '', ['FL', 'BL', 'RM'], true)) {
        $notice = $event;
        break;
    }
}
$artifactUrl = static fn (string $publicId): string => base_url('admin/clients/businesses/view/' . rawurlencode($slugOf) . '/artifacts/' . rawurlencode($publicId));
$storeRows = (array) ($stores['rows'] ?? []);
$days      = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
$names     = static fn (array $list): string => [] === $list ? '<span class="text-muted">&mdash;</span>' : implode('<br>', array_map(static fn ($n): string => esc((string) $n), $list));
$loadError = static fn (?string $error): string => null === $error ? ''
    : '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' . esc($error) . '</div>';
$tabs = [
    'overview' => ['Overview', 'bi-info-circle', null],
    'staff'    => ['Staff', 'bi-people', count($staffRows)],
    'stores'   => ['Stores', 'bi-shop', count($storeRows)],
    'products' => ['Products & Services', 'bi-box-seam', null],
    'history'  => ['Moderation History', 'bi-clock-history', count($historyRows)],
];
?>
<?= $this->section('content') ?>
    <section class="section">
        <ul class="nav nav-tabs mb-4" id="businessTabs" role="tablist">
            <?php foreach ($tabs as $id => [$label, $icon, $count]): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= 'overview' === $id ? ' active' : '' ?>" id="tab-<?= $id ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= $id ?>"
                            data-tab="<?= $id ?>" type="button" role="tab" aria-controls="pane-<?= $id ?>" aria-selected="<?= 'overview' === $id ? 'true' : 'false' ?>">
                        <i class="bi <?= $icon ?> me-1"></i><?= esc($label) ?>
                        <?php if (null !== $count): ?><span class="badge bg-light text-dark border ms-1"><?= $count ?></span><?php endif ?>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel" aria-labelledby="tab-overview">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card<?= 'N' === $moderatedAs ? '' : ' border-danger' ?>">
                            <div class="card-header">
                                <h5 class="card-title">Review &amp; Moderation</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-2 text-sm-end text-secondary">Review</div>
                                    <div class="col-sm-10"><?= B::badge(CB::REVIEW_STATUSES, $reviewOf) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-2 text-sm-end text-secondary">Moderation</div>
                                    <div class="col-sm-10"><?= B::badge(CB::MODERATION_STATUSES, $moderatedAs) ?></div>
                                </div>
                                <?php if (null !== $notice): ?>
                                    <div class="row mb-3">
                                        <div class="col-sm-2 text-sm-end text-secondary">Told the Business</div>
                                        <div class="col-sm-10">
                                            <?= B::badge(CB::MODERATION_ACTIONS, $notice['event_action']) ?>
                                            <span class="text-secondary ms-1"><?= esc(CB::MODERATION_REASONS[$notice['reason_code'] ?? ''] ?? (string) ($notice['reason_code'] ?? '')) ?> · <?= B::time($notice['created_at'] ?? null) ?></span>
                                            <div class="mt-1"><?= $long($notice['client_message'] ?? null) ?></div>
                                        </div>
                                    </div>
                                <?php endif ?>
                                <div class="row">
                                    <div class="col-sm-10 offset-sm-2">
                                        <?= CB::moderationButtons('business', '', 'this business', $reviewOf, $moderatedAs) ?>
                                        <a class="btn btn-link px-0" href="#history" data-show-tab="history">See the history <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($sections as $title => $rows): ?>
                        <div class="col-12 col-xl-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title"><?= esc($title) ?></h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($rows as $label => $html): ?>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-sm-end text-secondary"><?= esc($label) ?></div>
                                            <div class="col-sm-8"><?= $html ?></div>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-staff" role="tabpanel" aria-labelledby="tab-staff">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Staff</h5>
                        <p class="text-secondary mb-0">Everyone ever linked to the business, with the stores they work in now and the services they give</p>
                    </div>
                    <div class="card-body">
                        <?= $loadError($staff['error'] ?? null) ?>
                        <?php if (null === ($staff['error'] ?? null) && [] === $staffRows): ?>
                            <p class="text-secondary mb-0">No one is linked to this business.</p>
                        <?php elseif ([] !== $staffRows): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr>
                                        <th>Name</th><th>Role</th><th>Status</th><th>Account</th><th>Started</th><th>Left</th><th>Stores</th><th>Services</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($staffRows as $person): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= esc((string) ($person['full_name'] ?? '')) ?></div>
                                                <?php if (! empty($person['email_address'])): ?>
                                                    <a class="small" href="mailto:<?= esc((string) $person['email_address'], 'attr') ?>"><?= esc((string) $person['email_address']) ?></a>
                                                <?php endif ?>
                                            </td>
                                            <td><?= B::badge(CB::STAFF_ROLES, $person['client_role'] ?? null) ?></td>
                                            <td><?= B::badge(CB::STAFF_STATUSES, $person['client_status'] ?? null) ?></td>
                                            <td><?= B::badge(CB::ACCOUNT_STATUSES, $person['account_status'] ?? null) ?></td>
                                            <td><?= $text($person['start_date'] ?? null) ?></td>
                                            <td><?= $text($person['termination_date'] ?? null) ?></td>
                                            <td><?= $names((array) ($person['stores'] ?? [])) ?></td>
                                            <td><?= $names((array) ($person['services'] ?? [])) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-stores" role="tabpanel" aria-labelledby="tab-stores">
                <?= $loadError($stores['error'] ?? null) ?>
                <?php if (null === ($stores['error'] ?? null) && [] === $storeRows): ?>
                    <div class="card"><div class="card-body"><p class="text-secondary mb-0">The business has no stores.</p></div></div>
                <?php endif ?>
                <div class="row g-4">
                    <?php foreach ($storeRows as $store):
                        $storeAddress = array_filter([
                            $store['store_address_line_1'] ?? null,
                            $store['store_address_line_2'] ?? null,
                            trim(($store['store_city'] ?? '') . ' ' . ($store['store_postal_code'] ?? '')),
                            $subdivisions[$store['store_subdivision_code'] ?? ''] ?? ($store['store_subdivision_code'] ?? null),
                        ], static fn ($line): bool => null !== $line && '' !== trim((string) $line));
                        $hours = [];
                        foreach ((array) ($store['weekly_hours'] ?? []) as $slot) {
                            $hours[(int) $slot['day_of_week']][] = esc($slot['open_time'] . '–' . $slot['close_time']);
                        }
                        ?>
                        <div class="col-12 col-xl-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <?= esc((string) ($store['store_name'] ?? '')) ?>
                                        <span class="badge <?= 'O' === ($store['store_type'] ?? '') ? 'bg-info text-dark' : 'bg-light text-dark border' ?> ms-1"><?= 'O' === ($store['store_type'] ?? '') ? 'Online' : 'Physical' ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-sm-4 text-sm-end text-secondary">Timezone</div>
                                        <div class="col-sm-8"><?= $text($store['store_timezone'] ?? null) ?></div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-sm-4 text-sm-end text-secondary">Phone Number</div>
                                        <div class="col-sm-8"><?= $text($store['store_phone_number'] ?? null) ?></div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-sm-4 text-sm-end text-secondary">Address</div>
                                        <div class="col-sm-8"><?= [] === $storeAddress ? $none : implode('<br>', array_map(static fn ($line): string => esc((string) $line), $storeAddress)) ?></div>
                                    </div>
                                    <?php if (! empty($store['google_maps_url'])): ?>
                                        <div class="row mb-3">
                                            <div class="col-sm-4 text-sm-end text-secondary">Map</div>
                                            <div class="col-sm-8"><a href="<?= esc((string) $store['google_maps_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer">Google Maps <i class="bi bi-box-arrow-up-right"></i></a></div>
                                        </div>
                                    <?php endif ?>
                                    <div class="row mb-3">
                                        <div class="col-sm-4 text-sm-end text-secondary">Weekly Hours</div>
                                        <div class="col-sm-8">
                                            <?php if ([] === $hours): ?>
                                                <?= $none ?>
                                            <?php else: ?>
                                                <?php foreach ($days as $day => $dayName): ?>
                                                    <div><span class="text-secondary d-inline-block" style="min-width: 7rem"><?= $dayName ?></span><?= isset($hours[$day]) ? implode(', ', $hours[$day]) : '<span class="text-muted">Closed</span>' ?></div>
                                                <?php endforeach ?>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-products" role="tabpanel" aria-labelledby="tab-products">
                <?= lb_render($artifactConfig) ?>
            </div>

            <div class="tab-pane fade" id="pane-history" role="tabpanel" aria-labelledby="tab-history">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Moderation History</h5>
                        <p class="text-secondary mb-0">Every review and moderation of the business and its items, and every change the business made to what was flagged, newest first</p>
                    </div>
                    <div class="card-body">
                        <?= $loadError($history['error'] ?? null) ?>
                        <?php if (null === ($history['error'] ?? null) && [] === $historyRows): ?>
                            <p class="text-secondary mb-0">Nothing has been reviewed yet.</p>
                        <?php elseif ([] !== $historyRows): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr><th>When</th><th>What</th><th>Action</th><th>Reason</th><th>Message to the Business</th><th>Internal Note</th><th>By</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($historyRows as $event):
                                        $what = match ($event['subject_type'] ?? '') {
                                            'A'     => '<a href="' . esc($artifactUrl((string) $event['artifact_public_id']), 'attr') . '">' . esc((string) ($event['artifact_name'] ?? '')) . '</a>',
                                            'V'     => '<a href="' . esc($artifactUrl((string) $event['artifact_public_id']), 'attr') . '">' . esc((string) ($event['artifact_name'] ?? '')) . '</a>'
                                                . ' <span class="text-secondary">· variant ' . esc((string) ($event['variant_sku'] ?? $event['variant_public_id'] ?? '')) . '</span>',
                                            default => '<span class="text-secondary">The business</span>',
                                        };
                                        ?>
                                        <tr>
                                            <td class="text-nowrap"><?= B::time($event['created_at'] ?? null) ?></td>
                                            <td><?= $what ?></td>
                                            <td><?= B::badge(CB::MODERATION_ACTIONS, $event['event_action'] ?? null) ?></td>
                                            <td><?= empty($event['reason_code']) ? $none : esc(CB::MODERATION_REASONS[$event['reason_code']] ?? (string) $event['reason_code']) ?></td>
                                            <td><?= $long($event['client_message'] ?? null) ?></td>
                                            <td><?= $long($event['internal_note'] ?? null) ?></td>
                                            <td>
                                                <?= $text($event['created_by_name'] ?? null) ?>
                                                <?php if ('C' === ($event['created_by_type'] ?? null)): ?><span class="badge bg-light text-dark border ms-1">Business</span><?php endif ?>
                                            </td>
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

<?= view('admin_moderation_modal', ['businessSlug' => $slugOf]) ?>
<?= lb_script($artifactConfig) ?>
<script>
    // the tab in the URL's hash (#staff, #stores, #products) opens first, and each tab opened is put there, so a reload
    // or the artifact page's breadcrumb comes back to it; the table is sized when its tab first shows
    document.addEventListener('DOMContentLoaded', function () { // Bootstrap and DataTables load after the content
        const buttons = document.querySelectorAll('#businessTabs [data-tab]');
        buttons.forEach(function (button) {
            button.addEventListener('shown.bs.tab', function () {
                history.replaceState(null, '', '#' + button.dataset.tab);
                if ('products' === button.dataset.tab && window.DataTable) {
                    DataTable.tables({visible: true, api: true}).columns.adjust();
                }
            });
        });
        const show = function (tab) {
            const button = document.querySelector('#businessTabs [data-tab="' + tab + '"]');
            if (button) bootstrap.Tab.getOrCreateInstance(button).show();
        };
        document.querySelectorAll('[data-show-tab]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                show(link.dataset.showTab);
            });
        });
        show(location.hash.slice(1));
    });
</script>
<?= $this->endSection() ?>
