<?php

namespace App\Controllers;

use App\Libraries\LedgerReportCsv;
use App\Libraries\ZipWriter;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The financial reports of a ledger entity and the closing of its books, behind the auth and department:EXE,FIN
 * filters, from LINK_INTERNAL_API v1/staff/ledger/report/*, periods and period/* (see App\Libraries\LedgerReports
 * and LedgerPeriods there). Every report is in the entity's base currency; the dates are the entity's own days,
 * today in its timezone by default. Each report can be printed (to PDF from the browser) or downloaded as CSV;
 * a closed month's reports come from what was kept when it closed, so they match what was filed.
 */
class LedgerReports extends BaseController
{
    /** URL segment => [title, the dates the report takes] */
    public const REPORTS = [
        'profit-loss'    => ['Profit and Loss', ['date_from', 'date_to']],
        'balance-sheet'  => ['Balance Sheet', ['date_to']],
        'trial-balance'  => ['Trial Balance', ['date_to']],
        'general-ledger' => ['General Ledger', ['date_from', 'date_to']],
        'vat'              => ['VAT', ['date_from', 'date_to']],
        'wht'              => ['Withholding Tax', ['date_from', 'date_to']],
        'revenue-register' => ['Revenue Register', ['date_from', 'date_to']],
        'expense-register' => ['Expense Register', ['date_from', 'date_to']],
    ];

    /** Reports over a month by default, as the tax returns are */
    private const MONTHLY = ['general-ledger', 'vat', 'wht', 'revenue-register', 'expense-register'];

    /** The files of the tax pack: file name => [report, the report's dates: period or as at its end] */
    private const TAX_PACK = [
        'profit-loss'      => ['profit-loss', 'period'],
        'balance-sheet'    => ['balance-sheet', 'end'],
        'trial-balance'    => ['trial-balance', 'end'],
        'general-ledger'   => ['general-ledger', 'period'],
        'vat'              => ['vat', 'period'],
        'wht'              => ['wht', 'period'],
        'revenue-register' => ['revenue-register', 'period'],
        'expense-register' => ['expense-register', 'period'],
    ];

    /** A snapshot's report_type => [the report it is, title] */
    public const SNAPSHOTS = [
        'PL' => ['profit-loss', 'Profit and Loss (month)'],
        'PY' => ['profit-loss', 'Profit and Loss (fiscal year to date)'],
        'BS' => ['balance-sheet', 'Balance Sheet'],
        'TB' => ['trial-balance', 'Trial Balance'],
        'GL' => ['general-ledger', 'General Ledger (month)'],
        'VT' => ['vat', 'VAT (month)'],
        'WT' => ['wht', 'Withholding Tax (month)'],
        'RR' => ['revenue-register', 'Revenue Register (month)'],
        'ER' => ['expense-register', 'Expense Register (month)'],
    ];

    /**
     * @return RedirectResponse
     */
    public function index(): RedirectResponse
    {
        return redirect()->to(base_url('admin/ledger/reports/profit-loss'));
    }

    /**
     * One report. GET entity (public_id; the first entity by name when none), date_from and date_to (Y-m-d): by
     * default the fiscal year so far for the profit and loss, the month so far for the general ledger, today for
     * the others
     * @param string $kind A key of REPORTS
     * @return string
     */
    public function report(string $kind): string
    {
        return view('admin_ledger_report', $this->load($kind));
    }

    /**
     * The same report as CSV
     * @param string $kind A key of REPORTS
     * @return ResponseInterface
     */
    public function csv(string $kind): ResponseInterface
    {
        $data = $this->load($kind);
        if ($data['report'] === null) {
            throw PageNotFoundException::forPageNotFound($data['error'] ?? 'There is no entity yet.');
        }
        $dates = $data['dates'];

        return $this->download(
            LedgerReportCsv::build($kind, $data['report']),
            self::fileName($data['entity']['entity_name'], $kind, $dates['date_from'] ?? null, $dates['date_to'])
        );
    }

