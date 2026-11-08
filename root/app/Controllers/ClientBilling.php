<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * The businesses' subscriptions, invoices and payments, behind the auth and department:EXE,FIN filters: the staff
 * look them up and approve or reject the payments waiting for review. Everything is read and saved through
 * LINK_INTERNAL_API v1/staff/subscriptions/*, which checks the department again. Records are in the URL by their
 * public_id; every time from the API is UTC and shown in the viewer's timezone.
 */
class ClientBilling extends BaseController
{
    public const SUBSCRIPTION_STATUSES = [
        'P' => ['label' => 'Awaiting first payment', 'class' => 'bg-warning text-dark'],
        'A' => ['label' => 'Active', 'class' => 'bg-success'],
        'S' => ['label' => 'Suspended', 'class' => 'bg-danger'],
        'C' => ['label' => 'Cancelled', 'class' => 'bg-secondary'],
        'X' => ['label' => 'Expired', 'class' => 'bg-secondary'],
    ];
    public const INVOICE_STATUSES = [
        'P' => ['label' => 'Unpaid', 'class' => 'bg-warning text-dark'],
        'D' => ['label' => 'Paid', 'class' => 'bg-success'],
        'V' => ['label' => 'Void', 'class' => 'bg-secondary'],
    ];
    public const PAYMENT_STATUSES = [
        'P' => ['label' => 'Waiting for review', 'class' => 'bg-warning text-dark'],
        'S' => ['label' => 'Approved', 'class' => 'bg-success'],
        'F' => ['label' => 'Rejected', 'class' => 'bg-danger'],
    ];
    public const WHT_STATUSES = [
        'R' => ['label' => 'Waiting for review', 'class' => 'bg-warning text-dark'],
        'A' => ['label' => 'Approved', 'class' => 'bg-success'],
        'N' => ['label' => 'Not withholding', 'class' => 'bg-secondary'],
    ];
    public const CERT_STATUSES = [
        'N' => ['label' => 'Not needed', 'class' => 'bg-secondary'],
        'W' => ['label' => 'Waiting for certificate', 'class' => 'bg-warning text-dark'],
        'U' => ['label' => 'Certificate to review', 'class' => 'bg-info text-dark'],
        'A' => ['label' => 'Certificate accepted', 'class' => 'bg-success'],
        'T' => ['label' => 'Billed as top-up', 'class' => 'bg-danger'],
    ];
    public const CERTIFICATE_STATUSES = [
        'P' => ['label' => 'Waiting for review', 'class' => 'bg-warning text-dark'],
        'A' => ['label' => 'Accepted', 'class' => 'bg-success'],
        'R' => ['label' => 'Rejected', 'class' => 'bg-danger'],
    ];
    public const BILLING_CYCLES = ['M' => 'Monthly', 'Y' => 'Yearly'];
    public const BILLING_MODES  = ['A' => 'Automatic', 'M' => 'Manual'];
    /** The numbers of the billing documents, as the API issues them */
    private const DOCUMENT_NUMBERS = ['invoice' => '/^INV-\d{6}-\d{6,}$/', 'receipt' => '/^RCT-\d{6}-\d{6,}$/'];

    // ---- subscriptions ----

    /**
     * Every business's subscriptions, newest first
     * @return string
     */
    public function subscriptions(): string
    {
        return view('generic_list', [
            'page_title' => 'Subscriptions',
            'slug'       => 'clients-subscriptions',
            'listConfig' => $this->subscriptionsListConfig($this->planOptions()),
        ]);
    }

    /**
     * One page of subscriptions: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/subscriptions/list,
     * which always orders newest first and searches the business's name and slug
     * @return ResponseInterface
     */
    public function subscriptionsList(): ResponseInterface
    {
        $config = $this->subscriptionsListConfig(null);
        return $this->apiList($config, 'v1/staff/subscriptions/list', 'Could not load the subscriptions. Please try again.', static function (array $row): array {
            $row['business']   = self::businessLabel($row);
            $row['plan']       = $row['plan_code'] . ' · ' . (self::BILLING_CYCLES[$row['billing_cycle']] ?? $row['billing_cycle']);
            $row['price_label'] = self::money($row['currency_code'], $row['price']);
            $row['current_period_end'] = (string) ($row['current_period_end'] ?? '');
            $row['next_billing_at']    = (string) ($row['next_billing_at'] ?? '');
            return $row;
        });
    }

