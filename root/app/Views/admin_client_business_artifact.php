<?php
/**
 * One product or service of a business, with its names in other languages, its variants (their attribute values and
 * stock per store) and its images; see App\Controllers\ClientBusinesses::artifactView()
 * @var array  $detail   From LINK_INTERNAL_API v1/staff/businesses/artifact: {artifact, variants, images}
 * @var string $currency     The business's currency_code, for the prices
 * @var string $businessSlug For the moderation dialog
 */

use App\Controllers\ClientBilling as B;
use App\Controllers\ClientBusinesses as CB;

$this->extend('_layout');
helper('list');
$a        = (array) ($detail['artifact'] ?? []);
$variants = (array) ($detail['variants'] ?? []);
$images   = (array) ($detail['images'] ?? []);
$service  = 'S' === ($a['artifact_type'] ?? '');
$none     = '<span class="text-muted">&mdash;</span>';
$text     = static fn (mixed $v): string => null === $v || '' === (string) $v ? $none : esc((string) $v);
$long     = static fn (mixed $v): string => null === $v || '' === (string) $v ? $none : nl2br(esc((string) $v));
$money    = static fn (mixed $v): string => null === $v || '' === (string) $v ? $none : esc(B::money($currency, $v));
$languages = listLanguages();
$labels    = ['N' => 'New', 'P' => 'Popular', 'R' => 'Recommended', 'L' => 'Limited'];
$taxes     = ['I' => 'Included in the price', 'E' => 'Added to the price', 'X' => 'No tax'];
$bookings  = ['A' => 'Appointment', 'G' => 'Group session'];
$variantOf = array_column($variants, 'sku', 'public_id');