    /**
     * The entity's months, closed or open, with the next one to close and the reports kept by each close
     * @return string
     */
    public function periods(): string
    {
        $data = [
            'page_title'  => 'Closing the Books',
            'slug'        => 'ledger-periods',
            'breadcrumbs' => [base_url('admin/ledger/reports') => 'Financial Reports'],
            'entities'    => [],
            'entity'      => null,
            'overview'    => null,
            'snapshots'   => array_map(static fn (array $snapshot): string => $snapshot[1], self::SNAPSHOTS),
            'canReopen'   => inDepartment(['EXE']),
            'notice'      => session()->getFlashdata('ledger_period_notice'),
            'posting'     => null,
            'error'       => null,
        ];
        [$entities, $error] = $this->entities();
        $data['entities']   = array_column($entities, 'entity_name', 'public_id');
        if ($error !== null || [] === $entities) {
            $data['error'] = $error;
            return view('admin_ledger_periods', $data);
        }
        $data['entity'] = $entities[(string) $this->request->getGet('entity')] ?? reset($entities);

        [$code, $body] = $this->callApi('v1/staff/ledger/periods', ['entity_public_id' => $data['entity']['public_id']]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            $data['error'] = $body['message'] ?? 'Could not load the months. Please try again.';
        } else {
            $data['overview'] = (array) $body['data'];
        }
        [$code, $body]   = $this->callApi('v1/staff/ledger/posting/status', []);
        $data['posting'] = 200 === $code ? (array) ($body['data'] ?? []) : null;

        return view('admin_ledger_periods', $data);
    }

    /**
     * Close the next month: POST entity, period_start; back to the periods page with what happened
     * @return RedirectResponse
     */
    public function close(): RedirectResponse
    {
        $entity        = (string) $this->request->getPost('entity');
        [$code, $body] = $this->callApi('v1/staff/ledger/period/close', ['entity_public_id' => $entity, 'period_start' => (string) $this->request->getPost('period_start')]);

        return $this->backToPeriods($entity, $code, $body);
    }

    /**
     * Reopen the last closed month: POST entity, reason; the API lets the Executive Office only
     * @return RedirectResponse
     */
    public function reopen(): RedirectResponse
    {
        $entity        = (string) $this->request->getPost('entity');
        [$code, $body] = $this->callApi('v1/staff/ledger/period/reopen', ['entity_public_id' => $entity, 'reason' => (string) $this->request->getPost('reason')]);

        return $this->backToPeriods($entity, $code, $body);
    }

    /**
     * Post the subscription billing to the ledger now, rather than at the next billing:run: POST entity
     * @return RedirectResponse
     */
    public function syncPosting(): RedirectResponse
    {
        [$code, $body] = $this->callApi('v1/staff/ledger/posting/sync', []);

        return $this->backToPeriods((string) $this->request->getPost('entity'), $code, $body);
    }

    /**
     * A report kept when a month closed, to view and print
     * @param string $periodPublicId
     * @param string $type A key of SNAPSHOTS
     * @return string
     */
    public function snapshot(string $periodPublicId, string $type): string
    {
        $snapshot = $this->loadSnapshot($periodPublicId, $type);
        [$kind, $title] = self::SNAPSHOTS[$type];

        return view('admin_ledger_report', [
            'page_title'  => $title . ' · ' . date('F Y', strtotime($snapshot['period']['period_start'])),
            'slug'        => 'ledger-periods',
            'breadcrumbs' => [base_url('admin/ledger/periods?entity=' . rawurlencode($snapshot['period']['entity_public_id'])) => 'Closing the Books'],
            'kind'        => $kind,
            'title'       => $title,
            'reports'     => [],
            'entities'    => [],
            'entity'      => $snapshot['report']['entity'],
            'dates'       => [],
            'report'      => $snapshot['report'],
            'snapshot'    => $snapshot,
            'csvUrl'      => base_url('admin/ledger/periods/' . rawurlencode($periodPublicId) . '/' . $type . '/csv'),
            'packUrl'     => base_url('admin/ledger/periods/' . rawurlencode($periodPublicId) . '/tax-pack'),
            'threshold'   => null,
            'error'       => null,
        ]);
    }

