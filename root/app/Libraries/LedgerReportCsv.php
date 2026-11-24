<?php

namespace App\Libraries;

/**
 * The rows of a financial report's CSV (see App\Controllers\LedgerReports): one table per report, with a header
 * row, plain decimals (a point, no thousands separators, minus for a negative) and ISO dates, the way an
 * accountant's spreadsheet or software imports them. The statements are rounded to 2 decimal places; the general
 * ledger keeps the amounts as entered.
 */
final class LedgerReportCsv
{
    /**
     * @param string $kind   A key of App\Controllers\LedgerReports::REPORTS
     * @param array  $report The API's report
     * @return list<list<string>>
     */
    public static function build(string $kind, array $report): array
    {
        $rows = match ($kind) {
            'profit-loss'    => self::profitLoss($report),
            'balance-sheet'  => self::balanceSheet($report),
            'trial-balance'  => self::trialBalance($report),
            'general-ledger' => self::generalLedger($report),
            'vat'              => self::taxLines($report, ['output' => 'Output VAT', 'input' => 'Input VAT'], ['Net VAT payable', $report['net_payable'] ?? '0']),
            'wht'              => self::taxLines($report, ['deducted' => 'Deducted from payees', 'suffered' => 'Deducted by customers'], null),
            'wht-payees'       => self::whtPayees($report),
            'revenue-register' => self::register($report),
            'expense-register' => self::register($report),
        };

        return array_map(static fn (array $row): array => array_map(self::cell(...), $row), $rows);
    }

    /**
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function profitLoss(array $report): array
    {
        $rows = [['Section', 'Account', 'Category Code', 'Category', 'Amount']];
        foreach (['revenue' => 'Revenue', 'expenses' => 'Expenses'] as $key => $section) {
            foreach ($report[$key]['accounts'] as $account) {
                foreach ($account['categories'] as $category) {
                    $rows[] = [$section, $account['account_name'], $category['category_code'], $category['category_name'], self::money($category['amount'])];
                }
            }
            $rows[] = [$section, 'Total ' . strtolower($section), '', '', self::money($report[$key]['total'])];
        }
        $rows[] = ['Result', (float) $report['net_profit'] < 0 ? 'Net loss' : 'Net profit', '', '', self::money($report['net_profit'])];

        return $rows;
    }

    /**
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function balanceSheet(array $report): array
    {
        $rows = [['Section', 'Line', 'Amount']];
        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $section) {
            foreach ($report[$key]['accounts'] as $account) {
                $rows[] = [$section, $account['account_name'], self::money($account['amount'])];
            }
            if ('equity' === $key) {
                $rows[] = [$section, 'Retained earnings', self::money($report['equity']['retained_earnings'])];
                $rows[] = [$section, 'Profit for the year from ' . $report['fiscal_year_start'], self::money($report['equity']['current_year_profit'])];
            }
            $rows[] = [$section, 'Total ' . strtolower($section), self::money($report[$key]['total'])];
        }
        $rows[] = ['Total', 'Total liabilities and equity', self::money($report['total_liabilities_equity'])];
        if ((float) $report['difference'] !== 0.0) {
            $rows[] = ['Total', 'Difference (accounts without a class)', self::money($report['difference'])];
        }

        return $rows;
    }

    /**
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function trialBalance(array $report): array
    {
        $classes = lang('OtternovaLedgerAccount.enum.account_class');
        $rows    = [['Account', 'Class', 'Debits', 'Credits', 'Balance Debit', 'Balance Credit']];
        foreach ($report['accounts'] as $account) {
            $rows[] = [$account['account_name'], $classes[$account['account_class']] ?? '',
                self::money($account['debit']), self::money($account['credit']), self::money($account['balance_debit']), self::money($account['balance_credit'])];
        }
        $totals = $report['totals'];
        $rows[] = ['Total', '', self::money($totals['debit']), self::money($totals['credit']), self::money($totals['balance_debit']), self::money($totals['balance_credit'])];

        return $rows;
    }

    /**
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function generalLedger(array $report): array
    {
        $base = $report['entity']['base_currency_code'];
        $rows = [['Date', 'Entry', 'Reference', 'Detail', 'Category Code', 'Category', 'From Account', 'To Account', 'Counterparty',
            'Paid Currency', 'Paid Amount', 'Billed Currency', 'Billed Amount', 'Exchange Rate', 'Amount (' . $base . ')', 'Receipt']];
        foreach ($report['entries'] as $entry) {
            $rows[] = [
                $entry['transaction_date'], $entry['public_id'], $entry['reference_number'], $entry['transaction_detail'],
                $entry['category_code'], $entry['category_name'], $entry['from_account_name'], $entry['to_account_name'], $entry['counterparty_name'],
                $entry['transaction_currency_code'], self::plain($entry['transaction_amount']), $entry['billed_currency_code'], self::plain($entry['billed_amount']),
                self::plain($entry['base_rate']), self::plain($entry['base_amount']), $entry['has_receipt'] ? 'Yes' : 'No',
            ];
        }
        $rows[] = ['Total', '', '', '', '', '', '', '', '', '', '', '', '', '', self::plain($report['total']), ''];

        return $rows;
    }

    /**
     * The lines of a VAT or withholding tax report, by section, with the sections' totals
     * @param array                       $report
     * @param array<string, string>       $sections key in the report => label
     * @param array{0: string, 1: string}|null $result The last row: label and amount
     * @return list<list<string|null>>
     */
    private static function taxLines(array $report, array $sections, ?array $result): array
    {
        $rows = [['Section', 'Date', 'Entry', 'Reference', 'Counterparty', 'Tax ID', 'Branch', 'Tax Code', 'Rate (%)', 'Taxable Amount', 'Tax', 'Receipt']];
        foreach ($sections as $key => $label) {
            foreach ($report[$key]['lines'] as $line) {
                $rows[] = [$label, $line['transaction_date'], $line['public_id'], $line['reference_number'], $line['counterparty_name'], $line['counterparty_tax_id'],
                    $line['counterparty_tax_branch'], $line['tax_code'], $line['rate'], self::money($line['tax_base']), self::money($line['tax_amount']), $line['has_receipt'] ? 'Yes' : 'No'];
            }
            $rows[] = [$label, 'Total', '', '', '', '', '', '', '', self::money($report[$key]['base']), self::money($report[$key]['tax']), ''];
        }
        if ($result !== null) {
            $rows[] = ['Result', $result[0], '', '', '', '', '', '', '', '', self::money($result[1]), ''];
        }

        return $rows;
    }