$ratingCount = (int) ($a['rating_count'] ?? 0);
$tax         = $taxes[$a['tax_calculation'] ?? ''] ?? (string) ($a['tax_calculation'] ?? '');
if (null !== ($a['tax_percentage'] ?? null) && 'X' !== ($a['tax_calculation'] ?? '')) {
    $tax .= ' · ' . rtrim(rtrim((string) $a['tax_percentage'], '0'), '.') . '%';
}
$details = [
    'Name'        => $text($a['artifact_name'] ?? null),
    'Type'        => B::badge(CB::ARTIFACT_TYPES, $a['artifact_type'] ?? null),
    'Status'      => B::badge(CB::ARTIFACT_STATUSES, $a['artifact_status'] ?? null),
    'Label'       => empty($a['artifact_label']) ? $none : esc($labels[$a['artifact_label']] ?? (string) $a['artifact_label']),
    'Category'    => $text($a['category_name'] ?? null),
    'Description' => $long($a['artifact_description'] ?? null),
    'Price From'  => $money($a['price_active_lowest'] ?? null)
        . (null === ($a['price_compare_lowest'] ?? null) ? '' : ' <del class="text-secondary ms-1">' . esc(B::money($currency, $a['price_compare_lowest'])) . '</del>'),
    'Tax'         => esc($tax),
    'Rating'      => 0 === $ratingCount ? '<span class="text-secondary">No ratings yet</span>'
        : esc(number_format((float) ($a['rating_average'] ?? 0), 2) . ' / 5 from ' . $ratingCount . ' rating(s)'),
    'Created'     => B::time($a['created_at'] ?? null),
    'Updated'     => B::time($a['updated_at'] ?? null),
];
$translations = (array) ($a['translations'] ?? []);
$reviewOf     = (string) ($a['review_status'] ?? 'U');
$moderatedAs  = (string) ($a['moderation_status'] ?? 'N');
$notice       = (array) ($a['moderation_notice'] ?? []);
$what         = $service ? 'this service' : 'this product';
$reasons      = CB::MODERATION_REASONS;
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <div class="card<?= 'N' === $moderatedAs ? '' : ' border-danger' ?>">
                    <div class="card-header"><h5 class="card-title">Review &amp; Moderation</h5></div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-2 text-sm-end text-secondary">Review</div>
                            <div class="col-sm-10"><?= B::badge(CB::REVIEW_STATUSES, $reviewOf) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-2 text-sm-end text-secondary">Moderation</div>
                            <div class="col-sm-10"><?= B::badge(CB::MODERATION_STATUSES, $moderatedAs) ?></div>
                        </div>
                        <?php if ([] !== $notice): ?>
                            <div class="row mb-3">
                                <div class="col-sm-2 text-sm-end text-secondary">Told the Business</div>
                                <div class="col-sm-10">
                                    <?= B::badge(CB::MODERATION_ACTIONS, $notice['event_action'] ?? null) ?>
                                    <span class="text-secondary ms-1"><?= esc($reasons[$notice['reason_code'] ?? ''] ?? (string) ($notice['reason_code'] ?? '')) ?> · <?= B::time($notice['created_at'] ?? null) ?></span>
                                    <div class="mt-1"><?= $long($notice['client_message'] ?? null) ?></div>
                                </div>
                            </div>
                        <?php endif ?>
                        <div class="row">
                            <div class="col-sm-10 offset-sm-2"><?= CB::moderationButtons('artifact', (string) ($a['public_id'] ?? ''), $what, $reviewOf, $moderatedAs) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="card-title"><?= $service ? 'Service' : 'Product' ?></h5></div>
                    <div class="card-body">
                        <?php foreach ($details as $label => $html): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-secondary"><?= esc($label) ?></div>
                                <div class="col-sm-8"><?= $html ?></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="card-title">Other Languages</h5></div>
                    <div class="card-body">
                        <?php if ([] === $translations): ?>
                            <p class="text-secondary mb-0">No names in other languages.</p>
                        <?php endif ?>
                        <?php foreach ($translations as $language => $t): ?>
                            <div class="mb-3">
                                <div class="text-secondary small"><?= esc($languages[$language] ?? (string) $language) ?></div>
                                <div class="fw-semibold"><?= esc((string) ($t['artifact_name'] ?? '')) ?></div>
                                <?php if (! empty($t['artifact_description'])): ?>
                                    <div><?= nl2br(esc((string) $t['artifact_description'])) ?></div>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Variants <span class="badge bg-light text-dark border ms-1"><?= count($variants) ?></span></h5>
                    </div>
                    <div class="card-body">
                        <?php if ([] === $variants): ?>
                            <p class="text-secondary mb-0">No variants.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                    <tr>
                                        <th>SKU</th><th>Options</th><th class="text-end">Price</th><th class="text-end">Compare At</th><th>Status</th>
                                        <?php if ($service): ?>
                                            <th>Duration</th><th>Booking</th><th class="text-end">Staff</th><th class="text-end">Capacity</th>
                                        <?php else: ?>
                                            <th>Barcode</th><th class="text-end">Weight</th><th>Stock</th>
                                        <?php endif ?>
                                        <th>Moderation</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($variants as $v):
                                        $options = array_map(static fn (array $o): string => '<span class="text-secondary">' . esc((string) ($o['attribute_name'] ?? '')) . ':</span> '
                                            . esc((string) ($o['attribute_value'] ?? '')), (array) ($v['attribute_values'] ?? []));
                                        $stock   = array_map(static fn (array $s): string => esc((string) ($s['store_name'] ?? '')) . ' <span class="badge bg-light text-dark border">'
                                            . (int) ($s['quantity'] ?? 0) . '</span>', (array) ($v['inventory'] ?? []));
                                        ?>
                                        <tr>
                                            <td><?= empty($v['sku']) ? $none : '<code>' . esc((string) $v['sku']) . '</code>' ?></td>
                                            <td><?= [] === $options ? $none : implode('<br>', $options) ?></td>
                                            <td class="text-end"><?= $money($v['price_active'] ?? null) ?></td>
                                            <td class="text-end"><?= $money($v['price_compare'] ?? null) ?></td>
                                            <td><?= B::badge(CB::VARIANT_STATUSES, $v['variant_status'] ?? null) ?></td>
                                            <?php if ($service): ?>
                                                <td><?= null === ($v['duration_minutes'] ?? null) ? $none : esc((int) $v['duration_minutes'] . ' min') ?></td>
                                                <td><?= empty($v['booking_type']) ? $none : esc($bookings[$v['booking_type']] ?? (string) $v['booking_type']) ?></td>
                                                <td class="text-end"><?= $text($v['staff_required'] ?? null) ?></td>
                                                <td class="text-end"><?= $text($v['capacity'] ?? null) ?></td>
                                            <?php else: ?>
                                                <td><?= $text($v['barcode'] ?? null) ?></td>
                                                <td class="text-end"><?= null === ($v['weight_grams'] ?? null) ? $none : esc(number_format((int) $v['weight_grams']) . ' g') ?></td>
                                                <td><?= [] === $stock ? $none : implode('<br>', $stock) ?></td>
                                            <?php endif ?>
                                            <td>
                                                <div class="mb-1"><?= B::badge(CB::MODERATION_STATUSES, $v['moderation_status'] ?? 'N') ?></div>
                                                <?= CB::moderationButtons('variant', (string) ($v['public_id'] ?? ''), 'variant ' . ($v['sku'] ?? $v['public_id'] ?? ''), null, (string) ($v['moderation_status'] ?? 'N'), true) ?>
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

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Images <span class="badge bg-light text-dark border ms-1"><?= count($images) ?></span></h5>
                    </div>
                    <div class="card-body">
                        <?php if ([] === $images): ?>
                            <p class="text-secondary mb-0">No images.</p>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($images as $image): ?>
                                    <div class="col-6 col-md-4 col-xl-2">
                                        <a href="<?= esc((string) ($image['url'] ?? ''), 'attr') ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?= esc((string) ($image['url'] ?? ''), 'attr') ?>" class="img-fluid rounded border" alt="" loading="lazy">
                                        </a>
                                        <?php if (! empty($image['variant_public_id'])): ?>
                                            <div class="small text-secondary mt-1">Variant <?= esc((string) ($variantOf[$image['variant_public_id']] ?? $image['variant_public_id'])) ?></div>
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
<?= view('admin_moderation_modal', ['businessSlug' => $businessSlug]) ?>
<?= $this->endSection() ?>