    /**
     * A report kept when a month closed, as CSV
     * @param string $periodPublicId
     * @param string $type A key of SNAPSHOTS
     * @return ResponseInterface
     */
    public function snapshotCsv(string $periodPublicId, string $type): ResponseInterface
    {
        $snapshot = $this->loadSnapshot($periodPublicId, $type);
        $kind     = self::SNAPSHOTS[$type][0];

        return $this->download(
            LedgerReportCsv::build($kind, $snapshot['report']),
            self::fileName($snapshot['report']['entity']['entity_name'], ($type === 'PY' ? 'profit-loss-ytd' : $kind), $snapshot['date_from'], $snapshot['date_to'])
        );
    }

    /**
     * The reports of a period for the accountant and the tax returns, as a ZIP of CSV files: GET entity, date_from,
     * date_to. The statements are as at date_to; everything else covers the period.
     * @return ResponseInterface
     */
    public function taxPack(): ResponseInterface
    {
        [$entities, $error] = $this->entities();
        if ($error !== null || [] === $entities) {
            throw PageNotFoundException::forPageNotFound($error ?? 'There is no entity yet.');
        }
        $entity = $entities[(string) $this->request->getGet('entity')] ?? reset($entities);
        $dates  = self::dates(['date_from', 'date_to'], $entity, (array) $this->request->getGet(), 'vat');

        $reports = [];
        foreach (self::TAX_PACK as $file => [$kind, $span]) {
            $payload = ['entity_public_id' => $entity['public_id'], 'date_to' => $dates['date_to']] + ('period' === $span ? ['date_from' => $dates['date_from']] : []);
            [$code, $body] = $this->callApi('v1/staff/ledger/report/' . $kind, $payload);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                $errors = array_values((array) ($body['errors'] ?? []));
                throw new \RuntimeException($errors[0] ?? ($body['message'] ?? 'Could not run the ' . $kind . ' report.'));
            }
            $reports[$file] = [$kind, (array) $body['data']['report']];
        }

        $readme = $this->readme($entity['entity_name'], $entity['tax_id'] ?? null, $entity['base_currency_code'], $dates['date_from'], $dates['date_to'],
            'Run on the entries as they were at the time above. A closed month\'s pack, from Closing the Books, has the reports as they were when it closed.', []);

