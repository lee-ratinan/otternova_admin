<?php
/**
 * One business's business_master details; see App\Controllers\ClientBusinesses::businessView()
 * @var array $business From LINK_INTERNAL_API v1/staff/businesses/business; tax_id is null for staff outside the
 *                      Executive Office and Finance
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ClientBusinesses as CB;

$this->extend('_layout');
helper(['list', 'auth']);
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
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
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
    </section>
<?= $this->endSection() ?>
