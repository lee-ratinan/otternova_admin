<?php
/**
 * A staff member's pay, under their account form (admin_accounts_edit): the salary history with a form to add a
 * change or correct the latest one, and the bank account their pay goes to. The Executive Office only.
 *
 * Expects:
 *   $salary  App\Controllers\StaffSalaries::load(): ['data' => LINK_INTERNAL_API v1/staff/salary data, 'error' => ...]
 *   $uid     auth_master.uid of the staff member
 */
use App\Controllers\StaffSalaries;

$data       = $salary['data'];
$notice     = session()->getFlashdata('salary_notice');
$history    = $data['salaries'] ?? [];
$latest     = $history[0] ?? null;
$entities   = $data['entities'] ?? [];
$account    = $data['pay_account'] ?? [];
$currencies = listCurrencies();
ksort($currencies);
// after a failed save the form shows what was posted; which form it was is in 'salary_form'
$failedForm = old('salary_form');
$val        = static function (string $form, string $field, $default) use ($failedForm) {
    return $failedForm === $form ? (string) old($field) : (string) ($default ?? '');
};
$money      = static fn (array $row): string => esc($row['currency_code']) . ' ' . esc(number_format((float) $row['base_amount'], 2));
$correcting = $latest !== null && ! $latest['locked'];