    /**
     * One subscription with its invoices
     * @param string $publicId
     * @return string
     */
    public function subscriptionView(string $publicId): string
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/subscription', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the subscription.');
        }
        $subscription = (array) ($body['data']['subscription'] ?? []);

        return view('admin_client_subscription', [
            'page_title'   => 'Subscription of ' . ($subscription['business_name'] ?? $publicId),
            'slug'         => 'clients-subscriptions',
            'breadcrumbs'  => [base_url('admin/clients/subscriptions') => 'Subscriptions'],
            'subscription' => $subscription,
            'invoices'     => (array) ($body['data']['invoices'] ?? []),
        ]);
    }

    /**
     * Suspend (S), reactivate (A) or cancel now (C) a subscription, through LINK_INTERNAL_API
     * v1/staff/subscriptions/subscription/status: { public_id, subscription_status }. The API decides which change
     * the subscription's status allows.
     * @return ResponseInterface
     */
    public function setStatus(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/subscription/status', [
            'public_id'           => trim((string) $this->request->getPost('public_id')),
            'subscription_status' => strtoupper(trim((string) $this->request->getPost('subscription_status'))),
        ]);
        return $this->reviewed($code, $body, 'Could not change the subscription. Please try again.');
    }

    // ---- billing documents ----

    /**
     * GET admin/clients/documents/{invoice|receipt}/{number}[?download=1]
     * An invoice (INV-...) or a receipt (RCT-...) as the API renders it (v1/staff/documents/*), the same page the
     * business gets: opened in a new tab, where it prints on A4 or saves as PDF, or saved as an .html file with
     * ?download=1, for a business that asks the staff for one
     * @param string $kind   invoice or receipt
     * @param string $number invoice_no or receipt_no
     * @return ResponseInterface
     */
    public function document(string $kind, string $number): ResponseInterface
    {
        $number = strtoupper($number);
        if (! isset(self::DOCUMENT_NUMBERS[$kind]) || ! preg_match(self::DOCUMENT_NUMBERS[$kind], $number)) {
            throw PageNotFoundException::forPageNotFound('There is no such ' . $kind . '.');
        }
        $endpoint = 'v1/staff/documents/' . $kind . '/' . $number;
        try {
            $response = service('curlrequest')->get(getenv('LINK_INTERNAL_API') . $endpoint, [
                'headers'     => ['Accept' => 'text/html', 'Authorization' => 'Bearer ' . session()->get('access_token')],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            throw new RuntimeException('Could not load the ' . $kind . '. Please try again.');
        }
        $code = $response->getStatusCode();
        if (404 === $code) {
            throw PageNotFoundException::forPageNotFound('There is no such ' . $kind . '.');
        }
        if (200 !== $code || ! str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
            $body = json_decode((string) $response->getBody(), true);
            throw new RuntimeException(is_array($body) && isset($body['message']) ? (string) $body['message'] : 'Could not load the ' . $kind . '. Please try again.');
        }

        // the page is the API's, so it keeps the API's policy: nothing but its own styles, fonts and embedded images
        $disposition = '1' === $this->request->getGet('download') ? 'attachment' : 'inline';
        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', $disposition . '; filename="' . $number . '.html"')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src data:; base-uri 'none'; form-action 'none'")
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setContentType('text/html', 'UTF-8')
            ->setBody((string) $response->getBody());
    }

    /**
     * The links to view and download an invoice's documents: its invoice, and its receipt once paid
     * @param array $invoice From the API, with invoice_no, receipt_no and invoice_status
     * @return string HTML
     */
    public static function documentLinks(array $invoice): string
    {
        $links = [];
        foreach (['invoice' => 'Invoice', 'receipt' => 'Receipt'] as $kind => $label) {
            $number = (string) ($invoice[$kind . '_no'] ?? '');
            if ('' === $number || ('receipt' === $kind && 'D' !== ($invoice['invoice_status'] ?? ''))) {
                continue;
            }
            $url     = base_url('admin/clients/documents/' . $kind . '/' . rawurlencode($number));
            $links[] = '<span class="text-nowrap">'
                . '<a href="' . esc($url, 'attr') . '" target="_blank" rel="noopener" title="View ' . esc($number, 'attr') . '"><i class="bi bi-file-earmark-text"></i> ' . $label . '</a>'
                . ' <a href="' . esc($url . '?download=1', 'attr') . '" class="ms-1" title="Download ' . esc($number, 'attr') . '"><i class="bi bi-download"></i></a>'
                . '</span>';
        }
        return [] === $links ? '<span class="text-muted">&mdash;</span>' : implode(' <span class="text-secondary">·</span> ', $links);
    }

    // ---- invoices ----

    /**
     * Every business's invoices; the unpaid ones at first, oldest due first, so the staff see what waits
     * @return string
     */
    public function invoices(): string
    {
        $config = $this->invoicesListConfig($this->planOptions());
        $config['values'] = ['invoice_status' => 'P'];
        if ('Y' === $this->request->getGet('review')) {
            $config['values']['awaiting_review'] = 'Y';
        }

        return view('generic_list', [
            'page_title' => 'Invoices & Payments',
            'slug'       => 'clients-invoices',
            'listConfig' => $config,
        ]);
    }

    /**
     * One page of invoices: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/subscriptions/invoices
     * @return ResponseInterface
     */
    public function invoicesList(): ResponseInterface
    {
        $config = $this->invoicesListConfig(null);
        return $this->apiList($config, 'v1/staff/subscriptions/invoices', 'Could not load the invoices. Please try again.', static function (array $row): array {
            $row['business']     = self::businessLabel($row);
            $row['plan']         = $row['plan_code'] . ' · ' . (self::BILLING_CYCLES[$row['billing_cycle']] ?? $row['billing_cycle']);
            $row['amount_label'] = self::money($row['currency_code'], $row['amount_payable'] ?? $row['amount_due']);
            $row['review']       = $row['pending_payments'] > 0 ? 'Y' : 'N';
            return $row;
        });
    }

    /**
     * One invoice with its payments; a payment waiting for review can be approved or rejected here
     * @param string $publicId
     * @return string
     */
    public function invoiceView(string $publicId): string
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/invoice', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the invoice.');
        }
        $invoice = (array) ($body['data']['invoice'] ?? []);

        return view('admin_client_invoice', [
            'page_title'  => 'Invoice ' . ($invoice['invoice_no'] ?? $publicId),
            'slug'        => 'clients-invoices',
            'breadcrumbs' => [base_url('admin/clients/invoices') => 'Invoices & Payments'],
            'invoice'     => $invoice,
        ]);
    }

    /**
     * Approve a payment through LINK_INTERNAL_API v1/staff/subscriptions/payment/approve: its invoice is paid
     * @return ResponseInterface
     */
    public function approvePayment(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/payment/approve', [
            'public_id' => trim((string) $this->request->getPost('public_id')),
        ]);
        return $this->reviewed($code, $body, 'Could not approve the payment. Please try again.');
    }

    /**
     * Reject a payment through LINK_INTERNAL_API v1/staff/subscriptions/payment/reject, with the reason the
     * business is shown; the invoice can be paid again
     * @return ResponseInterface
     */
    public function rejectPayment(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/payment/reject', [
            'public_id'      => trim((string) $this->request->getPost('public_id')),
            'failure_reason' => trim((string) $this->request->getPost('failure_reason')),
        ]);
        return $this->reviewed($code, $body, 'Could not reject the payment. Please try again.');
    }

    /**
     * A payment's slip, passed on from LINK_INTERNAL_API GET v1/staff/subscriptions/slip/{public_id}: the browser
     * cannot send the JWT itself, so the image or PDF comes through here
     * @param string $publicId The payment's
     * @return ResponseInterface
     */
    public function slip(string $publicId): ResponseInterface
    {
        return $this->passFile('v1/staff/subscriptions/slip/' . rawurlencode($publicId), 'slip-' . $publicId, 'This payment has no slip.');
    }

    /**
     * An image or PDF from LINK_INTERNAL_API, passed on with the signed-in user's JWT, never cached
     * @param string $endpoint
     * @param string $name     The file name, before its extension
     * @param string $missing  The message of the 404 when there is none
     * @return ResponseInterface
     */
    private function passFile(string $endpoint, string $name, string $missing): ResponseInterface
    {
        try {
            $response = service('curlrequest')->get(getenv('LINK_INTERNAL_API') . $endpoint, [
                'headers'     => ['Authorization' => 'Bearer ' . session()->get('access_token')],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            throw new RuntimeException('Could not load the file.');
        }
        $type = strtok($response->getHeaderLine('Content-Type'), ';');
        if ($response->getStatusCode() !== 200 || ! in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            throw PageNotFoundException::forPageNotFound($missing);
        }
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$type];

        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'inline; filename="' . preg_replace('/[^0-9A-Za-z_-]/', '', $name) . '.' . $extension . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType($type, '')
            ->setBody($response->getBody());
    }

    // ---- withholding tax ----

    /**
     * Withholding tax: the businesses asking to withhold (?requests=R|A|N), and the certificates sent for the tax
     * withheld (?certificates=P|A|R), the ones waiting first. Both are reviewed on this page.
     * @return string
     */
    public function withholding(): string
    {
        $requestStatus = strtoupper((string) $this->request->getGet('requests'));
        $requestStatus = isset(self::WHT_STATUSES[$requestStatus]) ? $requestStatus : 'R';
        $certStatus    = strtoupper((string) $this->request->getGet('certificates'));
        $certStatus    = isset(self::CERTIFICATE_STATUSES[$certStatus]) ? $certStatus : 'P';

        [$code, $body] = $this->callApi('v1/staff/subscriptions/withholding', ['start' => 0, 'length' => 100, 'filters' => ['wht_status' => $requestStatus]]);
        $requests      = $code === 200 && ($body['status'] ?? '') === 'success' ? (array) ($body['data']['rows'] ?? []) : null;
        [$code, $body] = $this->callApi('v1/staff/subscriptions/certificates', ['start' => 0, 'length' => 100, 'filters' => ['cert_status' => $certStatus]]);
        $certificates  = $code === 200 && ($body['status'] ?? '') === 'success' ? (array) ($body['data']['rows'] ?? []) : null;

        return view('admin_client_withholding', [
            'page_title'    => 'Withholding Tax',
            'slug'          => 'clients-withholding',
            'requestStatus' => $requestStatus,
            'certStatus'    => $certStatus,
            'requests'      => $requests,
            'certificates'  => $certificates,
        ]);
    }

    /**
     * Approve (A) or turn down (N) a business's request to withhold tax, through LINK_INTERNAL_API
     * v1/staff/subscriptions/withholding/review: { business_slug, wht_status }
     * @return ResponseInterface
     */
    public function reviewWithholding(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/withholding/review', [
            'business_slug' => trim((string) $this->request->getPost('business_slug')),
            'wht_status'    => trim((string) $this->request->getPost('wht_status')),
        ]);
        return $this->reviewed($code, $body, 'Could not save the decision. Please try again.');
    }

    /**
     * Accept a certificate through LINK_INTERNAL_API v1/staff/subscriptions/certificate/accept; the message names a
     * paid top-up to refund
     * @return ResponseInterface
     */
    public function acceptCertificate(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/certificate/accept', [
            'public_id' => trim((string) $this->request->getPost('public_id')),
        ]);
        return $this->reviewed($code, $body, 'Could not accept the certificate. Please try again.');
    }

    /**
     * Reject a certificate through LINK_INTERNAL_API v1/staff/subscriptions/certificate/reject, with the reason the
     * business is shown; it can send another
     * @return ResponseInterface
     */
    public function rejectCertificate(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/subscriptions/certificate/reject', [
            'public_id'        => trim((string) $this->request->getPost('public_id')),
            'rejection_reason' => trim((string) $this->request->getPost('rejection_reason')),
        ]);
        return $this->reviewed($code, $body, 'Could not reject the certificate. Please try again.');
    }

    /**
     * A certificate's file, passed on from LINK_INTERNAL_API GET v1/staff/subscriptions/certificate-file/{public_id}
     * @param string $publicId The certificate's
     * @return ResponseInterface
     */
    public function certificateFile(string $publicId): ResponseInterface
    {
        return $this->passFile('v1/staff/subscriptions/certificate-file/' . rawurlencode($publicId), 'certificate-' . $publicId, 'This certificate has no file.');
    }

    // ---- configuration ----

    /**
     * Table Builder configuration of the subscription list, shared by the page and its JSON endpoint
     * @param array|null $plans plan_code => label for the plan filter; null for the JSON endpoint (the API checks it)
     * @return array
     */
    private function subscriptionsListConfig(?array $plans): array
    {
        return [
            'table_id'    => 'clientSubscriptionTable',
            'title'       => 'Subscriptions',
            'subtitle'    => 'Every business\'s subscriptions, newest first; the search looks in the business name and slug',
            'id_field'    => 'public_id',
            'urls'        => [
                'data' => base_url('admin/clients/subscriptions'),
                'edit' => base_url('admin/clients/subscriptions/view/{public_id}'),
            ],
            'columns'     => [
                self::column('business', 'Business', ['searchable' => true]),
                self::column('plan', 'Plan'),
                self::column('price_label', 'Price', ['class' => 'text-end text-nowrap']),
                self::column('subscription_status', 'Status', ['type' => 'badge', 'badges' => self::SUBSCRIPTION_STATUSES]),
                self::column('current_period_end', 'Period Ends', ['type' => 'datetime']),
                self::column('next_billing_at', 'Next Billing', ['type' => 'datetime']),
            ],
            'filters'     => [
                ['name' => 'subscription_status', 'type' => 'select', 'label' => 'Status', 'options' => array_map(static fn (array $b): string => $b['label'], self::SUBSCRIPTION_STATUSES)],
                ['name' => 'plan_code', 'type' => 'select', 'label' => 'Plan', 'options' => $plans ?? []],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of the invoice list, shared by the page and its JSON endpoint
     * @param array|null $plans plan_code => label for the plan filter; null for the JSON endpoint (the API checks it)
     * @return array
     */
    private function invoicesListConfig(?array $plans): array
    {
        return [
            'table_id'    => 'clientInvoiceTable',
            'title'       => 'Invoices & Payments',
            'subtitle'    => 'Unpaid invoices come oldest due first; the search looks in the invoice number and the business name and slug',
            'id_field'    => 'public_id',
            'urls'        => [
                'data' => base_url('admin/clients/invoices'),
                'edit' => base_url('admin/clients/invoices/view/{public_id}'),
            ],
            'columns'     => [
                self::column('invoice_no', 'Invoice No.', ['searchable' => true, 'class' => 'text-nowrap']),
                self::column('business', 'Business', ['searchable' => true]),
                self::column('plan', 'Plan'),
                self::column('amount_label', 'To Pay', ['class' => 'text-end text-nowrap']),
                self::column('due_at', 'Due', ['type' => 'datetime']),
                self::column('invoice_status', 'Status', ['type' => 'badge', 'badges' => self::INVOICE_STATUSES]),
                self::column('review', 'Payment', ['type' => 'badge', 'badges' => [
                    'Y' => ['label' => 'To review', 'class' => 'bg-info text-dark'],
                    'N' => ['label' => '—', 'class' => 'bg-transparent text-secondary'],
                ]]),
            ],
            'filters'     => [
                ['name' => 'invoice_status', 'type' => 'select', 'label' => 'Status', 'options' => array_map(static fn (array $b): string => $b['label'], self::INVOICE_STATUSES)],
                ['name' => 'awaiting_review', 'type' => 'select', 'label' => 'Payment', 'options' => ['Y' => 'Waiting for review']],
                ['name' => 'plan_code', 'type' => 'select', 'label' => 'Plan', 'options' => $plans ?? []],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * A Table Builder column the API orders (never by the table) and does not search unless said so
     * @param string $data
     * @param string $title
     * @param array  $extra
     * @return array
     */
    private static function column(string $data, string $title, array $extra = []): array
    {
        return $extra + ['data' => $data, 'db' => $data, 'title' => $title, 'orderable' => false, 'searchable' => false];
    }

    // ---- API ----

    /**
     * The JSON of a list the API pages, searches and filters itself
     * @param array    $config   Table Builder configuration
     * @param string   $endpoint
     * @param string   $fallback Message when the API gives none
     * @param callable $rowFn    fn(array $row): array, formatting a row for the table
     * @return ResponseInterface
     */
    private function apiList(array $config, string $endpoint, string $fallback, callable $rowFn): ResponseInterface
    {
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi($endpoint, [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $body['message'] ?? $fallback]);
        }

        $result = (array) ($body['data'] ?? []);
        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($result['records_total'] ?? 0),
            (int) ($result['records_filtered'] ?? 0),
            lb_rows(array_map($rowFn, (array) ($result['rows'] ?? [])), $config)
        ));
    }

    /**
     * The plans, as plan_code => plan_code, for the plan filters; none when they cannot be loaded
     * @return array<string, string>
     */
    private function planOptions(): array
    {
        [$code, $body] = $this->callApi('v1/staff/plans/plans', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [];
        }
        $plans = array_column((array) ($body['data']['rows'] ?? []), 'plan_code');
        return array_combine($plans, $plans);
    }

    /**
     * JSON answer of an approve or reject
     * @param int    $code
     * @param array  $body
     * @param string $fallback
     * @return ResponseInterface
     */
    private function reviewed(int $code, array $body, string $fallback): ResponseInterface
    {
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? $fallback,
                'errors'    => $body['errors'] ?? null,
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Done.',
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array  $payload Sent as JSON
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

    // ---- formatting, also used by the views ----

    /**
     * "Otter Cafe (otter-cafe)"
     * @param array $row With business_name and business_slug
     * @return string
     */
    public static function businessLabel(array $row): string
    {
        return trim(($row['business_name'] ?? '') . ' (' . ($row['business_slug'] ?? '') . ')');
    }

    /**
     * An amount from the API ("299.00") in its currency's format; '' when there is none
     * @param string|null $currency
     * @param mixed       $amount
     * @return string
     */
    public static function money(?string $currency, mixed $amount): string
    {
        if (null === $amount || '' === (string) $amount || ! is_numeric($amount)) {
            return '';
        }
        $formatted = formatCurrency((float) $amount, (string) $currency);
        return str_starts_with($formatted, '[') ? trim($currency . ' ' . number_format((float) $amount, 2)) : $formatted;
    }

    /**
     * A status badge
     * @param array<string, array{label: string, class: string}> $statuses
     * @param string|null $value
     * @return string
     */
    public static function badge(array $statuses, ?string $value): string
    {
        $badge = $statuses[(string) $value] ?? ['label' => (string) $value, 'class' => 'bg-secondary'];
        return '<span class="badge ' . esc($badge['class'], 'attr') . '">' . esc($badge['label']) . '</span>';
    }

    /**
     * "start – end" of a billing period; a first invoice has none until it is paid
     * @param string|null $start UTC
     * @param string|null $end   UTC
     * @return string
     */
    public static function period(?string $start, ?string $end): string
    {
        return null === $start || '' === $start ? '<span class="text-secondary">Starts when paid</span>' : self::time($start) . ' – ' . self::time($end);
    }

    /**
     * A <time data-utc> tag for a UTC time from the API, which main.js shows in the viewer's timezone
     * (html_helper's localTime() is never defined: PHP's own localtime() already has that name)
     * @param string|null $utc
     * @return string
     */
    public static function time(?string $utc): string
    {
        return null === $utc || '' === $utc ? '<span class="text-muted">&mdash;</span>'
            : '<time data-utc datetime="' . esc($utc, 'attr') . '">' . esc($utc) . ' UTC</time>';
    }
}