        return $this->zip($reports, $readme, self::fileName($entity['entity_name'], 'tax-pack', $dates['date_from'], $dates['date_to'], 'zip'));
    }

    /**
     * The tax pack of a closed month, from the reports kept when it closed
     * @param string $periodPublicId
     * @return ResponseInterface
     */
    public function snapshotTaxPack(string $periodPublicId): ResponseInterface
    {
        $map     = ['PL' => 'profit-loss', 'PY' => 'profit-loss-year-to-date', 'BS' => 'balance-sheet', 'TB' => 'trial-balance', 'GL' => 'general-ledger',
            'VT' => 'vat', 'WT' => 'wht', 'RR' => 'revenue-register', 'ER' => 'expense-register'];
        $reports = [];
        $hashes  = [];
        $first   = null;
        foreach ($map as $type => $file) {
            $snapshot         = $this->loadSnapshot($periodPublicId, $type);
            $first          ??= $snapshot;
            $reports[$file]   = [self::SNAPSHOTS[$type][0], $snapshot['report']];
            $hashes[$file]    = $snapshot['sha256'];
        }
        $entity = $first['report']['entity'];
        $period = $first['period'];
        $readme = $this->readme($entity['entity_name'], $entity['tax_id'] ?? null, $entity['base_currency_code'], $period['period_start'], $period['period_end'],
            'The reports as they were when the month closed (' . $period['closed_at'] . ' UTC' . ($period['closed_by_name'] ? ', by ' . $period['closed_by_name'] : '') . ')'
            . ($period['period_status'] === 'C' ? '.' : '. The month was reopened later, so this is history.'), $hashes);

        return $this->zip($reports, $readme, self::fileName($entity['entity_name'], 'tax-pack', $period['period_start'], $period['period_end'], 'zip'));
    }

    /**
     * @param array<string, array{0: string, 1: array}> $reports file name => [kind, report]
     * @param string                                    $readme
     * @param string                                    $name
     * @return ResponseInterface
     */
    private function zip(array $reports, string $readme, string $name): ResponseInterface
    {
        $zip = (new ZipWriter())->add('README.txt', $readme);
        foreach ($reports as $file => [$kind, $report]) {
            $zip->add($file . '.csv', self::csvText(LedgerReportCsv::build($kind, $report)));
            if ('wht' === $kind) {
                $zip->add('wht-by-payee.csv', self::csvText(LedgerReportCsv::build('wht-payees', $report)));
            }
        }

        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType('application/zip', '')
            ->setBody($zip->build());
    }

    /**
     * What the tax pack is, for whoever opens it
     * @param string                $entity
     * @param string|null           $taxId
     * @param string                $currency
     * @param string                $from
     * @param string                $to
     * @param string                $source
     * @param array<string, string> $hashes file => sha256 of the kept report
     * @return string
     */
    private function readme(string $entity, ?string $taxId, string $currency, string $from, string $to, string $source, array $hashes): string
    {
        $lines = [
            $entity . ($taxId ? ' (tax ID ' . $taxId . ')' : ''),
            'Tax pack ' . $from . ' to ' . $to . '; amounts in ' . $currency,
            'Made ' . gmdate('Y-m-d H:i') . ' UTC by ' . (session()->get('user')['full_name'] ?? ''),
            $source,
            '',
            'The profit and loss, VAT, withholding tax, general ledger and registers cover the period; the balance sheet and trial balance are as at its last day.',
            'The CSV files are UTF-8, with decimal points and ISO dates (YYYY-MM-DD).',
        ];
        if ($hashes !== []) {
            $lines[] = '';
            $lines[] = 'SHA-256 of each report as kept:';
            foreach ($hashes as $file => $hash) {
                $lines[] = '  ' . $file . ': ' . $hash;
            }
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * The report's dates: those asked for when they are dates, otherwise today in the entity's timezone and, for
     * date_from, the first day of the fiscal year (the month for the general ledger) that today is in
     * @param list<string> $fields
     * @param array        $entity A row of v1/staff/ledger/entities
     * @param array        $get    The query string
     * @param string       $kind   A key of REPORTS
     * @return array<string, string>
     */
    public static function dates(array $fields, array $entity, array $get, string $kind = 'profit-loss'): array
    {
        try {
            $today = new DateTimeImmutable('now', new DateTimeZone((string) $entity['timezone']));
        } catch (Throwable) {
            $today = new DateTimeImmutable('now');
        }
        $startMonth = max(1, min(12, (int) ($entity['fiscal_year_start_month'] ?? 1)));
        $year       = (int) $today->format('Y') - ((int) $today->format('n') < $startMonth ? 1 : 0);
        $defaults   = [
            'date_from' => in_array($kind, self::MONTHLY, true) ? $today->format('Y-m-01') : sprintf('%04d-%02d-01', $year, $startMonth),
            'date_to'   => $today->format('Y-m-d'),
        ];

        $dates = [];
        foreach ($fields as $field) {
            $value = $get[$field] ?? null;
            $date  = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            $dates[$field] = $date !== false && $date->format('Y-m-d') === $value ? $value : $defaults[$field];
        }

        return $dates;
    }

    /**
     * The page data of a report, run through the API
     * @param string $kind A key of REPORTS
     * @return array
     */
    private function load(string $kind): array
    {
        [$title, $dateFields] = self::REPORTS[$kind];
        $data                 = [
            'page_title'  => $title,
            'slug'        => 'ledger-reports',
            'breadcrumbs' => [base_url('admin/ledger') => 'Ledger'],
            'kind'        => $kind,
            'title'       => $title,
            'reports'     => array_map(static fn (array $report): string => $report[0], self::REPORTS),
            'entities'    => [],
            'entity'      => null,
            'dates'       => [],
            'report'      => null,
            'snapshot'    => null,
            'csvUrl'      => null,
            'packUrl'     => null,
            'threshold'   => null,
            'error'       => null,
        ];

        [$entities, $error] = $this->entities();
        $data['entities']   = array_column($entities, 'entity_name', 'public_id');
        if ($error !== null || [] === $entities) {
            $data['error'] = $error;
            return $data;
        }
        $entity         = $entities[(string) $this->request->getGet('entity')] ?? reset($entities);
        $data['entity'] = $entity;
        $data['dates']  = self::dates($dateFields, $entity, (array) $this->request->getGet(), $kind);
        $data['csvUrl'] = base_url('admin/ledger/reports/' . $kind . '/csv') . '?' . http_build_query(['entity' => $entity['public_id']] + $data['dates']);
        if (isset($data['dates']['date_from'])) {
            $data['packUrl'] = base_url('admin/ledger/reports/tax-pack') . '?' . http_build_query(['entity' => $entity['public_id']] + $data['dates']);
        }

        [$code, $body] = $this->callApi('v1/staff/ledger/report/' . $kind, ['entity_public_id' => $entity['public_id']] + $data['dates']);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            $errors        = array_values((array) ($body['errors'] ?? []));
            $data['error'] = $errors[0] ?? ($body['message'] ?? 'Could not run the report. Please try again.');
            return $data;
        }
        $data['report'] = (array) ($body['data']['report'] ?? []);
        if ('vat' === $kind) {
            [$code, $body]     = $this->callApi('v1/staff/ledger/report/vat-threshold', ['entity_public_id' => $entity['public_id'], 'date_to' => $data['dates']['date_to']]);
            $data['threshold'] = 200 === $code ? ($body['data']['report'] ?? null) : null;
        }

        return $data;
    }

    /**
     * @return array{0: array<string, array>, 1: string|null} The entities by public_id, and the error when they could not be loaded
     */
    private function entities(): array
    {
        [$code, $body] = $this->callApi('v1/staff/ledger/entities', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], $body['message'] ?? 'Could not load the entities. Please try again.'];
        }

        return [array_column((array) ($body['data']['rows'] ?? []), null, 'public_id'), null];
    }

    /**
     * @param string $periodPublicId
     * @param string $type
     * @return array The API's snapshot
     */
    private function loadSnapshot(string $periodPublicId, string $type): array
    {
        [$code, $body] = $this->callApi('v1/staff/ledger/period/snapshot', ['period_public_id' => $periodPublicId, 'report_type' => $type]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw PageNotFoundException::forPageNotFound($body['message'] ?? 'That report was not kept.');
        }

        return (array) $body['data']['snapshot'];
    }

    /**
     * @param string $entity
     * @param int    $code
     * @param array  $body
     * @return RedirectResponse
     */
    private function backToPeriods(string $entity, int $code, array $body): RedirectResponse
    {
        $ok      = $code === 200 && ($body['status'] ?? '') === 'success';
        $details = array_values(array_merge(...array_map(static fn ($error) => (array) $error, array_values((array) ($body['errors'] ?? [])))));
        session()->setFlashdata('ledger_period_notice', [
            $ok ? 'success' : 'danger',
            $body['message'] ?? ($ok ? 'Done.' : 'Could not do that. Please try again.'),
            $ok ? [] : $details,
        ]);

        return redirect()->to(base_url('admin/ledger/periods') . '?' . http_build_query(['entity' => $entity]));
    }

    /**
     * A CSV download, with the byte order mark Excel needs to read UTF-8
     * @param list<list<string>> $rows
     * @param string             $name
     * @return ResponseInterface
     */
    private function download(array $rows, string $name): ResponseInterface
    {
        $csv = self::csvText($rows);

        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType('text/csv', 'UTF-8')
            ->setBody($csv);
    }

    /**
     * CSV text with the byte order mark Excel needs to read UTF-8
     * @param list<list<string>> $rows
     * @return string
     */
    private static function csvText(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * "otternova-co-ltd_profit-loss_2026-01-01_2026-03-31.csv"
     * @param string      $entity
     * @param string      $kind
     * @param string|null $from
     * @param string      $to
     * @param string      $extension
     * @return string
     */
    private static function fileName(string $entity, string $kind, ?string $from, string $to, string $extension = 'csv'): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($entity)), '-') ?: 'entity';

        return $slug . '_' . $kind . '_' . ($from === null ? '' : $from . '_') . $to . '.' . $extension;
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array  $payload
     * @return array
     */
    private function callApi(string $endpoint, array $payload): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                'json'        => $payload,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . session()->get('access_token'),
                ],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            if (! is_array($body) || $code >= 500) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => 'The service is unavailable. Please try again later.']];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => 'The service is unavailable. Please try again later.']];
        }
    }
}
