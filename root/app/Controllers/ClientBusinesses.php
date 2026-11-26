<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Every business in the system, behind the auth filter: any staff member lists them, filtered by whether they are open
 * on the marketplace, their withholding, their latest subscription and their country, and opens one to see its
 * business_master details and everything under it: its staff, its stores, and its products and services with their
 * variants. Read through LINK_INTERNAL_API v1/staff/businesses/*, which sends the tax ID to the
 * Executive Office and Finance only. Businesses are in the URL by their business_slug; every time is UTC.
 */
class ClientBusinesses extends BaseController
{
    public const OPEN_STATUSES = [
        'Y' => ['label' => 'Open', 'class' => 'bg-success'],
        'N' => ['label' => 'Closed', 'class' => 'bg-secondary'],
    ];
    public const ARTIFACT_TYPES = [
        'P' => ['label' => 'Product', 'class' => 'bg-info text-dark'],
        'S' => ['label' => 'Service', 'class' => 'bg-primary'],
    ];
    public const ARTIFACT_STATUSES = [
        'A' => ['label' => 'Active', 'class' => 'bg-success'],
        'I' => ['label' => 'Inactive', 'class' => 'bg-secondary'],
        'D' => ['label' => 'Discontinued', 'class' => 'bg-dark'],
    ];
    public const VARIANT_STATUSES = [
        'A' => ['label' => 'On sale', 'class' => 'bg-success'],
        'I' => ['label' => 'Off sale', 'class' => 'bg-secondary'],
    ];
    public const STAFF_ROLES = [
        'O' => ['label' => 'Owner', 'class' => 'bg-primary'],
        'M' => ['label' => 'Manager', 'class' => 'bg-info text-dark'],
        'E' => ['label' => 'Employee', 'class' => 'bg-light text-dark border'],
    ];
    public const STAFF_STATUSES = [
        'A' => ['label' => 'Active', 'class' => 'bg-success'],
        'T' => ['label' => 'Terminated', 'class' => 'bg-secondary'],
    ];
    /** auth_master.account_status */
    public const ACCOUNT_STATUSES = [
        'A' => ['label' => 'Active', 'class' => 'bg-success'],
        'P' => ['label' => 'Pending', 'class' => 'bg-warning text-dark'],
        'B' => ['label' => 'Blocked', 'class' => 'bg-danger'],
        'S' => ['label' => 'Suspended', 'class' => 'bg-secondary'],
    ];
    /** ClientBilling::SUBSCRIPTION_STATUSES, and NONE for a business that never subscribed */
    public const NO_SUBSCRIPTION = 'NONE';

    /**
     * Every business, newest first
     * @return string
     */
    public function businesses(): string
    {
        return view('generic_list', [
            'page_title' => 'Businesses',
            'slug'       => 'clients-businesses',
            'listConfig' => $this->listConfig(),
        ]);
    }

