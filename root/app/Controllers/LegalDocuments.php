<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;
use Throwable;

/**
 * The Terms of Service and Privacy Policy, behind the auth and department filters: the versions and who accepted
 * them for LEGAL_DEPARTMENTS, publishing and cancelling a version for the Executive Office only. Everything is read
 * and saved through LINK_INTERNAL_API v1/staff/legal/*, which checks the departments again.
 * A version is in the URL by its public_id. It cannot be edited once published: a change is a new version, and only
 * one that has not taken effect yet can be cancelled.
 */
class LegalDocuments extends BaseController
{
    /** Timezone the form takes its time in, unless another is picked */
    private const DEFAULT_TIMEZONE = 'Asia/Bangkok';

    private const TYPES        = ['T' => 'Terms of Service', 'P' => 'Privacy Policy'];
    // who a version is for; each audience's documents are a series of their own
    private const AUDIENCES    = ['B' => 'Client app (businesses)', 'M' => 'Marketplace (customers)'];
    private const REACCEPTANCE = ['Y' => 'Yes: everyone accepts it again', 'N' => 'No: a minor change, people are only told'];
    private const STATES       = [
        'S' => ['label' => 'Scheduled', 'class' => 'bg-info'],
        'C' => ['label' => 'Current', 'class' => 'bg-success'],
        'E' => ['label' => 'Ended', 'class' => 'bg-secondary'],
    ];
    private const CONTEXTS = [
        'S' => ['label' => 'Sign-up', 'class' => 'bg-primary'],
        'I' => ['label' => 'Invitation sign-up', 'class' => 'bg-info'],
        'R' => ['label' => 'Accepted again', 'class' => 'bg-secondary'],
    ];

    /** The languages a version can have, as the Client app supports them; en-US is required */
    private const LOCALES = [
        'en-US' => 'English (en-US)',
        'th'    => 'Thai (th)',
        'id'    => 'Indonesian (id)',
        'ms'    => 'Malay (ms)',
        'ja'    => 'Japanese (ja)',
        'zh-SG' => 'Chinese, Simplified (zh-SG)',
        'zh-TW' => 'Chinese, Traditional (zh-TW)',
    ];

    // ---- versions ----

    /**
     * Every version, newest first
     * @return string
     */
    public function versions(): string
    {
        return view('generic_list', [
            'page_title' => 'Legal Documents',
            'slug'       => 'legal-versions',
            'listConfig' => $this->versionsListConfig(),
        ]);
    }

