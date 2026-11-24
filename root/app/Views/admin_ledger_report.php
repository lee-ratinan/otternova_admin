<?php
/**
 * A financial report of a ledger entity; see App\Controllers\LedgerReports and the API's App\Libraries\LedgerReports
 * @var string                $kind     profit-loss, balance-sheet, trial-balance or general-ledger
 * @var string                $title
 * @var array<string, string> $reports  kind => title; empty for a closed month's report
 * @var array|null            $snapshot The kept report of a closed month, null for one run now
 * @var string|null           $csvUrl
 * @var string|null           $packUrl   The tax pack (ZIP) of the same period, for the reports over a period
 * @var array|null            $threshold The VAT threshold report, on the VAT page
 * @var array<string, string> $entities public_id => name
 * @var array|null            $entity   The entity shown
 * @var array<string, string> $dates    date_from and/or date_to
 * @var array|null            $report   The API's report, null when it could not be run
 * @var string|null           $error
 */
// "-1234.5000" => "(1,234.50)", as accountants write a negative amount
$amount = static function (string|int|float|null $value): string {
    $number = round((float) $value, 2);
    $text   = number_format(abs($number), 2);
    return $number < 0 ? '(' . $text . ')' : $text;
};
$classes  = lang('OtternovaLedgerAccount.enum.account_class');
$query    = static fn (string $to, array $extra = []): string => base_url('admin/ledger/reports/' . $to) . '?' . http_build_query(array_filter(['entity' => $entity['public_id'] ?? null] + $extra));
$currency = $entity['base_currency_code'] ?? '';
$row      = static fn (string $label, string|int|float|null $value, string $class = '', int $indent = 0): string => '<tr class="' . $class . '"><td' . ($indent > 0 ? ' class="ps-' . (2 + 2 * $indent) . '"' : '') . '>' . esc($label) . '</td><td class="text-end text-nowrap">' . $amount($value) . '</td></tr>';
?>
<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <style>
        @media print {
            .ledger-report-controls, .sidebar, .app-sidebar, header, nav, footer, .breadcrumb { display: none !important; }
            .ledger-report-card { border: 0 !important; box-shadow: none !important; }
        }
        .ledger-report-table td, .ledger-report-table th { padding-top: .35rem; padding-bottom: .35rem; }
        .ledger-report-table .section-row td { font-weight: 600; padding-top: 1rem; border-bottom-width: 2px; }
        .ledger-report-table .total-row td { font-weight: 600; border-top: 1px solid var(--bs-border-color); }
        .ledger-report-table .grand-row td { font-weight: 700; border-top: 2px solid var(--bs-emphasis-color); border-bottom: 3px double var(--bs-emphasis-color); }
    </style>
    <div class="ledger-report-controls mb-3">
        <?php if ($snapshot !== null) : ?>
        <div class="alert alert-<?= $snapshot['period']['period_status'] === 'C' ? 'info' : 'secondary' ?> d-flex flex-wrap align-items-center gap-2" role="alert">
            <div class="me-auto">
                Kept when <?= esc(date('F Y', strtotime($snapshot['period']['period_start']))) ?> closed, <?= localTimeTag($snapshot['period']['closed_at']) ?>
                <?= $snapshot['period']['closed_by_name'] ? 'by ' . esc($snapshot['period']['closed_by_name']) : '' ?>.
                <?php if ($snapshot['period']['period_status'] !== 'C') : ?><strong>The month was reopened later</strong>, so this is history.<?php endif; ?>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?= esc($csvUrl, 'attr') ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= esc($packUrl, 'attr') ?>" title="Every report of the month, as kept, in one ZIP"><i class="bi bi-file-earmark-zip"></i> Tax Pack</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
        <?php else : ?>
        <ul class="nav nav-pills mb-3">
            <?php foreach ($reports as $key => $reportTitle) : ?>
            <li class="nav-item"><a class="nav-link<?= $key === $kind ? ' active' : '' ?>" href="<?= esc($query($key), 'attr') ?>"><?= esc($reportTitle) ?></a></li>
            <?php endforeach; ?>
            <li class="nav-item ms-auto"><a class="nav-link" href="<?= esc(base_url('admin/ledger/periods') . '?' . http_build_query(array_filter(['entity' => $entity['public_id'] ?? null])), 'attr') ?>"><i class="bi bi-lock"></i> Closing the Books</a></li>
        </ul>
        <?php endif; ?>
        <?php if ($entities !== [] && $snapshot === null) : ?>
        <form method="get" action="<?= base_url('admin/ledger/reports/' . $kind) ?>" class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label" for="report-entity">Entity</label>
                <select class="form-select" id="report-entity" name="entity">
                    <?php foreach ($entities as $publicId => $name) : ?>
                    <option value="<?= esc($publicId, 'attr') ?>"<?= $publicId === ($entity['public_id'] ?? '') ? ' selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php foreach ($dates as $field => $value) : ?>
            <div class="col-sm-3">
                <label class="form-label" for="report-<?= esc($field, 'attr') ?>"><?= $field === 'date_from' ? 'From' : (isset($dates['date_from']) ? 'To' : 'As at the end of') ?></label>
                <input class="form-control" type="date" id="report-<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
            </div>
            <?php endforeach; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Run</button>
                <?php if ($report !== null && $csvUrl !== null) : ?>
                <a class="btn btn-outline-secondary" href="<?= esc($csvUrl, 'attr') ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
                <?php endif; ?>
                <?php if ($packUrl !== null) : ?>
                <a class="btn btn-outline-secondary" href="<?= esc($packUrl, 'attr') ?>" title="Every report of this period in one ZIP, for the accountant and the tax returns"><i class="bi bi-file-earmark-zip"></i> Tax Pack</a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($error !== null) : ?>
    <div class="alert alert-warning" role="alert"><?= esc($error) ?></div>
    <?php elseif ($entities === [] && $snapshot === null) : ?>
    <div class="alert alert-info" role="alert">There is no entity yet. <a href="<?= base_url('admin/ledger/entities/create') ?>">Add the legal entity</a> whose books the ledger keeps, then put its accounts in it.</div>
    <?php elseif ($report !== null) : ?>
    <?php $warnings = $report['warnings'] ?? ['missing_base_amount' => 0, 'unclassified_accounts' => []]; ?>
    <?php if (($entity['closed_through'] ?? null) !== null && $snapshot === null && ($dates['date_to'] ?? '') !== '' && ($dates['date_from'] ?? $dates['date_to']) <= $entity['closed_through']) : ?>
    <div class="alert alert-light border ledger-report-controls small" role="status">
        The books are closed through <?= esc(formatDate($entity['closed_through'], LOCALE)) ?>. This report is run on today's entries; the reports as they were when each month closed are under <a href="<?= esc(base_url('admin/ledger/periods') . '?entity=' . rawurlencode($entity['public_id']), 'attr') ?>">Closing the Books</a>.
    </div>
    <?php endif; ?>
    <?php if ($warnings['missing_base_amount'] > 0 || $warnings['unclassified_accounts'] !== []) : ?>
    <div class="alert alert-warning ledger-report-controls" role="alert">
        <?php if ($warnings['missing_base_amount'] > 0) : ?>
        <div><?= esc(number_format($warnings['missing_base_amount'])) ?> entr<?= $warnings['missing_base_amount'] === 1 ? 'y has' : 'ies have' ?> no amount in <?= esc($currency) ?> and <?= $warnings['missing_base_amount'] === 1 ? 'is' : 'are' ?> left out: open <?= $warnings['missing_base_amount'] === 1 ? 'it' : 'them' ?> in the <a href="<?= base_url('admin/ledger') ?>">ledger</a> and save with an exchange rate.</div>
        <?php endif; ?>
        <?php if ($warnings['unclassified_accounts'] !== []) : ?>
        <div>These accounts have entries but no class, so they are on no statement:
            <?php foreach ($warnings['unclassified_accounts'] as $i => $account) : ?><?= $i > 0 ? ', ' : '' ?><a href="<?= base_url('admin/ledger/accounts/edit/' . rawurlencode($account['public_id'])) ?>"><?= esc($account['account_name']) ?></a><?php endforeach; ?>.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($threshold !== null) : ?>
    <?php
        $states = [
            'registered'  => ['success', 'Registered for VAT on ' . formatDate((string) $threshold['entity']['vat_registered_on'], LOCALE)],
            'none'        => ['secondary', 'No VAT registration threshold is set for this entity'],
            'exceeded'    => ['danger', 'Revenue has reached the VAT registration threshold: the entity must register for VAT'],
            'approaching' => ['warning', 'Revenue is close to the VAT registration threshold'],
            'below'       => ['light', 'Revenue is below the VAT registration threshold'],
        ];
        [$colour, $message] = $states[$threshold['status']];
    ?>
    <div class="alert alert-<?= esc($colour, 'attr') ?> border ledger-report-controls" role="status">
        <strong><?= esc($message) ?>.</strong>
        <?php if ($threshold['threshold'] !== null && $threshold['status'] !== 'registered') : ?>
        <div class="small mt-1">
            Fiscal year from <?= esc(formatDate($threshold['fiscal_year_start'], LOCALE)) ?>: <?= $amount($threshold['fiscal_year_revenue']) ?> ·
            last 12 months: <?= $amount($threshold['twelve_months_revenue']) ?> ·
            threshold: <?= $amount($threshold['threshold']) ?> <?= esc($currency) ?>
            (<?= esc(number_format(100 * (float) $threshold['share'], 1)) ?>%).
            Which of the two counts depends on the country's rules; please confirm with the accountant.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card ledger-report-card">
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="mb-1"><?= esc($report['entity']['entity_name']) ?></h4>
                <?php if (! empty($report['entity']['tax_id'])) : ?><div class="text-secondary small">Tax ID <?= esc($report['entity']['tax_id']) ?></div><?php endif; ?>
                <h5 class="mt-2 mb-1"><?= esc($title) ?></h5>
                <div class="text-secondary">
                    <?php if (isset($report['date_from'])) : ?>
                    <?= esc(formatDate($report['date_from'], LOCALE)) ?> – <?= esc(formatDate($report['date_to'], LOCALE)) ?>
                    <?php else : ?>
                    As at <?= esc(formatDate($report['date_to'], LOCALE)) ?>
                    <?php endif; ?>
                    · Amounts in <?= esc($currency) ?>
                </div>
            </div>

            <?php if ($kind === 'profit-loss') : ?>
            <table class="table table-borderless ledger-report-table mx-auto" style="max-width: 48rem">
                <tbody>
                <?php foreach (['revenue' => ['Revenue', 'Total revenue'], 'expenses' => ['Expenses', 'Total expenses']] as $key => [$heading, $totalLabel]) : ?>
                    <tr class="section-row"><td colspan="2"><?= esc($heading) ?></td></tr>
                    <?php if ($report[$key]['accounts'] === []) : ?>
                    <tr><td colspan="2" class="text-secondary ps-4">None in this period</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report[$key]['accounts'] as $account) : ?>
                    <?= $row($account['account_name'], $account['amount'], '', 1) ?>
                    <?php if (count($account['categories']) > 1) : ?>
                    <?php foreach ($account['categories'] as $category) : ?>
                    <?= $row($category['category_name'] . ' (' . $category['category_code'] . ')', $category['amount'], 'text-secondary small', 2) ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?= $row($totalLabel, $report[$key]['total'], 'total-row') ?>
                <?php endforeach; ?>
                    <?= $row((float) $report['net_profit'] < 0 ? 'Net loss' : 'Net profit', $report['net_profit'], 'grand-row') ?>
                </tbody>
            </table>

            <?php elseif ($kind === 'balance-sheet') : ?>
            <table class="table table-borderless ledger-report-table mx-auto" style="max-width: 48rem">
                <tbody>
                <?php foreach (['assets' => ['Assets', 'Total assets'], 'liabilities' => ['Liabilities', 'Total liabilities']] as $key => [$heading, $totalLabel]) : ?>
                    <tr class="section-row"><td colspan="2"><?= esc($heading) ?></td></tr>
                    <?php foreach ($report[$key]['accounts'] as $account) : ?>
                    <?= $row($account['account_name'], $account['amount'], '', 1) ?>
                    <?php endforeach; ?>
                    <?= $row($totalLabel, $report[$key]['total'], $key === 'assets' ? 'grand-row' : 'total-row') ?>
                <?php endforeach; ?>
                    <tr class="section-row"><td colspan="2">Equity</td></tr>
                    <?php foreach ($report['equity']['accounts'] as $account) : ?>
                    <?= $row($account['account_name'], $account['amount'], '', 1) ?>
                    <?php endforeach; ?>
                    <?= $row('Retained earnings', $report['equity']['retained_earnings'], '', 1) ?>
                    <?= $row('Profit for the year from ' . formatDate($report['fiscal_year_start'], LOCALE), $report['equity']['current_year_profit'], '', 1) ?>
                    <?= $row('Total equity', $report['equity']['total'], 'total-row') ?>
                    <?= $row('Total liabilities and equity', $report['total_liabilities_equity'], 'grand-row') ?>
                    <?php if ((float) $report['difference'] !== 0.0) : ?>
                    <?= $row('Difference (accounts without a class)', $report['difference'], 'text-danger') ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php elseif ($kind === 'vat' || $kind === 'wht') : ?>
            <?php $sections = $kind === 'vat' ? ['output' => 'Output VAT (sales)', 'input' => 'Input VAT (purchases)'] : ['deducted' => 'Deducted from payees', 'suffered' => 'Deducted by customers']; ?>
            <?php foreach ($sections as $key => $heading) : ?>
            <h6 class="mt-3"><?= esc($heading) ?></h6>
            <div class="table-responsive">
                <table class="table table-sm ledger-report-table small">
                    <thead><tr><th>Date</th><th>Reference</th><th>Counterparty</th><th>Tax ID</th><th>Code</th><th class="text-end">Taxable</th><th class="text-end">Tax</th></tr></thead>
                    <tbody>
                    <?php if ($report[$key]['lines'] === []) : ?>
                        <tr><td colspan="7" class="text-secondary">None in this period</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report[$key]['lines'] as $line) : ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= base_url('admin/ledger/entries/edit/' . rawurlencode($line['public_id'])) ?>"><?= esc($line['transaction_date']) ?></a></td>
                            <td><?= esc((string) $line['reference_number']) ?></td>
                            <td><?= esc((string) $line['counterparty_name']) ?></td>
                            <td class="text-nowrap"><?= esc((string) $line['counterparty_tax_id']) ?><?= $line['counterparty_tax_branch'] ? ' <span class="text-secondary">' . esc($line['counterparty_tax_branch']) . '</span>' : '' ?></td>
                            <td><?= esc($line['tax_code']) ?></td>
                            <td class="text-end text-nowrap"><?= $amount($line['tax_base']) ?></td>
                            <td class="text-end text-nowrap"><?= $amount($line['tax_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="total-row"><td colspan="5">Total</td><td class="text-end text-nowrap"><?= $amount($report[$key]['base']) ?></td><td class="text-end text-nowrap"><?= $amount($report[$key]['tax']) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            <?php if ($kind === 'vat') : ?>
            <table class="table table-borderless ledger-report-table mx-auto mt-3" style="max-width: 32rem">
                <tbody><?= $row((float) $report['net_payable'] < 0 ? 'VAT to claim back' : 'Net VAT payable', $report['net_payable'], 'grand-row') ?></tbody>
            </table>
            <?php elseif ($report['deducted_by_payee'] !== []) : ?>
            <h6 class="mt-3">Deducted, by payee</h6>
            <div class="table-responsive">
                <table class="table table-sm ledger-report-table small">
                    <thead><tr><th>Payee</th><th>Tax ID</th><th>Code</th><th class="text-end">Paid</th><th class="text-end">Withheld</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['deducted_by_payee'] as $payee) : ?>
                        <tr><td><?= esc((string) $payee['counterparty_name']) ?></td><td><?= esc((string) $payee['counterparty_tax_id']) ?></td><td><?= esc($payee['tax_code']) ?></td>
                            <td class="text-end text-nowrap"><?= $amount($payee['base']) ?></td><td class="text-end text-nowrap"><?= $amount($payee['tax']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($kind === 'revenue-register' || $kind === 'expense-register') : ?>
            <div class="table-responsive">
                <table class="table table-sm ledger-report-table small">
                    <thead><tr><th>Date</th><th>Detail</th><th>Account</th><th>Counterparty</th><th class="text-end"><?= esc($currency) ?></th><th class="text-end">VAT</th><th class="text-end">WHT</th><th>Receipt</th></tr></thead>
                    <tbody>
                    <?php if ($report['rows'] === []) : ?>
                        <tr><td colspan="8" class="text-secondary">None in this period</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report['rows'] as $line) : ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= base_url('admin/ledger/entries/edit/' . rawurlencode($line['public_id'])) ?>"><?= esc($line['transaction_date']) ?></a></td>
                            <td><?= esc($line['transaction_detail']) ?><?= $line['reference_number'] ? ' <span class="text-secondary">· ' . esc($line['reference_number']) . '</span>' : '' ?></td>
                            <td><?= esc($line['account_name']) ?></td>
                            <td><?= esc((string) $line['counterparty_name']) ?><?= $line['counterparty_tax_id'] ? ' <span class="text-secondary">' . esc($line['counterparty_tax_id']) . '</span>' : '' ?></td>
                            <td class="text-end text-nowrap"><?= $amount($line['amount']) ?></td>
                            <td class="text-end text-nowrap"><?= (float) $line['vat'] === 0.0 ? '' : $amount($line['vat']) ?></td>
                            <td class="text-end text-nowrap"><?= (float) $line['wht'] === 0.0 ? '' : $amount($line['wht']) ?></td>
                            <td><?= $line['has_receipt'] ? 'Yes' : '<span class="text-danger">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="grand-row"><td colspan="4">Total</td>
                            <?php foreach (['amount', 'vat', 'wht'] as $column) : ?><td class="text-end text-nowrap"><?= $amount($report['totals'][$column]) ?></td><?php endforeach; ?><td></td></tr>
                    </tbody>
                </table>
            </div>

            <?php elseif ($kind === 'general-ledger') : ?>
            <div class="table-responsive">
                <table class="table table-sm ledger-report-table small">
                    <thead>
                        <tr>
                            <th>Date</th><th>Detail</th><th>Category</th><th>From</th><th>To</th>
                            <th class="text-end">Paid</th><th class="text-end">Rate</th><th class="text-end"><?= esc($currency) ?></th><th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($report['entries'] === []) : ?>
                        <tr><td colspan="9" class="text-secondary">No entries in this period</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report['entries'] as $entry) : ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= base_url('admin/ledger/entries/edit/' . rawurlencode($entry['public_id'])) ?>"><?= esc($entry['transaction_date']) ?></a></td>
                            <td><?= esc($entry['transaction_detail']) ?><?php if ($entry['reference_number']) : ?> <span class="text-secondary">· <?= esc($entry['reference_number']) ?></span><?php endif; ?></td>
                            <td><?= esc($entry['category_code']) ?></td>
                            <td><?= esc($entry['from_account_name']) ?></td>
                            <td><?= esc($entry['to_account_name']) ?><?php if ($entry['counterparty_name']) : ?> <span class="text-secondary">· <?= esc($entry['counterparty_name']) ?></span><?php endif; ?></td>
                            <td class="text-end text-nowrap"><?= esc($entry['transaction_currency_code']) ?> <?= $amount($entry['transaction_amount']) ?></td>
                            <td class="text-end"><?= $entry['base_rate'] === null ? '' : esc(rtrim(rtrim((string) $entry['base_rate'], '0'), '.')) ?></td>
                            <td class="text-end text-nowrap"><?= $entry['base_amount'] === null ? '<span class="text-danger">None</span>' : $amount($entry['base_amount']) ?></td>
                            <td><?= $entry['has_receipt'] ? 'Yes' : '<span class="text-secondary">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="grand-row"><td colspan="7">Total</td><td class="text-end text-nowrap"><?= $amount($report['total']) ?></td><td></td></tr>
                    </tbody>
                </table>
            </div>

            <?php else : ?>
            <div class="table-responsive">
                <table class="table ledger-report-table">
                    <thead>
                        <tr>
                            <th>Account</th><th>Class</th>
                            <th class="text-end">Debits</th><th class="text-end">Credits</th>
                            <th class="text-end">Balance Dr</th><th class="text-end">Balance Cr</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($report['accounts'] === []) : ?>
                        <tr><td colspan="6" class="text-secondary">No entries up to this day</td></tr>
                    <?php endif; ?>
                    <?php foreach ($report['accounts'] as $account) : ?>
                        <tr>
                            <td><a href="<?= base_url('admin/ledger/accounts/edit/' . rawurlencode($account['public_id'])) ?>"><?= esc($account['account_name']) ?></a></td>
                            <td><?= esc($classes[$account['account_class']] ?? '—') ?></td>
                            <?php foreach (['debit', 'credit', 'balance_debit', 'balance_credit'] as $column) : ?>
                            <td class="text-end text-nowrap"><?= $amount($account[$column]) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="grand-row">
                            <td colspan="2">Total</td>
                            <?php foreach (['debit', 'credit', 'balance_debit', 'balance_credit'] as $column) : ?>
                            <td class="text-end text-nowrap"><?= $amount($report['totals'][$column]) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php if (! $report['balanced']) : ?>
            <div class="alert alert-danger mt-3" role="alert">The debits and credits do not agree. Please tell Engineering.</div>
            <?php endif; ?>
            <?php endif; ?>
            <div class="text-secondary small mt-4 pt-2 border-top">
                <?php if ($snapshot !== null) : ?>
                Kept on closing <?= esc(substr($snapshot['period']['period_start'], 0, 7)) ?> · SHA-256 <span class="font-monospace"><?= esc($snapshot['sha256']) ?></span>
                <?php else : ?>
                Run <?= esc(gmdate('Y-m-d H:i')) ?> UTC by <?= esc((string) (session()->get('user')['full_name'] ?? '')) ?> on the entries as they are now
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?= $this->endSection() ?>