    /**
     * One page of businesses: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/businesses/list, which
     * always orders newest first and searches the business's name, slug and contact email
     * @return ResponseInterface
     */
    public function businessesList(): ResponseInterface
    {
        $config  = $this->listConfig();
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi('v1/staff/businesses/list', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $body['message'] ?? 'Could not load the businesses. Please try again.']);
        }

        helper('list');
        $countries = listSupportedCountries();
        $rows      = array_map(static function (array $row) use ($countries): array {
            $row['business']            = ClientBilling::businessLabel($row);
            $row['country']             = $countries[$row['country_code'] ?? ''] ?? (string) ($row['country_code'] ?? '');
            $row['subscription_status'] = $row['subscription_status'] ?? self::NO_SUBSCRIPTION;
            $row['plan_code']           = (string) ($row['plan_code'] ?? '');
            return $row;
        }, (array) ($body['data']['rows'] ?? []));

        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($body['data']['records_total'] ?? 0),
            (int) ($body['data']['records_filtered'] ?? 0),
            lb_rows($rows, $config)
        ));
    }

    /**
     * One business's details
     * @param string $slug
     * @return string
     */
    public function businessView(string $slug): string
    {
        [$code, $body] = $this->callApi('v1/staff/businesses/business', ['business_slug' => $slug]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the business.');
        }
        $business = (array) ($body['data'] ?? []);
        // a tab that cannot load says so, rather than the whole page failing
        $tab = function (string $endpoint) use ($slug): array {
            [$code, $body] = $this->callApi($endpoint, ['business_slug' => $slug]);
            return $code === 200 && ($body['status'] ?? '') === 'success'
                ? ['rows' => (array) ($body['data'] ?? []), 'error' => null]
                : ['rows' => [], 'error' => (string) ($body['message'] ?? 'Could not load this list. Please try again.')];
        };

        return view('admin_client_business', [
            'page_title'     => (string) ($business['business_name'] ?? $slug),
            'slug'           => 'clients-businesses',
            'breadcrumbs'    => [base_url('admin/clients/businesses') => 'Businesses'],
            'business'       => $business,
            'staff'          => $tab('v1/staff/businesses/staff'),
            'stores'         => $tab('v1/staff/businesses/stores'),
            'artifactConfig' => $this->artifactListConfig($slug),
        ]);
    }

    /**
     * One page of a business's products and services: the DataTables request forwarded to LINK_INTERNAL_API
     * v1/staff/businesses/artifacts, which orders them by name and searches their names and their variants' SKUs
     * @param string $slug
     * @return ResponseInterface
     */
    public function artifactsList(string $slug): ResponseInterface
    {
        $config  = $this->artifactListConfig($slug);
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi('v1/staff/businesses/artifacts', [
            'business_slug' => $slug,
            'start'         => $params['start'],
            'length'        => $params['length'],
            'search'        => $params['search'],
            'filters'       => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $body['message'] ?? 'Could not load the products and services. Please try again.']);
        }

        $rows = array_map(static function (array $row): array {
            $row['category_name'] = (string) ($row['category_name'] ?? '');
            return $row;
        }, (array) ($body['data']['rows'] ?? []));

        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($body['data']['records_total'] ?? 0),
            (int) ($body['data']['records_filtered'] ?? 0),
            lb_rows($rows, $config)
        ));
    }

    /**
     * One product or service of a business, with its variants, their stock and its images
     * @param string $slug
     * @param string $publicId
     * @return string
     */
    public function artifactView(string $slug, string $publicId): string
    {
        [$code, $body] = $this->callApi('v1/staff/businesses/artifact', ['business_slug' => $slug, 'public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the product or service.');
        }
        [$bCode, $bBody] = $this->callApi('v1/staff/businesses/business', ['business_slug' => $slug]);
        $business        = $bCode === 200 ? (array) ($bBody['data'] ?? []) : [];
        $detail          = (array) ($body['data'] ?? []);
        $businessUrl     = base_url('admin/clients/businesses/view/' . rawurlencode($slug));

        return view('admin_client_business_artifact', [
            'page_title'  => (string) ($detail['artifact']['artifact_name'] ?? $publicId),
            'slug'        => 'clients-businesses',
            'breadcrumbs' => [
                base_url('admin/clients/businesses') => 'Businesses',
                $businessUrl                          => (string) ($business['business_name'] ?? $slug),
                $businessUrl . '#products'            => 'Products & Services',
            ],
            'currency'    => (string) ($business['currency_code'] ?? ''),
            'detail'      => $detail,
        ]);
    }

    /**
     * The subscription statuses a business can have, for the badges and the filter
     * @return array<string, array{label: string, class: string}>
     */
    public static function subscriptionStatuses(): array
    {
        return ClientBilling::SUBSCRIPTION_STATUSES + [self::NO_SUBSCRIPTION => ['label' => 'No subscription', 'class' => 'bg-light text-dark border']];
    }

    /**
     * Table Builder configuration of the business list, shared by the page and its JSON endpoint
     * @return array
     */
    private function listConfig(): array
    {
        helper('list');
        $labels = static fn (array $badges): array => array_map(static fn (array $b): string => $b['label'], $badges);

        return [
            'table_id'    => 'clientBusinessTable',
            'title'       => 'Businesses',
            'subtitle'    => 'Every business in the system, newest first; the search looks in the business name, slug and contact email',
            'id_field'    => 'business_slug',
            'urls'        => [
                'data' => base_url('admin/clients/businesses'),
                'edit' => base_url('admin/clients/businesses/view/{business_slug}'),
            ],
            'columns'     => [
                self::column('business', 'Business', ['searchable' => true]),
                self::column('type_code', 'Type'),
                self::column('country', 'Country'),
                self::column('contact_email_address', 'Contact Email'),
                self::column('is_business_open', 'Marketplace', ['type' => 'badge', 'badges' => self::OPEN_STATUSES]),
                self::column('wht_status', 'Withholding', ['type' => 'badge', 'badges' => ClientBilling::WHT_STATUSES]),
                self::column('subscription_status', 'Subscription', ['type' => 'badge', 'badges' => self::subscriptionStatuses()]),
                self::column('plan_code', 'Plan'),
                self::column('created_at', 'Created', ['type' => 'datetime']),
            ],
            'filters'     => [
                ['name' => 'is_business_open', 'type' => 'select', 'label' => 'Marketplace', 'options' => $labels(self::OPEN_STATUSES)],
                ['name' => 'wht_status', 'type' => 'select', 'label' => 'Withholding', 'options' => $labels(ClientBilling::WHT_STATUSES)],
                ['name' => 'subscription_status', 'type' => 'select', 'label' => 'Subscription', 'options' => $labels(self::subscriptionStatuses())],
                ['name' => 'country_code', 'type' => 'select', 'label' => 'Country', 'options' => listSupportedCountries()],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of a business's products and services, shared by its tab and its JSON endpoint
     * @param string $slug
     * @return array
     */
    private function artifactListConfig(string $slug): array
    {
        $labels = static fn (array $badges): array => array_map(static fn (array $b): string => $b['label'], $badges);
        $base   = base_url('admin/clients/businesses/view/' . rawurlencode($slug));

        return [
            'table_id'    => 'businessArtifactTable',
            'title'       => 'Products & Services',
            'subtitle'    => 'Everything the business sells, by name; the search looks in the names and the variants\' SKUs',
            'id_field'    => 'public_id',
            'urls'        => [
                'data' => $base . '/artifacts',
                'edit' => $base . '/artifacts/{public_id}',
            ],
            'columns'     => [
                self::column('artifact_name', 'Name', ['searchable' => true]),
                self::column('artifact_type', 'Type', ['type' => 'badge', 'badges' => self::ARTIFACT_TYPES]),
                self::column('category_name', 'Category'),
                self::column('artifact_status', 'Status', ['type' => 'badge', 'badges' => self::ARTIFACT_STATUSES]),
                self::column('variant_count', 'Variants', ['type' => 'number', 'class' => 'text-end']),
                self::column('price_active_lowest', 'From', ['type' => 'number', 'decimals' => 2, 'class' => 'text-end']),
                self::column('rating_average', 'Rating', ['type' => 'number', 'decimals' => 2, 'class' => 'text-end']),
                self::column('updated_at', 'Updated', ['type' => 'datetime']),
            ],
            'filters'     => [
                ['name' => 'artifact_type', 'type' => 'select', 'label' => 'Type', 'options' => $labels(self::ARTIFACT_TYPES)],
                ['name' => 'artifact_status', 'type' => 'select', 'label' => 'Status', 'options' => $labels(self::ARTIFACT_STATUSES)],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * @param string $data
     * @param string $title
     * @param array  $extra
     * @return array
     */
    private static function column(string $data, string $title, array $extra = []): array
    {
        return $extra + ['data' => $data, 'db' => $data, 'title' => $title, 'orderable' => false, 'searchable' => false];
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
                'timeout'     => 15,
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
}