    /**
     * One page of versions; the API sends them all in its own order, which the list keeps
     * @return ResponseInterface
     */
    public function versionsList(): ResponseInterface
    {
        $config = $this->versionsListConfig();
        $params = lb_request($this->request, $config);
        try {
            $all = array_map(static function (array $row): array {
                $row['document_label'] = self::TYPES[$row['document_type']] ?? $row['document_type'];
                $row['audience_label'] = self::AUDIENCES[$row['audience']] ?? $row['audience'];
                $row['locales_label']  = implode(', ', (array) ($row['locales'] ?? []));
                return $row;
            }, $this->versionRows());
        } catch (RuntimeException $e) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $e->getMessage()]);
        }

        $filtered = array_values(array_filter($all, static function (array $row) use ($params): bool {
            foreach ($params['filters'] as $item) {
                if ((string) ($row[$item['def']['name']] ?? '') !== (string) $item['value']) {
                    return false;
                }
            }
            $search = $params['search'];
            return '' === $search || false !== mb_stripos($row['version_label'] . ' ' . $row['document_label'] . ' ' . $row['audience_label'] . ' ' . $row['locales_label'], $search);
        }));

        return $this->response->setJSON(lb_response($params['draw'], count($all), count($filtered),
            lb_rows(array_slice($filtered, $params['start'], $params['length']), $config)));
    }

    /**
     * The form to publish a new version (Executive Office only); ?type=P opens it on the Privacy Policy, ?audience=M
     * on the marketplace's
     * @return string
     */
    public function create(): string
    {
        $type     = (string) $this->request->getGet('type');
        $audience = (string) $this->request->getGet('audience');
        $fields   = [
            ['name' => 'audience', 'type' => 'select', 'label' => 'For', 'rules' => 'required', 'options' => self::AUDIENCES, 'default' => 'B',
             'help' => 'The Client app\'s documents are accepted by businesses, the marketplace\'s by its customers; each has its own versions'],
            ['name' => 'document_type', 'type' => 'select', 'label' => 'Document', 'rules' => 'required', 'options' => self::TYPES],
            ['name' => 'version_label', 'type' => 'text', 'label' => 'Version', 'rules' => 'required|regex_match[/^[0-9A-Za-z][0-9A-Za-z._-]{0,31}$/]',
             'maxlength' => 32, 'default' => gmdate('Y-m-d'), 'help' => 'What people see, such as 2026-12-01; each version of a document has its own'],
            ['name' => 'effective_from', 'type' => 'datetime-local', 'label' => 'In Effect From', 'optional' => true,
             'help' => 'Leave empty to take effect now. Otherwise not in the past, and after every version of this document set so far.'],
            self::timezoneField(),
            ['name' => 'requires_reacceptance', 'type' => 'select', 'label' => 'Ask Everyone Again?', 'rules' => 'required', 'options' => self::REACCEPTANCE,
             'default' => 'Y', 'help' => 'Yes for a material change: clients cannot use the Client app until they accept it. People who never accepted this document are always asked.'],
        ];
        foreach (self::LOCALES as $locale => $language) {
            $required = 'en-US' === $locale;
            $fields[] = ['type' => 'heading', 'label' => $language];
            $fields[] = ['name' => 'body_' . $locale, 'type' => 'textarea', 'label' => 'Text (Markdown)', 'rows' => $required ? 16 : 8,
                         'rules' => $required ? 'required' : '', 'optional' => ! $required,
                         'help' => $required ? 'Required: shown wherever a language has no text of its own' : 'Leave empty to show the English text in this language'];
            $fields[] = ['name' => 'summary_' . $locale, 'type' => 'textarea', 'label' => 'What Changed', 'rows' => 2, 'optional' => true, 'maxlength' => 2000,
                         'help' => 'Shown above the text on the page where clients accept the new version'];
        }

        $config = [
            'form_id'  => 'legal-version-form',
            'title'    => 'Publish a New Version',
            'subtitle' => 'A version cannot be changed once published; publish a newer one instead',
            'mode'     => 'create',
            'id_field' => 'public_id',
            'id'       => '',
            'method'   => 'POST',
            'locale'   => LOCALE,
            'urls'     => ['submit' => base_url('admin/legal/versions/save'), 'list' => base_url('admin/legal/versions')],
            'labels'   => ['save' => 'Publish'],
            'values'   => array_filter(['document_type' => isset(self::TYPES[$type]) ? $type : null, 'audience' => isset(self::AUDIENCES[$audience]) ? $audience : null]),
            'fields'   => $fields,
        ];

        return view('generic_form', [
            'page_title'  => 'New Version',
            'slug'        => 'legal-versions',
            'breadcrumbs' => [base_url('admin/legal/versions') => 'Legal Documents'],
            'formConfig'  => $config,
        ]);
    }

    /**
     * Publish a version through LINK_INTERNAL_API v1/staff/legal/version/create
     * @return ResponseInterface
     */
    public function save(): ResponseInterface
    {
        $post  = (array) $this->request->getPost();
        $texts = [];
        foreach (array_keys(self::LOCALES) as $locale) {
            $texts[$locale] = [
                'body'           => (string) ($post['body_' . $locale] ?? ''),
                'change_summary' => (string) ($post['summary_' . $locale] ?? ''),
            ];
        }
        [$code, $body] = $this->callApi('v1/staff/legal/version/create', [
            'audience'              => trim((string) ($post['audience'] ?? '')),
            'document_type'         => trim((string) ($post['document_type'] ?? '')),
            'version_label'         => trim((string) ($post['version_label'] ?? '')),
            'effective_from'        => self::toOffsetTime($post['effective_from'] ?? null, self::timezoneOf($post['timezone'] ?? null)),
            'requires_reacceptance' => trim((string) ($post['requires_reacceptance'] ?? '')),
            'texts'                 => $texts,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // the API names a text's errors texts.{locale}.body; the form names them body_{locale}
            $errors = [];
            foreach ((array) ($body['errors'] ?? []) as $field => $message) {
                $errors[preg_replace(['/^texts\.([^.]+)\.change_summary$/', '/^texts\.([^.]+)(\.body)?$/'], ['summary_$1', 'body_$1'], (string) $field)] = $message;
            }
            return $this->saveFailed($code, ['errors' => $errors ?: null] + $body, 'Could not publish the version. Please try again.');
        }

        return $this->saved($body, 'admin/legal/versions/view/' . rawurlencode((string) ($body['data']['public_id'] ?? '')));
    }

    /**
     * One version with all its texts; a scheduled one can be cancelled by the Executive Office (the Delete button)
     * @param string $publicId
     * @return string
     */
    public function view(string $publicId): string
    {
        [$code, $body] = $this->callApi('v1/staff/legal/version', ['public_id' => $publicId]);
        if ($code === 404) {
            throw PageNotFoundException::forPageNotFound('Version not found.');
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the version. Please try again.');
        }
        $version = (array) $body['data']['version'];
        $state   = self::STATES[$version['state']] ?? ['label' => $version['state'], 'class' => 'bg-secondary'];

        $fields = [
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('For', esc(self::AUDIENCES[$version['audience']] ?? $version['audience']))],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Document', esc(self::TYPES[$version['document_type']] ?? $version['document_type']))],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Version', esc($version['version_label']))],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('In Effect From', self::utcTime($version['effective_from']))],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('State', '<span class="badge ' . esc($state['class'], 'attr') . '">' . esc($state['label']) . '</span>')],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Ask Everyone Again?', esc(self::REACCEPTANCE[$version['requires_reacceptance']] ?? $version['requires_reacceptance']))],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Accepted By',
                '<a href="' . esc(base_url('admin/legal/acceptances') . '?version=' . rawurlencode($publicId), 'attr') . '"><i class="bi bi-people"></i> '
                . esc(number_format((int) $version['acceptances'])) . ' ' . (1 === (int) $version['acceptances'] ? 'person' : 'people') . '</a>')],
            ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Published', self::utcTime($version['created_at'] ?? null)
                . ('' !== (string) ($version['created_by'] ?? '') ? ' by ' . esc($version['created_by']) : ''))],
        ];
        foreach ((array) ($version['texts'] ?? []) as $text) {
            $locale   = (string) $text['locale'];
            $fields[] = ['type' => 'heading', 'label' => self::LOCALES[$locale] ?? $locale];
            if ('' !== (string) ($text['change_summary'] ?? '')) {
                $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('What Changed', nl2br(esc($text['change_summary'])))];
            }
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Text',
                '<pre class="border rounded p-2 small bg-body-tertiary mb-1" style="max-height: 24rem; overflow-y: auto; white-space: pre-wrap;">' . esc($text['body']) . '</pre>'
                . '<span class="small text-secondary font-monospace">SHA-256 ' . esc($text['body_sha256']) . '</span>')];
        }

        $config = [
            'form_id'  => 'legal-version-form',
            'title'    => 'Version Detail',
            'subtitle' => 'A version cannot be changed once published; publish a newer one instead',
            'mode'     => 'view',
            'editable' => false,
            'id_field' => 'public_id',
            'id'       => $publicId,
            'method'   => 'POST',
            'locale'   => LOCALE,
            'urls'     => ['edit' => '#', 'list' => base_url('admin/legal/versions')],
            'values'   => [],
            'fields'   => $fields,
        ];
        if ('S' === $version['state'] && inDepartment(EXECUTIVE_DEPARTMENT)) {
            // only a version that has not taken effect yet can be taken back
            $config['urls']['delete'] = base_url('admin/legal/versions/cancel');
            $config['labels']         = ['delete' => 'Cancel This Version'];
            $config['messages']       = ['confirmDelete' => 'Cancel this version? It will not take effect, and the current one stays.'];
        }

        return view('generic_form', [
            'page_title'  => self::versionName($version),
            'slug'        => 'legal-versions',
            'breadcrumbs' => [base_url('admin/legal/versions') => 'Legal Documents'],
            'formConfig'  => $config,
        ]);
    }

    /**
     * Cancel a scheduled version through LINK_INTERNAL_API v1/staff/legal/version/cancel
     * @return ResponseInterface
     */
    public function cancel(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/legal/version/cancel', ['public_id' => trim((string) $this->request->getPost('public_id'))]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not cancel the version. Please try again.');
        }

        return $this->saved($body, 'admin/legal/versions');
    }

    // ---- acceptances ----

    /**
     * Who accepted which version, newest first; ?version= opens it on one version, ?search= on an address
     * @return string
     */
    public function acceptances(): string
    {
        try {
            $options = $this->versionOptions();
        } catch (RuntimeException) {
            $options = [];
        }
        $config  = $this->acceptancesListConfig($options);
        $version = trim((string) $this->request->getGet('version'));
        if ('' !== $version) {
            $config['values'] = ['version' => $version];
        }
        $search = mb_substr(trim((string) $this->request->getGet('search')), 0, 128);
        if ('' !== $search) {
            $config['dt_options']['search'] = ['search' => $search];
        }

        return view('generic_list', [
            'page_title' => 'Acceptances',
            'slug'       => 'legal-acceptances',
            'listConfig' => $config,
        ]);
    }

    /**
     * One page of acceptances: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/legal/acceptances
     * @return ResponseInterface
     */
    public function acceptancesList(): ResponseInterface
    {
        $config  = $this->acceptancesListConfig([]);
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi('v1/staff/legal/acceptances', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => $body['message'] ?? 'Could not load the acceptances. Please try again.', 'csrf_hash' => csrf_hash()]);
        }

        $result = (array) ($body['data'] ?? []);
        $rows   = array_map(static fn (array $row): array => [
            'id'          => $row['id'],
            'accepted_at' => (string) ($row['accepted_at'] ?? ''),
            'document'    => '<a href="' . esc(base_url('admin/legal/versions/view/' . rawurlencode((string) $row['version_public_id'])), 'attr') . '">'
                . esc(self::versionName($row)) . '</a>',
            'who'         => esc((string) ($row['who_name'] ?? '')) . '<br><span class="small text-secondary">' . esc((string) ($row['who_email'] ?? ''))
                . ('customer' === ($row['who_type'] ?? '') ? ' · marketplace customer' : '') . '</span>',
            'context'     => (string) ($row['context'] ?? ''),
            'locale'      => (string) ($row['locale'] ?? ''),
            'ip_address'  => (string) ($row['ip_address'] ?? ''),
            'user_agent'  => (string) ($row['user_agent'] ?? ''),
        ], (array) ($result['rows'] ?? []));

        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($result['records_total'] ?? 0),
            (int) ($result['records_filtered'] ?? 0),
            $rows
        ) + ['csrf_hash' => csrf_hash()]);
    }

    // ---- configuration ----

    private function versionsListConfig(): array
    {
        $fixed  = ['orderable' => false];
        $config = [
            'table_id'    => 'legalVersionTable',
            'title'       => 'Legal Documents',
            'subtitle'    => 'Every version of the Terms of Service and Privacy Policy, newest first; a new version ends the one before it',
            'id_field'    => 'public_id',
            'urls'        => [
                'data' => base_url('admin/legal/versions'),
                'edit' => base_url('admin/legal/versions/view/{public_id}'),
            ],
            'labels'      => ['add' => 'Publish a New Version'],
            'columns'     => [
                self::column('audience_label', 'For', $fixed),
                self::column('document_label', 'Document', $fixed),
                self::column('version_label', 'Version', $fixed),
                self::column('effective_from', 'In Effect From', $fixed + ['type' => 'datetime', 'searchable' => false]),
                self::column('state', 'State', $fixed + ['type' => 'badge', 'badges' => self::STATES, 'searchable' => false]),
                self::column('requires_reacceptance', 'Ask Again', $fixed + ['type' => 'badge', 'searchable' => false, 'badges' => [
                    'Y' => ['label' => 'Yes', 'class' => 'bg-warning text-dark'],
                    'N' => ['label' => 'No', 'class' => 'bg-secondary'],
                ]]),
                self::column('locales_label', 'Languages', $fixed),
                self::column('acceptances', 'Accepted By', $fixed + ['class' => 'text-end', 'searchable' => false]),
            ],
            'filters'     => [
                ['name' => 'audience', 'type' => 'select', 'label' => 'For', 'options' => self::AUDIENCES],
                ['name' => 'document_type', 'type' => 'select', 'label' => 'Document', 'options' => self::TYPES],
                ['name' => 'state', 'type' => 'select', 'label' => 'State', 'options' => array_map(static fn (array $b): string => $b['label'], self::STATES)],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
        if (inDepartment(EXECUTIVE_DEPARTMENT)) {
            $config['urls']['create'] = base_url('admin/legal/versions/create');
        }
        return $config;
    }

    /**
     * @param array<string, string> $versions public_id => label, for the version filter
     * @return array
     */
    private function acceptancesListConfig(array $versions): array
    {
        return [
            'table_id'    => 'legalAcceptanceTable',
            'title'       => 'Acceptances',
            'subtitle'    => 'Who accepted which version, newest first. Search finds an email address, a name or an IP address.',
            'id_field'    => 'id',
            'editable'    => false,
            'urls'        => ['data' => base_url('admin/legal/acceptances')],
            'columns'     => [
                self::column('accepted_at', 'Accepted At', ['type' => 'datetime', 'class' => 'text-nowrap']),
                self::column('document', 'Document', ['type' => 'html']),
                self::column('who', 'Who', ['type' => 'html']),
                self::column('context', 'How', ['type' => 'badge', 'badges' => self::CONTEXTS]),
                self::column('locale', 'Language', ['class' => 'text-nowrap']),
                self::column('ip_address', 'IP Address', ['class' => 'text-nowrap']),
                self::column('user_agent', 'Browser', ['width' => '20%', 'class' => 'small text-break']),
            ],
            'filters'     => [
                ['name' => 'audience', 'type' => 'select', 'label' => 'For', 'options' => self::AUDIENCES, 'col' => 3],
                ['name' => 'version', 'type' => 'select', 'label' => 'Version', 'options' => $versions, 'col' => 5],
            ],
            'order'       => [],
            'page_length' => 25,
            'dt_options'  => ['ordering' => false],
        ];
    }

    /**
     * Every version, as public_id => versionName(), for the filter
     * @return array<string, string>
     * @throws RuntimeException
     */
    private function versionOptions(): array
    {
        $options = [];
        foreach ($this->versionRows() as $row) {
            $options[$row['public_id']] = self::versionName($row);
        }
        return $options;
    }

    /**
     * "Terms of Service 2026-12-01" for the Client app's, "Marketplace Terms of Service 2026-12-01" for the marketplace's
     * @param array $row With audience, document_type and version_label
     * @return string
     */
    private static function versionName(array $row): string
    {
        return ('M' === ($row['audience'] ?? 'B') ? 'Marketplace ' : '') . (self::TYPES[$row['document_type']] ?? $row['document_type']) . ' ' . $row['version_label'];
    }

    /**
     * Every version, from LINK_INTERNAL_API v1/staff/legal/versions
     * @return list<array>
     * @throws RuntimeException With the message to show
     */
    private function versionRows(): array
    {
        [$code, $body] = $this->callApi('v1/staff/legal/versions', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the versions. Please try again.');
        }
        return (array) ($body['data']['rows'] ?? []);
    }

    // ---- helpers (as in App\Controllers\Plans) ----

    private static function column(string $data, string $title, array $extra = []): array
    {
        return $extra + ['data' => $data, 'db' => $data, 'title' => $title];
    }

    private static function timezoneField(): array
    {
        $options = [];
        foreach (listSupportedTimezones() as $zones) {
            foreach ($zones as $zone => $label) {
                $options[$zone] = $zone . ' — ' . $label;
            }
        }
        $options['UTC'] = 'UTC';
        return ['name' => 'timezone', 'type' => 'select', 'label' => 'Timezone', 'rules' => 'required', 'options' => $options,
                'default' => self::DEFAULT_TIMEZONE, 'help' => 'The timezone of the time above'];
    }

    private static function timezoneOf(mixed $value): string
    {
        $value = is_string($value) ? $value : '';
        return array_key_exists($value, self::timezoneField()['options']) ? $value : self::DEFAULT_TIMEZONE;
    }

    /**
     * A datetime-local value in $timezone as the API takes it, with its offset; empty stays empty (now)
     */
    private static function toOffsetTime(mixed $local, string $timezone): string
    {
        $local = trim(is_string($local) ? $local : '');
        if ('' === $local || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $local)) {
            return $local;
        }
        try {
            return (new DateTimeImmutable($local, new DateTimeZone($timezone)))->format('Y-m-d\TH:i:sP');
        } catch (Exception) {
            return $local;
        }
    }

    private static function infoRow(string $label, string $html): string
    {
        return '<div class="row mb-3"><div class="col-sm-4 text-sm-end">' . esc($label) . '</div><div class="col-sm-8">'
            . ('' === $html ? '<span class="text-muted">&mdash;</span>' : $html) . '</div></div>';
    }

    private static function utcTime(?string $utc): string
    {
        return null === $utc || '' === $utc ? '' : '<time data-utc datetime="' . esc($utc, 'attr') . '">' . esc($utc) . ' UTC</time>';
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
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

    private function saveFailed(int $code, array $body, string $fallback): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => $body['errors'] ?? null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    private function saved(array $body, string $path): ResponseInterface
    {
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Saved.',
            'redirect'  => base_url($path),
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
