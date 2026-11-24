<?php

namespace App\Controllers;

use App\Models\OtternovaLedgerAccountModel;
use App\Models\OtternovaLedgerCategoryModel;
use App\Models\OtternovaLedgerCounterpartyModel;
use App\Models\OtternovaLedgerEntityModel;
use App\Models\OtternovaLedgerMasterModel;
use App\Models\OtternovaLedgerPostingRuleModel;
use App\Models\OtternovaLedgerTaxCodeModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;
use Throwable;

/**
 * The ledger pages, behind the auth and department:EXE,FIN filters: the entries with their PDF receipts, the
 * accounts, categories and counterparties they point at, and the legal entities whose books they are (the financial
 * reports are App\Controllers\LedgerReports). Everything is read and saved through LINK_INTERNAL_API
 * v1/staff/ledger/*, which checks the department again.
 */
class Ledger extends BaseController
{
    /**
     * The pages shared by entities, accounts, categories and counterparties, keyed by their URL segment, which is also
     * the API's list endpoint (v1/staff/ledger/{kind}); 'endpoint' is the API's save endpoint
     * (v1/staff/ledger/{endpoint}/update); 'references' are the model's columns the API takes by a public_id instead
     */
    private const RECORDS = [
        'entities'       => [
            'model'      => OtternovaLedgerEntityModel::class,
            'endpoint'   => 'entity',
            'title'      => 'Entities',
            'subtitle'   => 'The legal entities whose books the ledger keeps; the reports are per entity',
            'one'        => 'Entity',
            'name'       => 'entity_name',
            'filters'    => [],
            'references' => [],
        ],
        'accounts'       => [
            'model'    => OtternovaLedgerAccountModel::class,
            'endpoint' => 'account',
            'title'    => 'Ledger Accounts',
            'subtitle' => 'Where the money of an entry comes from and goes to',
            'one'      => 'Ledger Account',
            'name'     => 'account_name',
            'filters'  => ['account_type', 'account_class'],
            'references' => ['entity_id' => 'entity_public_id'],
        ],
        'tax-codes'      => [
            'model'      => OtternovaLedgerTaxCodeModel::class,
            'endpoint'   => 'tax-code',
            'title'      => 'Tax Codes',
            'subtitle'   => 'VAT and withholding tax, per entity; a new rate is a new code',
            'one'        => 'Tax Code',
            'name'       => 'tax_code',
            'filters'    => ['tax_kind'],
            'references' => ['entity_id' => 'entity_public_id', 'account_id' => 'account_public_id'],
        ],
        'posting-rules'  => [
            'model'      => OtternovaLedgerPostingRuleModel::class,
            'endpoint'   => 'posting-rule',
            'title'      => 'Posting Rules',
            'subtitle'   => 'Which entity books the subscription invoices of a country, and into which accounts',
            'one'        => 'Posting Rule',
            'name'       => 'country_code',
            'filters'    => [],
            'references' => [
                'entity_id' => 'entity_public_id', 'receivable_account_id' => 'receivable_account_public_id',
                'deferred_revenue_account_id' => 'deferred_revenue_account_public_id', 'revenue_account_id' => 'revenue_account_public_id',
                'cash_account_id' => 'cash_account_public_id', 'wht_receivable_account_id' => 'wht_receivable_account_public_id',
                'wht_tax_code_id' => 'wht_tax_code_public_id', 'category_id' => 'category_public_id',
            ],
        ],
        'categories'     => [
            'model'    => OtternovaLedgerCategoryModel::class,
            'endpoint' => 'category',
            'title'    => 'Categories',
            'subtitle' => 'What an entry is for, such as infrastructure, bills or salary',
            'one'      => 'Category',
            'name'     => 'category_name',
            'filters'  => [],
            'references' => [],
        ],
        'counterparties' => [
            'model'    => OtternovaLedgerCounterpartyModel::class,
            'endpoint' => 'counterparty',
            'title'    => 'Counterparties',
            'subtitle' => 'The other side of an entry with an external account',
            'one'      => 'Counterparty',
            'name'     => 'counterparty_name',
            'filters'  => [],
            'references' => [],
        ],
    ];

    /**
     * The entry's fields that point at other ledger records: the model's column => the field the API sends and
     * takes, by the record's public_id
     */
    private const ENTRY_REFERENCES = [
        'category_id'     => 'category_public_id',
        'from_account_id' => 'from_account_public_id',
        'to_account_id'   => 'to_account_public_id',
        'counterparty_id' => 'counterparty_public_id',
        'tax_code_id'     => 'tax_code_public_id',
    ];

    /** Largest receipt the API accepts (Config\LedgerReceipt::$maxFileSize there) */
    private const RECEIPT_MAX_SIZE = 10 * 1024 * 1024;

    // ---- entries ----

    /**
     * All entries, newest transaction first
     * @return string
     */
    public function entries(): string
    {
        $data = [
            'page_title' => 'Ledger',
            'slug'       => 'ledger-entries',
            'listConfig' => $this->entriesListConfig($this->lookups()),
        ];
        return view('generic_list', $data);
    }

    /**
     * One page of entries: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/ledger/entries,
     * which always orders by transaction date and checks the filter values itself
     * @return ResponseInterface
     */
    public function entriesList(): ResponseInterface
    {
        $config  = $this->entriesListConfig(null);
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }

        [$code, $body] = $this->callApi('v1/staff/ledger/entries', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // the Table Builder shows 'error' as a toast
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error' => $body['message'] ?? 'Could not load the ledger. Please try again.',
            ]);
        }

        $result = $body['data'] ?? [];
        $rows   = array_map(static function (array $row): array {
            $row['transaction_date'] = formatDate($row['transaction_date'], LOCALE);
            $row['paid']             = self::formatAmount($row['transaction_currency_code'], $row['transaction_amount']);
            $row['billed']           = self::formatAmount($row['billed_currency_code'], $row['billed_amount']);
            $row['base']             = self::formatAmount($row['base_currency_code'] ?? null, $row['base_amount'] ?? null);
            $row['counterparty_name'] = (string) ($row['counterparty_name'] ?? '');
            $row['reference_number'] = (string) ($row['reference_number'] ?? '');
            return $row;
        }, (array) ($result['rows'] ?? []));
        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($result['records_total'] ?? 0),
            (int) ($result['records_filtered'] ?? 0),
            lb_rows($rows, $config)
        ));
    }

    /**
     * Entry form: 'create' opens an empty entry, any other value is the public_id of the entry to view
     * (the Edit button switches the same page to edit mode). A saved entry also has its receipt upload form.
     * @param string $publicId
     * @return string
     */
    public function entriesEdit(string $publicId): string
    {
        $mode   = 'create' === $publicId ? 'create' : 'view';
        $values = [];
        $with   = null;
        if ('view' === $mode) {
            $values = $this->entry($publicId);
        } elseif ('' !== (string) $this->request->getGet('with')) {
            // a line of the same transaction, such as its VAT: it starts as a copy of the entry it goes with
            $with   = $this->entry((string) $this->request->getGet('with'));
            $values = array_intersect_key($with, array_flip([
                'transaction_date', 'transaction_detail', 'reference_number', 'category_public_id', 'transaction_currency_code',
                'billed_currency_code', 'from_account_public_id', 'counterparty_public_id',
            ])) + ['with_entry_public_id' => $with['public_id']];
        }

        $data = [
            'page_title'  => 'view' === $mode ? 'Ledger Entry ' . $values['public_id'] : (null === $with ? 'New Ledger Entry' : 'New Line of Entry ' . $with['public_id']),
            'slug'        => 'ledger-entries',
            'breadcrumbs' => [base_url('admin/ledger') => 'Ledger'],
            'formConfig'  => [
                'form_id'  => 'ledger-entry-form',
                'title'    => ['create' => 'New Entry', 'view' => 'Entry Detail'][$mode],
                'subtitle' => 'Fields marked with * are required',
                'mode'     => $mode,
                'id_field' => 'public_id',
                'id'       => $values['public_id'] ?? '',
                'method'   => 'POST',
                'locale'   => LOCALE,
                'urls'     => [
                    'submit' => base_url('admin/ledger/entries/save'),
                    'edit'   => base_url('admin/ledger/entries/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/ledger'),
                ],
                'values'   => $values,
                'fields'   => $this->entryFormFields($this->lookups(), $values),
            ],
        ];
        if ('view' === $mode) {
            $data['extraForms'] = [$this->receiptForm($values)];
        }
        return view('generic_form', $data);
    }

    /**
     * One entry from LINK_INTERNAL_API v1/staff/ledger/entry, its amounts as the form shows them
     * @param string $publicId
     * @return array
     */
    private function entry(string $publicId): array
    {
        [$code, $body] = $this->callApi('v1/staff/ledger/entry', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the ledger entry.');
        }
        $values = (array) ($body['data']['entry'] ?? []);
        $values['transaction_amount'] = self::trimAmount($values['transaction_amount'] ?? null);
        $values['billed_amount']      = self::trimAmount($values['billed_amount'] ?? null);
        $values['tax_base_amount']    = self::trimAmount($values['tax_base_amount'] ?? null);
        $values['base_rate']          = self::trimRate($values['base_rate'] ?? null);

        return $values;
    }

    /**
     * Save an entry: an empty public_id creates one, otherwise the entry with that public_id is updated.
     * Forwarded to LINK_INTERNAL_API v1/staff/ledger/entry/update, which validates it
     * @return ResponseInterface
     */
    public function entriesEditPost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $fields   = array_map(static fn (string $field): string => self::ENTRY_REFERENCES[$field] ?? $field, OtternovaLedgerMasterModel::FORM_FIELDS);

        $with     = '' === $publicId ? trim((string) ($post['with_entry_public_id'] ?? '')) : '';

        [$code, $body] = $this->callApi('v1/staff/ledger/entry/update', ['public_id' => '' === $publicId ? null : $publicId]
            + ('' === $with ? [] : ['with_entry_public_id' => $with])
            + array_intersect_key($post, array_flip($fields)));
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save the ledger entry. Please try again.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Saved.',
            'redirect'  => base_url('admin/ledger/entries/edit/' . rawurlencode((string) ($body['data']['public_id'] ?? $publicId))),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Upload an entry's receipt through LINK_INTERNAL_API v1/staff/ledger/receipt-upload: multipart { public_id,
     * receipt }, a PDF that replaces the one the entry had. The page reloads, so it shows the link to the new receipt.
     * @return ResponseInterface
     */
    public function receiptUpload(): ResponseInterface
    {
        $publicId = (string) $this->request->getPost('public_id');
        $file     = $this->request->getFile('receipt');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $message  = $tooLarge ? 'The file is too large. Please choose a smaller one.' : 'Please choose a PDF file.';
            return $this->response->setStatusCode($tooLarge ? 413 : 422)->setJSON([
                'success'   => false,
                'message'   => $message,
                'errors'    => ['receipt' => $message],
                'csrf_hash' => csrf_hash(),
            ]);
        }

        [$code, $body] = $this->callApi('v1/staff/ledger/receipt-upload', [
            'public_id' => $publicId,
            'receipt'   => new CURLFile($file->getTempName(), 'application/pdf', $file->getClientName()),
        ], true);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not upload the receipt. Please try again.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Receipt uploaded.',
            'redirect'  => base_url('admin/ledger/entries/edit/' . rawurlencode($publicId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Remove an entry's receipt through LINK_INTERNAL_API v1/staff/ledger/receipt-delete
     * @return ResponseInterface
     */
    public function receiptDelete(): ResponseInterface
    {
        $publicId = (string) $this->request->getPost('public_id');
        [$code, $body] = $this->callApi('v1/staff/ledger/receipt-delete', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not delete the receipt. Please try again.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Receipt deleted.',
            'redirect'  => base_url('admin/ledger/entries/edit/' . rawurlencode($publicId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * An entry's receipt, passed on from LINK_INTERNAL_API GET v1/staff/ledger/receipt/{public_id}: the browser cannot
     * send the JWT itself, so the PDF comes through here
     * @param string $publicId
     * @return ResponseInterface
     */
    public function receipt(string $publicId): ResponseInterface
    {
        try {
            $response = service('curlrequest')->get(getenv('LINK_INTERNAL_API') . 'v1/staff/ledger/receipt/' . rawurlencode($publicId), [
                'headers'     => ['Authorization' => 'Bearer ' . session()->get('access_token')],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API v1/staff/ledger/receipt failed: ' . $e->getMessage());
            throw new RuntimeException('Could not load the receipt.');
        }
        if ($response->getStatusCode() !== 200 || ! str_starts_with($response->getHeaderLine('Content-Type'), 'application/pdf')) {
            throw PageNotFoundException::forPageNotFound('This entry has no receipt.');
        }

        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'inline; filename="receipt-' . preg_replace('/[^0-9A-Za-z_-]/', '', $publicId) . '.pdf"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType('application/pdf', '')
            ->setBody($response->getBody());
    }

    // ---- accounts, categories, counterparties ----

    /**
     * The list of accounts, categories or counterparties
     * @param string $kind A key of RECORDS
     * @return string
     */
    public function records(string $kind): string
    {
        $data = [
            'page_title' => self::RECORDS[$kind]['title'],
            'slug'       => 'ledger-' . $kind,
            'listConfig' => $this->recordsListConfig($kind),
        ];
        return view('generic_list', $data);
    }

    /**
     * One page of accounts, categories or counterparties. The API sends them all (they are few), so the search,
     * filters, order and paging of the DataTables request are applied here.
     * @param string $kind A key of RECORDS
     * @return ResponseInterface
     */
    public function recordsList(string $kind): ResponseInterface
    {
        $config = $this->recordsListConfig($kind);
        $params = lb_request($this->request, $config);
        try {
            $all = $this->lookup($kind);
        } catch (RuntimeException $e) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $e->getMessage()]);
        }

        $columns = array_column($params['columns'], null, 'data');
        $rows    = array_filter($all, static function (array $row) use ($params, $columns): bool {
            foreach ($params['filters'] as $item) {
                if ((string) ($row[$item['def']['name']] ?? '') !== (string) $item['value']) {
                    return false;
                }
            }
            if ('' === $params['search']) {
                return true;
            }
            foreach ($columns as $key => $column) {
                // enum columns match their label, as that is what the table shows
                $text = (string) ($row[$key] ?? '');
                $text = $column['labels'][$text] ?? ($column['badges'][$text]['label'] ?? $text);
                if ($column['searchable'] && false !== mb_stripos($text, $params['search'])) {
                    return true;
                }
            }
            return false;
        });

        $fieldOf = array_column($params['columns'], 'data', 'db');
        usort($rows, static function (array $a, array $b) use ($params, $fieldOf): int {
            foreach ($params['order'] as [$db, $dir]) {
                $cmp = strnatcasecmp((string) ($a[$fieldOf[$db]] ?? ''), (string) ($b[$fieldOf[$db]] ?? ''));
                if (0 !== $cmp) {
                    return 'DESC' === $dir ? -$cmp : $cmp;
                }
            }
            return strcmp($a['public_id'], $b['public_id']);
        });

        return $this->response->setJSON(lb_response(
            $params['draw'],
            count($all),
            count($rows),
            lb_rows(array_slice($rows, $params['start'], $params['length']), $config)
        ));
    }

    /**
     * Account, category or counterparty form: 'create' opens an empty one, any other value is the public_id of
     * the one to view (the Edit button switches the same page to edit mode)
     * @param string $kind A key of RECORDS
     * @param string $publicId
     * @return string
     */
    public function recordsEdit(string $kind, string $publicId): string
    {
        $record = self::RECORDS[$kind];
        $mode   = 'create' === $publicId ? 'create' : 'view';
        $values = [];
        if ('view' === $mode) {
            $values = array_column($this->lookup($kind), null, 'public_id')[$publicId] ?? null;
            if (null === $values) {
                throw PageNotFoundException::forPageNotFound($record['one'] . ' not found.');
            }
        }

        $fields = (new $record['model']())->getConfiguration();
        if ('entities' === $kind) {
            $fields['entity_name']['help']        = 'The registered name, such as OtterNova Co., Ltd.';
            $fields['legal_form']['help']         = 'Such as Sole proprietorship or Co., Ltd.';
            $fields['country_code']               = ['type' => 'select', 'options' => listCountries()] + $fields['country_code'];
            $fields['timezone']                   = ['type' => 'select', 'options' => array_combine(timezone_identifiers_list(), timezone_identifiers_list())] + $fields['timezone'];
            $fields['timezone']['help']           = 'The days of the reports are this timezone\'s days';
            $fields['base_currency_code']['help'] = 'Every report is in it. It cannot change once the entity has entries';
            $fields['fiscal_year_start_month']['help'] = 'Can be changed later; the reports follow it when they are run';
            $fields['closed_on']['help']          = 'When another entity took over, if it did';
        }
        if ('accounts' === $kind) {
            $fields['currency_code'] += ['maxlength' => 3, 'help' => 'The ISO 4217 code, such as THB or USD'];
            $fields['account_class']['help'] = 'Where it goes on the financial statements: assets, liabilities and equity on the balance sheet, revenue and expenses on the profit and loss';
            $fields['entity_id'] = ['name' => 'entity_public_id', 'rules' => 'required', 'options' => $this->entityOptions(),
                'help' => 'Whose books the account is in; it stays there once it has entries'] + $fields['entity_id'];
        }
        if ('categories' === $kind) {
            $fields['category_code'] += ['maxlength' => 3, 'help' => '3 letters or digits, such as INF'];
        }
        if ('tax-codes' === $kind) {
            $accounts = [];
            foreach ($this->lookup('accounts') as $row) {
                $accounts[$row['public_id']] = $row['account_name'] . (null === ($row['entity_name'] ?? null) ? '' : ' (' . $row['entity_name'] . ')');
            }
            $fields['entity_id']  = ['name' => 'entity_public_id', 'rules' => 'required', 'options' => $this->entityOptions()] + $fields['entity_id'];
            $fields['account_id'] = ['name' => 'account_public_id', 'rules' => '', 'options' => $accounts,
                'help' => 'The account the tax goes into or out of, such as Output VAT payable; none for a 0% code, which goes on the sale or purchase itself'] + $fields['account_id'];
            $fields['tax_code'] += ['maxlength' => 16, 'help' => 'Such as VAT7 or WHT3'];
            $fields['tax_kind']['help'] = 'Output VAT on sales, input VAT on purchases; withholding tax deducted from a payee, or by a customer';
            $fields['rate']['help']     = 'Percent. It cannot change once the code is on an entry: end it the day before and add a new code';
        }
        if ('posting-rules' === $kind) {
            $accounts = [];
            foreach ($this->lookup('accounts') as $row) {
                $accounts[$row['public_id']] = $row['account_name'] . (null === ($row['entity_name'] ?? null) ? '' : ' (' . $row['entity_name'] . ')');
            }
            $codes = [];
            foreach ($this->lookup('tax-codes') as $row) {
                if ('WS' === $row['tax_kind']) {
                    $codes[$row['public_id']] = $row['tax_code'] . ' · ' . $row['tax_name'] . ' (' . $row['entity_name'] . ')';
                }
            }
            $help = [
                'receivable_account_id'       => 'Debited when an invoice is issued, credited when it is paid or voided',
                'deferred_revenue_account_id' => 'Credited when an invoice is issued; its revenue moves out month by month as the service is given',
                'revenue_account_id'          => 'Credited with each month\'s share of a paid period, by days',
                'cash_account_id'             => 'Debited with what the business paid',
                'wht_receivable_account_id'   => 'Debited with the tax the business withheld; must be the withholding tax code\'s account',
            ];
            foreach (OtternovaLedgerPostingRuleModel::ACCOUNT_FIELDS as $column) {
                $required        = 'wht_receivable_account_id' !== $column;
                $fields[$column] = ['name' => $record['references'][$column], 'rules' => $required ? 'required' : '', 'options' => $accounts, 'help' => $help[$column]] + $fields[$column];
            }
            $fields['entity_id']       = ['name' => 'entity_public_id', 'rules' => 'required', 'options' => $this->entityOptions()] + $fields['entity_id'];
            $fields['wht_tax_code_id'] = ['name' => 'wht_tax_code_public_id', 'rules' => '', 'options' => $codes, 'help' => 'A withholding tax suffered (WS) code of the entity'] + $fields['wht_tax_code_id'];
            $fields['category_id']     = ['name' => 'category_public_id', 'rules' => 'required', 'options' => $this->lookups()['categories']] + $fields['category_id'];
            $fields['country_code']    = ['type' => 'select', 'options' => listCountries(), 'help' => 'The country of the businesses whose invoices this rule posts'] + $fields['country_code'];
            $fields['effective_from']['help'] = 'Invoices made before this day are never posted: set it after the last month entered by hand, so nothing counts twice';
        }
        if ('counterparties' === $kind) {
            $fields['tax_id']['help']     = 'For the VAT and withholding tax reports';
            $fields['tax_branch']['help'] = 'The branch on their tax invoices, such as 00000 for the head office';
        }
        if ('entities' === $kind) {
            $fields['vat_threshold_amount']['help'] = 'Revenue in a year from which the entity must register for VAT, in the base currency (THB 1,800,000 in Thailand); empty for none';
            $fields['vat_registered_on']['help']    = 'Empty until the entity registers for VAT';
        }
        $data = [
            'page_title'  => 'view' === $mode ? $values[$record['name']] : 'New ' . $record['one'],
            'slug'        => 'ledger-' . $kind,
            'breadcrumbs' => [base_url('admin/ledger/' . $kind) => $record['title']],
            'formConfig'  => [
                'form_id'  => 'ledger-' . $kind . '-form',
                'title'    => ['create' => 'New ' . $record['one'], 'view' => $record['one'] . ' Detail'][$mode],
                'subtitle' => 'Fields marked with * are required',
                'mode'     => $mode,
                'id_field' => 'public_id',
                'id'       => $values['public_id'] ?? '',
                'method'   => 'POST',
                'locale'   => LOCALE,
                'urls'     => [
                    'submit' => base_url('admin/ledger/' . $kind . '/save'),
                    'edit'   => base_url('admin/ledger/' . $kind . '/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/ledger/' . $kind),
                ],
                'values'   => $values,
                'fields'   => array_values($fields),
            ],
        ];
        return view('generic_form', $data);
    }

    /**
     * Save an account, category or counterparty: an empty public_id creates one, otherwise the one with that
     * public_id is updated. Forwarded to LINK_INTERNAL_API v1/staff/ledger/{endpoint}/update, which validates it
     * @param string $kind A key of RECORDS
     * @return ResponseInterface
     */
    public function recordsEditPost(string $kind): ResponseInterface
    {
        $record   = self::RECORDS[$kind];
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));

        $fields   = array_map(static fn (string $field): string => $record['references'][$field] ?? $field, $record['model']::FORM_FIELDS);

        [$code, $body] = $this->callApi('v1/staff/ledger/' . $record['endpoint'] . '/update', ['public_id' => '' === $publicId ? null : $publicId]
            + array_intersect_key($post, array_flip($fields)));
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save the ' . strtolower($record['one']) . '. Please try again.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Saved.',
            'redirect'  => base_url('admin/ledger/' . $kind . '/edit/' . rawurlencode((string) ($body['data']['public_id'] ?? $publicId))),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---- configuration ----

    /**
     * Table Builder configuration of the entry list, shared by the page and its JSON endpoint
     * @param array|null $lookups From lookups(), for the filter options; null for the JSON endpoint, where the
     *                            API checks the filter values
     * @return array
     */
    private function entriesListConfig(?array $lookups): array
    {
        $labels  = static fn (string $name): string => lang('OtternovaLedgerMaster.fields.' . $name);
        // the API orders by transaction date, newest first, and searches the detail and reference number only
        $column  = static fn (string $data, string $title, bool $searchable = false, string $class = ''): array => [
            'data' => $data, 'title' => $title, 'orderable' => false, 'searchable' => $searchable, 'class' => $class,
        ];
        return [
            'table_id'    => 'ledgerEntryTable',
            'title'       => 'Ledger Entries',
            'subtitle'    => 'Newest transaction first; the search looks in the detail and reference number',
            'id_field'    => 'public_id',
            'urls'        => [
                'data'   => base_url('admin/ledger'),
                'create' => base_url('admin/ledger/entries/create'),
                'edit'   => base_url('admin/ledger/entries/edit/{public_id}'),
            ],
            'labels'      => ['add' => 'Add Entry'],
            'columns'     => [
                $column('transaction_date', 'Date'),
                $column('reference_number', $labels('reference_number'), true),
                $column('transaction_detail', $labels('transaction_detail'), true),
                $column('category_name', $labels('category_id')),
                $column('from_account_name', $labels('from_account_id')),
                $column('to_account_name', $labels('to_account_id')),
                $column('counterparty_name', $labels('counterparty_id')),
                $column('paid', 'Paid', false, 'text-end text-nowrap'),
                $column('billed', 'Billed', false, 'text-end text-nowrap'),
                $column('base', 'Base', false, 'text-end text-nowrap'),
                ['type' => 'boolean', 'labels' => ['true' => 'Yes', 'false' => 'No']] + $column('has_receipt', 'Receipt'),
            ],
            'filters'     => [
                ['name' => 'date_from', 'type' => 'date', 'label' => 'Date from'],
                ['name' => 'date_to', 'type' => 'date', 'label' => 'Date to'],
                ['name' => 'account_public_id', 'type' => 'select', 'label' => 'Account', 'options' => $lookups['accounts'] ?? []],
                ['name' => 'category_public_id', 'type' => 'select', 'label' => $labels('category_id'), 'options' => $lookups['categories'] ?? []],
                ['name' => 'counterparty_public_id', 'type' => 'select', 'label' => $labels('counterparty_id'), 'options' => $lookups['counterparties'] ?? []],
            ],
            'layout'      => ['filter_col' => 2],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of the account, category or counterparty list, shared by the page and its JSON endpoint
     * @param string $kind A key of RECORDS
     * @return array
     */
    private function recordsListConfig(string $kind): array
    {
        $record  = self::RECORDS[$kind];
        $model   = new $record['model']();
        $fields  = $model->getConfiguration();
        $filters = $model::toFilters($fields, $record['filters']);
        if ('accounts' === $kind) {
            // the API sends the entity by its public_id and name
            $fields['entity_id'] = ['name' => 'entity_name', 'db' => 'entity_name'] + $fields['entity_id'];
            array_unshift($filters, ['name' => 'entity_public_id', 'type' => 'select', 'label' => $fields['entity_id']['label'], 'column' => 'entity_public_id', 'options' => $this->entityOptions()]);
        }
        if ('entities' === $kind) {
            unset($fields['timezone'], $fields['tax_id'], $fields['vat_threshold_amount'], $fields['vat_registered_on']);
        }
        if ('posting-rules' === $kind) {
            $fields = [
                'entity_id' => ['name' => 'entity_name', 'db' => 'entity_name'] + $fields['entity_id'],
                'country_code' => $fields['country_code'], 'effective_from' => $fields['effective_from'], 'effective_to' => $fields['effective_to'],
                'receivable_account_id' => ['name' => 'receivable_account_name', 'db' => 'receivable_account_name'] + $fields['receivable_account_id'],
                'revenue_account_id' => ['name' => 'revenue_account_name', 'db' => 'revenue_account_name'] + $fields['revenue_account_id'],
                'cash_account_id' => ['name' => 'cash_account_name', 'db' => 'cash_account_name'] + $fields['cash_account_id'],
            ];
        }
        if ('tax-codes' === $kind) {
            $fields['entity_id']  = ['name' => 'entity_name', 'db' => 'entity_name'] + $fields['entity_id'];
            $fields['account_id'] = ['name' => 'account_name', 'db' => 'account_name'] + $fields['account_id'];
            array_unshift($filters, ['name' => 'entity_public_id', 'type' => 'select', 'label' => $fields['entity_id']['label'], 'column' => 'entity_public_id', 'options' => $this->entityOptions()]);
        }
        return [
            'table_id'    => 'ledger' . ucfirst($kind) . 'Table',
            'title'       => $record['title'],
            'subtitle'    => $record['subtitle'],
            'id_field'    => 'public_id',
            'urls'        => [
                'data'   => base_url('admin/ledger/' . $kind),
                'create' => base_url('admin/ledger/' . $kind . '/create'),
                'edit'   => base_url('admin/ledger/' . $kind . '/edit/{public_id}'),
            ],
            'labels'      => ['add' => 'Add ' . $record['one']],
            'columns'     => $model::toColumns($fields),
            'filters'     => $filters,
            'order'       => [[$record['name'], 'asc']],
            'page_length' => 25,
        ];
    }

    /**
     * Fields of the entry form, with the options of its dropdowns
     * @param array $lookups From lookups()
     * @param array $values  The entry, empty for a new one
     * @return array
     */
    private function entryFormFields(array $lookups, array $values): array
    {
        $fields = (new OtternovaLedgerMasterModel())->getConfiguration();
        $fields['category_id']['options']     = $lookups['categories'];
        $fields['from_account_id']['options'] = $lookups['accounts'];
        $fields['to_account_id']['options']   = $lookups['accounts'];
        $fields['counterparty_id']['options'] = $lookups['counterparties'];
        $fields['tax_code_id']['options']     = $lookups['tax_codes'];
        $fields['tax_code_id']['help']        = 'When this row is a tax, such as the VAT of a bill: one of its accounts is then the code\'s tax account. A 0% code goes on the sale or purchase itself';
        $fields['tax_base_amount']['help']    = 'What the tax was worked out on, in the entity\'s base currency, such as the bill before VAT';
        $fields['tax_base_amount']['min']     = 0;
        // these are sent and taken by the public_id of the record, so the model's id rules do not apply
        foreach (self::ENTRY_REFERENCES as $column => $name) {
            $fields[$column]['name']  = $name;
            $fields[$column]['rules'] = $fields[$column]['optional'] ? '' : 'required';
        }
        $fields['counterparty_id']['help']    = 'Required when either account is external';
        $fields['reference_number']['help']   = 'The number on the bill or receipt, if there is one';
        $fields['transaction_amount']['help'] = 'What was actually paid out of the From account';
        $fields['billed_amount']['help']      = 'What the bill asked for, which may differ in currency or amount';
        foreach (['transaction_currency_code', 'billed_currency_code'] as $name) {
            $fields[$name] += ['maxlength' => 3, 'help' => 'The ISO 4217 code, such as THB or USD'];
        }
        foreach (['transaction_amount', 'billed_amount'] as $name) {
            $fields[$name]['min'] = 0;
        }
        $fields['base_rate']['min']  = 0;
        $fields['base_rate']['help'] = 'How much of the entity\'s base currency one unit of the paid currency bought. Only used when neither currency is the base currency; leave it empty to use the day\'s rate (European Central Bank), or clear it to fetch it again after changing the date or currency';

        $heading = static fn (string $label): array => ['type' => 'heading', 'label' => $label];
        $out     = [
            $heading('Transaction'),
            $fields['transaction_date'], $fields['transaction_detail'], $fields['reference_number'], $fields['category_id'],
            $heading('From / To'),
            $fields['from_account_id'], $fields['to_account_id'], $fields['counterparty_id'],
            $heading('Amounts'),
            $fields['transaction_currency_code'], $fields['transaction_amount'], $fields['billed_currency_code'], $fields['billed_amount'],
            $fields['base_rate'],
            $heading('Tax'),
            $fields['tax_code_id'], $fields['tax_base_amount'],
        ];
        if ('' !== (string) ($values['with_entry_public_id'] ?? '')) {
            $out[] = ['name' => 'with_entry_public_id', 'type' => 'hidden', 'show_in' => ['create'], 'rules' => '', 'optional' => true];
            $out[] = ['type' => 'html', 'show_in' => ['create'], 'html' => '<div class="alert alert-info small">This line goes with entry <a href="'
                . esc(base_url('admin/ledger/entries/edit/' . rawurlencode($values['with_entry_public_id'])), 'attr') . '">' . esc($values['with_entry_public_id'])
                . '</a>, in the same transaction. For its VAT, choose the tax account as the To or From account, the tax code, and the amount before VAT as the taxable amount.</div>'];
        }
        if ([] !== $values && isset($values['public_id'])) {
            $out[] = ['show_in' => ['view']] + $heading('Transaction');
            $out[] = ['type' => 'html', 'show_in' => ['view'], 'html' => $this->journalHtml($values)];
        }
        if ([] !== $values) {
            $base  = self::formatAmount($values['base_currency_code'] ?? null, $values['base_amount'] ?? null);
            $out[] = ['type' => 'html', 'show_in' => ['view'], 'html' => '<div class="row mb-3"><div class="col-sm-4 text-sm-end">Base amount</div><div class="col-sm-8">'
                . ('' === $base ? '<span class="text-secondary">None yet: save the entry with a rate</span>' : esc($base))
                . ' <span class="text-secondary">' . esc((string) ($values['entity_name'] ?? '')) . '</span></div></div>'];
        }
        if ([] !== $values) {
            $entered = esc((string) ($values['created_by_name'] ?? '')) . ' ' . localTimeTag($values['created_at'] ?? null);
            $out[]   = ['show_in' => ['view']] + $heading('Record');
            $out[]   = ['type' => 'html', 'show_in' => ['view'], 'html' => '<div class="row mb-3"><div class="col-sm-4 text-sm-end">Entered by</div>'
                . '<div class="col-sm-8">' . trim($entered) . '</div></div>'];
        }
        return $out;
    }

    /**
     * The other rows of the entry's transaction, and the link that adds one
     * @param array $entry
     * @return string
     */
    private function journalHtml(array $entry): string
    {
        $add   = null !== ($entry['source_key'] ?? null)
            ? '<div class="alert alert-info small mb-0">Posted from the subscription billing, so it cannot change here: change the invoice instead.</div>'
            : '<a class="btn btn-sm btn-outline-primary" href="' . esc(base_url('admin/ledger/entries/create') . '?with=' . rawurlencode($entry['public_id']), 'attr') . '">'
            . '<i class="bi bi-plus-lg"></i> Add a line, such as its VAT or withholding tax</a>';
        $lines = '';
        if (null !== ($entry['journal_id'] ?? null)) {
            [$code, $body] = $this->callApi('v1/staff/ledger/entries', ['start' => 0, 'length' => 50, 'filters' => ['journal_id' => $entry['journal_id']]]);
            foreach (200 === $code ? (array) ($body['data']['rows'] ?? []) : [] as $row) {
                $current = $row['public_id'] === $entry['public_id'];
                $lines  .= '<li>' . ($current ? '<strong>' : '<a href="' . esc(base_url('admin/ledger/entries/edit/' . rawurlencode($row['public_id'])), 'attr') . '">')
                    . esc($row['from_account_name'] . ' → ' . $row['to_account_name']) . ($current ? '</strong>' : '</a>')
                    . ' <span class="text-secondary">' . esc(self::formatAmount($row['transaction_currency_code'], $row['transaction_amount']))
                    . (null === ($row['tax_code'] ?? null) ? '' : ' · ' . esc($row['tax_code'])) . '</span></li>';
            }
        }
        return '<div class="row mb-3"><div class="col-sm-4 text-sm-end">Rows</div><div class="col-sm-8">'
            . ('' === $lines ? '<p class="text-secondary mb-2">Only this one</p>' : '<ul class="mb-2">' . $lines . '</ul>') . $add . '</div></div>';
    }

    /**
     * The stand-alone upload form of an entry's receipt, with a link to the current one
     * @param array $entry
     * @return array
     */
    private function receiptForm(array $entry): array
    {
        $hasReceipt = ! empty($entry['has_receipt']);
        $current    = $hasReceipt
            ? '<a href="' . esc(base_url('admin/ledger/entries/receipt/' . rawurlencode($entry['public_id'])), 'attr') . '" target="_blank" rel="noopener">'
                . '<i class="bi bi-file-earmark-pdf"></i> Open the receipt</a>'
            : '<span class="text-secondary">No receipt yet</span>';
        return [
            'form_id'   => 'ledger-receipt-form',
            'title'     => 'Receipt',
            'subtitle'  => 'The PDF of the bill or receipt; a new upload replaces the current one',
            'id_prefix' => 'receipt-',
            'id_field'  => 'public_id',
            'id'        => $entry['public_id'],
            'urls'      => ['submit' => base_url('admin/ledger/entries/receipt-upload'), 'delete' => base_url('admin/ledger/entries/receipt-delete')],
            'messages'  => ['confirmDeleteImage' => 'Are you sure you want to delete this receipt? This cannot be undone.'],
            'fields'    => [
                ['type' => 'html', 'html' => '<div class="row mb-3"><div class="col-sm-4 text-sm-end">Current receipt</div><div class="col-sm-8">' . $current . '</div></div>'],
                ['name' => 'receipt', 'type' => 'file', 'label' => 'PDF file', 'rules' => 'required', 'accept' => 'application/pdf',
                 'types' => ['application/pdf'], 'max_size' => self::RECEIPT_MAX_SIZE, 'max_size_label' => '10MB',
                 'help' => 'PDF only. Max size 10MB', 'preview' => '', 'stored' => $hasReceipt, 'default_preview' => ''],
            ],
        ];
    }

    // ---- API ----

    /**
     * The options of the entry form's and list's dropdowns: accounts, categories and counterparties as
     * public_id => label
     * @return array{accounts: array, categories: array, counterparties: array}
     */
    private function lookups(): array
    {
        $out = [];
        foreach ($this->lookup('accounts') as $row) {
            $type = lang('OtternovaLedgerAccount.enum.account_type.' . $row['account_type']);
            $out['accounts'][$row['public_id']] = $row['account_name'] . ' (' . $type . ', ' . $row['currency_code']
                . (null === ($row['entity_name'] ?? null) ? '' : ', ' . $row['entity_name']) . ')';
        }
        foreach ($this->lookup('categories') as $row) {
            $out['categories'][$row['public_id']] = $row['category_name'] . ' (' . $row['category_code'] . ')';
        }
        foreach ($this->lookup('counterparties') as $row) {
            $out['counterparties'][$row['public_id']] = $row['counterparty_name'];
        }
        foreach ($this->lookup('tax-codes') as $row) {
            $out['tax_codes'][$row['public_id']] = $row['tax_code'] . ' · ' . $row['tax_name'] . ' (' . rtrim(rtrim((string) $row['rate'], '0'), '.') . '%, ' . $row['entity_name'] . ')';
        }
        return $out + ['accounts' => [], 'categories' => [], 'counterparties' => [], 'tax_codes' => []];
    }

    /**
     * The entities as public_id => name, for the dropdowns
     * @return array<string, string>
     */
    private function entityOptions(): array
    {
        return array_column($this->lookup('entities'), 'entity_name', 'public_id');
    }

    /**
     * Every entity, account, category or counterparty, from LINK_INTERNAL_API v1/staff/ledger/{kind}
     * @param string $kind A key of RECORDS
     * @return list<array>
     * @throws RuntimeException When the API call fails, with the message to show
     */
    private function lookup(string $kind): array
    {
        [$code, $body] = $this->callApi('v1/staff/ledger/' . $kind, []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the list. Please try again.');
        }
        return (array) ($body['data']['rows'] ?? []);
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array $payload
     * @param bool $multipart true: sent as multipart/form-data (files as CURLFile), otherwise as JSON
     * @return array
     */
    private function callApi(string $endpoint, array $payload, bool $multipart = false): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                ($multipart ? 'multipart' : 'json') => $payload,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . session()->get('access_token'),
                ],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            // a 404 without the API's own error body means the endpoint is missing, not the record
            if (! is_array($body) || $code >= 500 || ($code === 404 && ($body['status'] ?? '') !== 'error')) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => 'The service is unavailable. Please try again later.']];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => 'The service is unavailable. Please try again later.']];
        }
    }

    /**
     * Form response of a failed save
     * @param int    $code     HTTP status of the API call
     * @param array  $body     decoded API response
     * @param string $fallback message when the API gives none
     * @return ResponseInterface
     */
    private function saveFailed(int $code, array $body, string $fallback): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => $body['errors'] ?? null,
            'csrf_hash' => csrf_hash(), // keeps the form's token fresh when CSRF regenerates
        ]);
    }

    // ---- formatting ----

    /**
     * An amount from the API ("3600.5000") with its currency, for the list: "THB 3,600.50"
     * @param string|null $currency
     * @param string|null $amount
     * @return string
     */
    private static function formatAmount(?string $currency, ?string $amount): string
    {
        $amount = self::trimAmount($amount);
        if ('' === $amount) {
            return '';
        }
        [$whole, $fraction] = explode('.', $amount . '.');
        return trim($currency . ' ' . number_format((int) $whole) . '.' . str_pad($fraction, 2, '0'));
    }

    /**
     * A rate from the API without the zeros DECIMAL(20,10) pads it with: "36.5"
     * @param string|int|float|null $rate
     * @return string
     */
    private static function trimRate(string|int|float|null $rate): string
    {
        if (null === $rate || '' === (string) $rate) {
            return '';
        }
        return str_contains((string) $rate, '.') ? rtrim(rtrim((string) $rate, '0'), '.') : (string) $rate;
    }

    /**
     * An amount from the API without the zeros DECIMAL(19,4) pads it with, keeping 2 decimal places: "3600.50"
     * @param string|int|float|null $amount
     * @return string
     */
    private static function trimAmount(string|int|float|null $amount): string
    {
        if (null === $amount || '' === (string) $amount) {
            return '';
        }
        [$whole, $fraction] = explode('.', (string) $amount . '.');
        return $whole . '.' . str_pad(rtrim($fraction, '0'), 2, '0');
    }
}