    /**
     * Withholding tax deducted, by payee: what the filing lists
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function whtPayees(array $report): array
    {
        $rows = [['Payee', 'Tax ID', 'Branch', 'Tax Code', 'Rate (%)', 'Amount Paid', 'Tax Withheld']];
        foreach ($report['deducted_by_payee'] as $payee) {
            $rows[] = [$payee['counterparty_name'], $payee['counterparty_tax_id'], $payee['counterparty_tax_branch'], $payee['tax_code'], $payee['rate'], self::money($payee['base']), self::money($payee['tax'])];
        }

        return $rows;
    }

    /**
     * A revenue or expense register
     * @param array $report
     * @return list<list<string|null>>
     */
    private static function register(array $report): array
    {
        $base = $report['entity']['base_currency_code'];
        $rows = [['Date', 'Entry', 'Transaction', 'Reference', 'Detail', 'Category Code', 'Category', 'Account', 'Counterparty', 'Tax ID', 'Branch',
            'Amount (' . $base . ')', 'VAT', 'Withholding Tax', 'Tax Codes', 'Receipt']];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['transaction_date'], $row['public_id'], $row['journal_id'], $row['reference_number'], $row['transaction_detail'], $row['category_code'],
                $row['category_name'], $row['account_name'], $row['counterparty_name'], $row['counterparty_tax_id'], $row['counterparty_tax_branch'],
                self::money($row['amount']), self::money($row['vat']), self::money($row['wht']), implode(' ', $row['tax_codes']), $row['has_receipt'] ? 'Yes' : 'No'];
        }
        $totals = $report['totals'];
        $rows[] = ['Total', '', '', '', '', '', '', '', '', '', '', self::money($totals['amount']), self::money($totals['vat']), self::money($totals['wht']), '', ''];

        return $rows;
    }

    /**
     * "-1234.5000" => "-1234.50"
     * @param string|int|float|null $amount
     * @return string
     */
    private static function money(string|int|float|null $amount): string
    {
        return number_format(round((float) $amount, 2), 2, '.', '');
    }

    /**
     * An amount or rate without the zeros the database pads it with, keeping 2 decimal places: "36.5" => "36.50"
     * @param string|int|float|null $amount
     * @return string
     */
    private static function plain(string|int|float|null $amount): string
    {
        if (null === $amount || '' === (string) $amount) {
            return '';
        }
        [$whole, $fraction] = explode('.', (string) $amount . '.');

        return $whole . '.' . str_pad(rtrim($fraction, '0'), 2, '0');
    }

    /**
     * A cell a spreadsheet will not run as a formula: text starting with = + - @ or a tab gets a leading '
     * (amounts are plain numbers, so a negative one stays as it is)
     * @param string|int|float|null $value
     * @return string
     */
    private static function cell(string|int|float|null $value): string
    {
        $value = (string) $value;
        if ('' !== $value && str_contains("=+-@\t\r", $value[0]) && ! is_numeric($value)) {
            return "'" . $value;
        }

        return $value;
    }
}
