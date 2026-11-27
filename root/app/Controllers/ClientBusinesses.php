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
    /** review_status of a business or an artifact (App\Models\ModerationEventModel of the API) */
    public const REVIEW_STATUSES = [
        'U' => ['label' => 'Unreviewed', 'class' => 'bg-light text-dark border'],
        'A' => ['label' => 'Approved', 'class' => 'bg-success'],
        'F' => ['label' => 'Flagged', 'class' => 'bg-warning text-dark'],
        'P' => ['label' => 'Awaiting re-review', 'class' => 'bg-info text-dark'],
    ];
    /** moderation_status of a business, an artifact or a variant */
    public const MODERATION_STATUSES = [
        'N' => ['label' => 'Not blocked', 'class' => 'bg-light text-dark border'],
        'B' => ['label' => 'Blocked', 'class' => 'bg-danger'],
        'R' => ['label' => 'Removed', 'class' => 'bg-dark'],
    ];
    /** moderation_event.event_action */
    public const MODERATION_ACTIONS = [
        'AP' => ['label' => 'Approved', 'class' => 'bg-success'],
        'FL' => ['label' => 'Flagged', 'class' => 'bg-warning text-dark'],
        'BL' => ['label' => 'Blocked', 'class' => 'bg-danger'],
        'UB' => ['label' => 'Unblocked', 'class' => 'bg-primary'],
        'RM' => ['label' => 'Removed', 'class' => 'bg-dark'],
        'RS' => ['label' => 'Changed by the business', 'class' => 'bg-info text-dark'],
    ];
    /** moderation_event.reason_code */
    public const MODERATION_REASONS = [
        'PROHIBITED'            => 'Prohibited or illegal goods or services',
        'MISLEADING'            => 'Misleading or false information',
        'INTELLECTUAL_PROPERTY' => 'Copyright or trademark infringement',
        'AUDIENCE'              => 'Wrong audience or age restriction',
        'BUSINESS_TYPE'         => 'Does not match the declared business type',
        'PRICING'               => 'Unfair or deceptive pricing',
        'OTHER'                 => 'Other',
    ];
    /** moderation_report.report_status: a mart visitor's report of the business or its product or service */
    public const REPORT_STATUSES = [
        'N' => ['label' => 'New', 'class' => 'bg-warning text-dark'],
        'S' => ['label' => 'Filtered as spam', 'class' => 'bg-light text-dark border'],
        'D' => ['label' => 'Dismissed', 'class' => 'bg-secondary'],
        'A' => ['label' => 'Actioned', 'class' => 'bg-success'],
    ];
    /** moderation_report.reason_code: what the visitor reported */
    public const REPORT_REASONS = [
        'PROHIBITED'            => 'Prohibited or illegal goods or services',
        'SCAM'                  => 'Scam or fraud',
        'MISLEADING'            => 'Misleading information',
        'INTELLECTUAL_PROPERTY' => 'Copyright or trademark infringement',
        'OFFENSIVE'             => 'Offensive or hateful content',
        'CHILD_SAFETY'          => 'Child safety',
        'OTHER'                 => 'Other',
    ];
    /** moderation_report.filter_reason: why the API filtered a report as spam */
    public const REPORT_FILTERS = [
        'audience_keyword' => 'About who the business declared it welcomes (LGBTQ+, naturist or adults only)',
        'duplicate'        => 'The same report again from the same sender',
        'link_in_name'     => 'Spam: a link',
        'repeated_name'    => 'Spam',
        'markup_link'      => 'Spam: a link',
        'too_many_links'   => 'Spam: too many links',
        'spam_keyword'     => 'Spam: spam words',
    ];

    /** The actions MODERATION_DEPARTMENTS take through moderate(); remove goes through moderateRemove() */
    private const MODERATE_ACTIONS = ['approve', 'flag', 'block', 'unblock'];

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
            $row['artifacts_to_review'] = (int) ($row['artifacts_to_review'] ?? 0);
            $row['open_reports']        = (int) ($row['open_reports'] ?? 0);
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
            'history'        => $tab('v1/staff/businesses/moderation'),
            'reports'        => $tab('v1/staff/businesses/reports'),
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
            'currency'     => (string) ($business['currency_code'] ?? ''),
            'detail'       => $detail,
            'businessSlug' => $slug,
        ]);
    }

    /**
     * Approve, flag, block or unblock a business, one of its products or services, or a variant: the form of the
     * moderation dialog (action, business_slug, subject, public_id, reason_code, client_message, internal_note)
     * forwarded to LINK_INTERNAL_API v1/staff/moderation/{action}; MODERATION_DEPARTMENTS only (Config\Routes).
     * JSON { status, message, errors, data } with the API's status code
     * @return ResponseInterface
     */
    public function moderate(): ResponseInterface
    {
        $action = (string) $this->request->getPost('action');
        if (! in_array($action, self::MODERATE_ACTIONS, true)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => 'Choose what to do.']);
        }
        return $this->forwardModeration($action);
    }

    /**
     * Remove a business, a product or service, or a variant for good: as moderate(), for MODERATION_REMOVE_DEPARTMENTS only
     * @return ResponseInterface
     */
    public function moderateRemove(): ResponseInterface
    {
        return $this->forwardModeration('remove');
    }

    /**
     * Mark a mart visitor's report new, filtered as spam, dismissed or actioned, with a note: the form of the report
     * dialog (public_id, report_status, staff_note) forwarded to LINK_INTERNAL_API v1/staff/reports/resolve;
     * MODERATION_DEPARTMENTS only (Config\Routes). JSON { status, message, errors, data } with the API's status code
     * @return ResponseInterface
     */
    public function resolveReport(): ResponseInterface
    {
        $payload = [];
        foreach (['public_id', 'report_status', 'staff_note'] as $field) {
            $payload[$field] = trim((string) $this->request->getPost($field));
        }
        [$code, $body] = $this->callApi('v1/staff/reports/resolve', $payload);

        return $this->response->setStatusCode($code)->setJSON([
            'status'  => (string) ($body['status'] ?? 'error'),
            'message' => (string) ($body['message'] ?? 'Could not save. Please try again.'),
            'errors'  => (array) ($body['errors'] ?? []),
            'data'    => $body['data'] ?? null,
        ]);
    }

    /**
     * The buttons of the actions the signed-in staff member may take on a subject as it stands, for the moderation dialog
     * (admin_moderation_modal); none for a removed one
     * @param string      $subject           business, artifact or variant
     * @param string      $publicId          Of the artifact or variant; '' for the business
     * @param string      $label             What the dialog says it acts on
     * @param string|null $reviewStatus      Null for a variant
     * @param string      $moderationStatus
     * @param bool        $small
     * @return string
     */
    public static function moderationButtons(string $subject, string $publicId, string $label, ?string $reviewStatus, string $moderationStatus, bool $small = false): string
    {
        helper('auth');
        if ('R' === $moderationStatus || ! inDepartment(MODERATION_DEPARTMENTS)) {
            return '';
        }
        $buttons = [];
        if ('B' === $moderationStatus) {
            $buttons['unblock'] = ['Unblock', 'btn-outline-primary', 'bi-unlock'];
        } else {
            if (null !== $reviewStatus && 'A' !== $reviewStatus) {
                $buttons['approve'] = ['Approve', 'btn-outline-success', 'bi-check-circle'];
            }
            if (null !== $reviewStatus && 'F' !== $reviewStatus) {
                $buttons['flag'] = ['Flag', 'btn-outline-warning', 'bi-flag'];
            }
            $buttons['block'] = ['Block', 'btn-outline-danger', 'bi-slash-circle'];
        }
        if (inDepartment(MODERATION_REMOVE_DEPARTMENTS)) {
            $buttons['remove'] = ['Remove', 'btn-outline-dark', 'bi-trash'];
        }
        $html = '';
        foreach ($buttons as $action => [$text, $class, $icon]) {
            $html .= '<button type="button" class="btn ' . ($small ? 'btn-sm ' : '') . $class . ' me-1 mb-1" data-moderate="' . $action . '"'
                . ' data-subject="' . esc($subject, 'attr') . '" data-public-id="' . esc($publicId, 'attr') . '" data-label="' . esc($label, 'attr') . '">'
                . '<i class="bi ' . $icon . ' me-1"></i>' . $text . '</button>';
        }
        return $html;
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
                self::column('review_status', 'Review', ['type' => 'badge', 'badges' => self::REVIEW_STATUSES]),
                self::column('moderation_status', 'Moderation', ['type' => 'badge', 'badges' => self::MODERATION_STATUSES]),
                self::column('artifacts_to_review', 'Items to Re-review', ['type' => 'number', 'class' => 'text-end']),
                self::column('open_reports', 'Open Reports', ['type' => 'number', 'class' => 'text-end']),
                self::column('created_at', 'Created', ['type' => 'datetime']),
            ],
            'filters'     => [
                ['name' => 'is_business_open', 'type' => 'select', 'label' => 'Marketplace', 'options' => $labels(self::OPEN_STATUSES)],
                ['name' => 'wht_status', 'type' => 'select', 'label' => 'Withholding', 'options' => $labels(ClientBilling::WHT_STATUSES)],
                ['name' => 'subscription_status', 'type' => 'select', 'label' => 'Subscription', 'options' => $labels(self::subscriptionStatuses())],
                ['name' => 'review_status', 'type' => 'select', 'label' => 'Review', 'options' => $labels(self::REVIEW_STATUSES)],
                ['name' => 'moderation_status', 'type' => 'select', 'label' => 'Moderation', 'options' => $labels(self::MODERATION_STATUSES)],
                ['name' => 'awaiting_review', 'type' => 'select', 'label' => 'Awaiting Re-review', 'options' => ['Y' => 'The business or any of its items']],
                ['name' => 'open_reports', 'type' => 'select', 'label' => 'Open Reports', 'options' => ['Y' => 'A visitor report awaits review']],
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
                self::column('review_status', 'Review', ['type' => 'badge', 'badges' => self::REVIEW_STATUSES]),
                self::column('moderation_status', 'Moderation', ['type' => 'badge', 'badges' => self::MODERATION_STATUSES]),
                self::column('variant_count', 'Variants', ['type' => 'number', 'class' => 'text-end']),
                self::column('price_active_lowest', 'From', ['type' => 'number', 'decimals' => 2, 'class' => 'text-end']),
                self::column('rating_average', 'Rating', ['type' => 'number', 'decimals' => 2, 'class' => 'text-end']),
                self::column('updated_at', 'Updated', ['type' => 'datetime']),
            ],
            'filters'     => [
                ['name' => 'artifact_type', 'type' => 'select', 'label' => 'Type', 'options' => $labels(self::ARTIFACT_TYPES)],
                ['name' => 'artifact_status', 'type' => 'select', 'label' => 'Status', 'options' => $labels(self::ARTIFACT_STATUSES)],
                ['name' => 'review_status', 'type' => 'select', 'label' => 'Review', 'options' => $labels(self::REVIEW_STATUSES)],
                ['name' => 'moderation_status', 'type' => 'select', 'label' => 'Moderation', 'options' => $labels(self::MODERATION_STATUSES)],
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
     * moderate() and moderateRemove()
     * @param string $action
     * @return ResponseInterface
     */
    private function forwardModeration(string $action): ResponseInterface
    {
        $payload = [];
        foreach (['business_slug', 'subject', 'public_id', 'reason_code', 'client_message', 'internal_note'] as $field) {
            $payload[$field] = trim((string) $this->request->getPost($field));
        }
        [$code, $body] = $this->callApi('v1/staff/moderation/' . $action, $payload);

        return $this->response->setStatusCode($code)->setJSON([
            'status'  => (string) ($body['status'] ?? 'error'),
            'message' => (string) ($body['message'] ?? 'Could not save. Please try again.'),
            'errors'  => (array) ($body['errors'] ?? []),
            'data'    => $body['data'] ?? null,
        ]);
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