/** The fields shared by the add and correct forms */
$salaryFields = static function (string $form, array $defaults) use ($val, $entities, $currencies, $failedForm): string {
    $id    = static fn (string $field): string => 'salary-' . $form . '-' . $field;
    $html  = '<div class="row g-3">';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('effective_date') . '">Starts on *</label>'
        . '<input type="date" class="form-control" id="' . $id('effective_date') . '" name="effective_date" required value="' . esc($val($form, 'effective_date', $defaults['effective_date'] ?? ''), 'attr') . '"></div>';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('end_date') . '">Ends on</label>'
        . '<input type="date" class="form-control" id="' . $id('end_date') . '" name="end_date" value="' . esc($val($form, 'end_date', $defaults['end_date'] ?? ''), 'attr') . '">'
        . '<div class="form-text">Leave empty while it applies; a new salary ends it for you.</div></div>';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('change_reason') . '">Reason *</label><select class="form-select" id="' . $id('change_reason') . '" name="change_reason" required>';
    $reason = $val($form, 'change_reason', $defaults['change_reason'] ?? '');
    foreach (StaffSalaries::CHANGE_REASONS as $code => $label) {
        $html .= '<option value="' . $code . '"' . ($reason === $code ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    $html .= '</select></div>';

    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('base_amount') . '">Amount *</label><div class="input-group">'
        . '<select class="form-select flex-grow-0 w-auto" name="currency_code" aria-label="Currency">';
    $currency = $val($form, 'currency_code', $defaults['currency_code'] ?? 'THB');
    foreach ($currencies as $code => $name) {
        $html .= '<option value="' . esc($code, 'attr') . '" title="' . esc($name, 'attr') . '"' . ($currency === $code ? ' selected' : '') . '>' . esc($code) . '</option>';
    }
    $html .= '</select><input type="number" class="form-control" id="' . $id('base_amount') . '" name="base_amount" min="0" step="0.01" required value="'
        . esc($val($form, 'base_amount', $defaults['base_amount'] ?? ''), 'attr') . '"></div></div>';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('rate_unit') . '">Rate *</label><select class="form-select" id="' . $id('rate_unit') . '" name="rate_unit">';
    $unit = $val($form, 'rate_unit', $defaults['rate_unit'] ?? 'M');
    foreach (StaffSalaries::RATE_UNITS as $code => $label) {
        $html .= '<option value="' . $code . '"' . ($unit === $code ? ' selected' : '') . '>' . esc(ucfirst($label)) . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('pay_frequency') . '">Paid *</label><select class="form-select" id="' . $id('pay_frequency') . '" name="pay_frequency">';
    $frequency = $val($form, 'pay_frequency', $defaults['pay_frequency'] ?? 'M');
    foreach (StaffSalaries::FREQUENCIES as $code => $label) {
        $html .= '<option value="' . $code . '"' . ($frequency === $code ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    $html .= '</select></div>';

    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('standard_hours_week') . '">Hours a week</label>'
        . '<input type="number" class="form-control" id="' . $id('standard_hours_week') . '" name="standard_hours_week" min="0.01" max="168" step="0.01" value="'
        . esc($val($form, 'standard_hours_week', $defaults['standard_hours_week'] ?? ''), 'attr') . '"><div class="form-text">For pro rata pay and overtime.</div></div>';
    $html .= '<div class="col-md-4"><label class="form-label" for="' . $id('entity') . '">Employing entity</label><select class="form-select" id="' . $id('entity') . '" name="entity">'
        . '<option value="">None yet (not registered)</option>';
    $entity = $val($form, 'entity', $defaults['entity']['public_id'] ?? '');
    foreach ($entities as $row) {
        $html .= '<option value="' . esc($row['public_id'], 'attr') . '"' . ($entity === $row['public_id'] ? ' selected' : '') . '>'
            . esc($row['entity_name'] . ' (' . $row['country_code'] . ')') . '</option>';
    }
    $html .= '</select></div>';
    $social = $form === $failedForm ? old('social_security') === '1' : (bool) ($defaults['social_security'] ?? false);
    $html .= '<div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2">'
        . '<input class="form-check-input" type="checkbox" id="' . $id('social_security') . '" name="social_security" value="1"' . ($social ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id('social_security') . '">Enrolled in social security</label></div></div>';
    $html .= '<div class="col-12"><label class="form-label" for="' . $id('note') . '">Note</label>'
        . '<input type="text" class="form-control" id="' . $id('note') . '" name="note" maxlength="255" value="' . esc($val($form, 'note', $defaults['note'] ?? ''), 'attr') . '"></div>';

    return $html . '</div>';
};
?>
    <section class="section mt-4" id="salary">
        <div class="row g-4">
            <div class="col-12">
                <?php if (is_array($notice)) : ?>
                <div class="alert alert-<?= esc($notice[0], 'attr') ?>" role="alert">
                    <?= esc($notice[1]) ?>
                    <?php if ($notice[2] !== []) : ?><ul class="mb-0"><?php foreach ($notice[2] as $detail) : ?><li><?= esc($detail) ?></li><?php endforeach; ?></ul><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h5 class="card-title mb-0">Salary</h5>
                            <div class="small text-secondary">Visible to the Executive Office only. Each change is kept as history.</div>
                        </div>
                        <?php if ($data !== null) : ?>
                        <div class="d-flex gap-2">
                            <?php if ($correcting) : ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#salary-edit" aria-expanded="<?= $failedForm === 'edit' ? 'true' : 'false' ?>" aria-controls="salary-edit"><i class="bi bi-pencil"></i> Correct latest</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#salary-add" aria-expanded="<?= $failedForm === 'add' ? 'true' : 'false' ?>" aria-controls="salary-add"><i class="bi bi-plus-lg"></i> <?= $latest === null ? 'Set salary' : 'Add a change' ?></button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($data === null) : ?>
                        <p class="text-danger mb-0"><?= esc($salary['error']) ?></p>
                        <?php else : ?>
                        <form method="post" action="<?= base_url('admin/accounts/salary/add') ?>" class="collapse<?= $failedForm === 'add' ? ' show' : '' ?> border rounded p-3 mb-4" id="salary-add">
                            <?= csrf_field() ?>
                            <input type="hidden" name="uid" value="<?= esc($uid, 'attr') ?>">
                            <input type="hidden" name="salary_form" value="add">
                            <h6 class="mb-3"><?= $latest === null ? 'Set the salary' : 'Add a salary change' ?></h6>
                            <?= $salaryFields('add', $latest === null
                                ? ['effective_date' => $data['staff']['employment_start_date'] ?? '', 'change_reason' => 'H']
                                : ['currency_code' => $latest['currency_code'], 'rate_unit' => $latest['rate_unit'], 'pay_frequency' => $latest['pay_frequency'],
                                    'standard_hours_week' => $latest['standard_hours_week'], 'entity' => $latest['entity'], 'social_security' => $latest['social_security'], 'change_reason' => 'A']) ?>
                            <?php if ($latest !== null && $latest['end_date'] === null) : ?>
                            <p class="small text-secondary mt-3 mb-0">The current salary will end the day before this one starts.</p>
                            <?php endif; ?>
                            <div class="mt-3"><button type="submit" class="btn btn-primary">Save</button></div>
                        </form>
                        <?php if ($correcting) : ?>
                        <form method="post" action="<?= base_url('admin/accounts/salary/edit') ?>" class="collapse<?= $failedForm === 'edit' ? ' show' : '' ?> border rounded p-3 mb-4" id="salary-edit">
                            <?= csrf_field() ?>
                            <input type="hidden" name="uid" value="<?= esc($uid, 'attr') ?>">
                            <input type="hidden" name="public_id" value="<?= esc($latest['public_id'], 'attr') ?>">
                            <input type="hidden" name="salary_form" value="edit">
                            <h6 class="mb-3">Correct the latest salary</h6>
                            <?= $salaryFields('edit', $latest) ?>
                            <div class="mt-3"><button type="submit" class="btn btn-primary">Save correction</button></div>
                        </form>
                        <?php endif; ?>

                        <?php if ($history === []) : ?>
                        <p class="text-secondary mb-0">No salary has been set yet.</p>
                        <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>From</th><th>To</th><th class="text-end">Amount</th><th>Paid</th><th>Reason</th><th>Entity</th><th>Note</th></tr></thead>
                                <tbody>
                                <?php foreach ($history as $i => $row) : ?>
                                    <tr>
                                        <td class="text-nowrap"><?= esc($row['effective_date']) ?><?= $i === 0 && $row['end_date'] === null ? ' <span class="badge bg-success">Current</span>' : '' ?></td>
                                        <td class="text-nowrap"><?= esc($row['end_date'] ?? '—') ?></td>
                                        <td class="text-end text-nowrap"><?= $money($row) ?> <span class="small text-secondary"><?= esc(StaffSalaries::RATE_UNITS[$row['rate_unit']] ?? '') ?></span></td>
                                        <td class="text-nowrap"><?= esc(StaffSalaries::FREQUENCIES[$row['pay_frequency']] ?? '') ?><?= $row['social_security'] ? ' <span class="badge text-bg-light border" title="Enrolled in social security">SSO</span>' : '' ?></td>
                                        <td><?= esc(StaffSalaries::CHANGE_REASONS[$row['change_reason']] ?? '') ?><?= $row['locked'] ? ' <i class="bi bi-lock text-secondary" title="A payslip uses this salary"></i>' : '' ?></td>
                                        <td><?= esc($row['entity']['entity_name'] ?? '—') ?></td>
                                        <td class="small"><?= esc($row['note'] ?? '') ?><?php if ($row['created_by'] !== null) : ?><div class="text-secondary">by <?= esc($row['created_by']) ?></div><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($data !== null) : ?>
            <div class="col-12">
                <form method="post" action="<?= base_url('admin/accounts/salary/pay-account') ?>" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="uid" value="<?= esc($uid, 'attr') ?>">
                    <input type="hidden" name="salary_form" value="pay-account">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bank Account</h5>
                        <div class="small text-secondary">Where the salary is paid. The account number is stored encrypted.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach (['bank_name' => ['Bank', 128], 'bank_branch' => ['Branch', 128], 'bank_account_name' => ['Account name', 255], 'bank_account_number' => ['Account number', 64]] as $field => [$label, $max]) : ?>
                            <div class="col-md-6">
                                <label class="form-label" for="pay-<?= $field ?>"><?= esc($label) ?></label>
                                <input type="text" class="form-control" id="pay-<?= $field ?>" name="<?= $field ?>" maxlength="<?= $max ?>" autocomplete="off"
                                       value="<?= esc($val('pay-account', $field, $account[$field] ?? ''), 'attr') ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3"><button type="submit" class="btn btn-primary">Save bank account</button></div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </section>
