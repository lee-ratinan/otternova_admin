<?php

namespace App\Controllers;

use App\Models\AuthAccountModel;
use App\Models\AuthMasterModel;
use App\Models\BusinessTypeModel;
use App\Models\ClientMasterModel;
use App\Models\StaffMasterModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CURLFile;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class Admin extends BaseController
{

    /**
     * Profile fields the account holder maintains on their own profile: shown disabled here and never forwarded on save
     */
    private const STAFF_SELF_MANAGED  = ['preferred_name', 'gender', 'date_of_birth', 'nationality_code',
        'address_line_1', 'address_line_2', 'address_city', 'subdivision_code', 'postal_code', 'country_code',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_telephone',
        'preferred_language', 'status_message', 'bio'];
    private const CLIENT_SELF_MANAGED = ['display_name', 'gender', 'date_of_birth', 'nationality_code',
        'preferred_language', 'status_message', 'status_emoji', 'bio', 'marketing_opt_in'];

    /**
     * Account fields the form cannot change (password_expiry is set by the password flow)
     * and the select values only the system sets (telephone OTP is not available yet; pending/blocked are automatic)
     */
    private const ACCOUNT_SYSTEM_MANAGED = ['password_expiry'];
    private const OTP_MODE_DISABLED      = [AuthMasterModel::OTP_TELEPHONE];
    private const STATUS_DISABLED        = [AuthMasterModel::STATUS_PENDING, AuthMasterModel::STATUS_BLOCKED];

    /**
     * Fields the signed-in staff member cannot change on their own profile: shown disabled and never forwarded on save
     */
    private const OWN_ACCOUNT_LOCKED = ['email_address', 'password_expiry', 'account_status', 'account_type', 'admin_notes'];
    private const OWN_STAFF_LOCKED   = ['employee_code', 'job_title', 'department_id', 'manager_staff_id',
        'employment_type', 'employment_status', 'employment_start_date', 'probation_end_date', 'employment_end_date',
        'work_country_code', 'work_email_address', 'work_telephone_number',
        'legal_first_name', 'legal_middle_name', 'legal_last_name', 'national_id_type', 'national_id_number', 'tax_id_number',
        'hr_notes'];

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

    /**
     * Table Builder configuration of the account list, shared by the page and its JSON endpoint
     * @return array
     */
    private function accountsListConfig(): array
    {
        $authModel = new AuthMasterModel();
        $fields    = $authModel->getConfiguration();
        unset($fields['uid'], $fields['admin_notes'], $fields['avatar_path']); // avatar_path: an image path, not a column to read
        $columns   = AuthMasterModel::toColumns($fields);
        // the select fields double as filters, querying the same column the table orders/searches on
        $filters   = AuthMasterModel::toFilters($fields, ['otp_mode', 'account_status', 'account_type']);
        return [
            'table_id'    => 'accountTable',
            'title'       => 'All User Accounts',
            'subtitle'    => 'This table includes staff and client accounts',
            'id_field'    => 'uid',
            'urls'        => [
                'data'   => base_url('admin/accounts'),
                'create' => base_url('admin/accounts/create'),
                'edit'   => base_url('admin/accounts/edit/{uid}'),
            ],
            'labels'      => ['add' => 'Add Account'],
            'columns'     => $columns,
            'filters'     => $filters,
            'order'       => [['email_address', 'asc']],
            'page_length' => 25,
        ];
    }

    /**
     * Dashboard
     * @return string
     */
    public function dashboard(): string
    {
        $data = [
            'page_title' => 'Dashboard',
            'slug'       => 'dashboard',
        ];
        return view('admin_dashboard', $data);
    }

    public function accounts(): string
    {
        $data = [
            'page_title' => 'Account',
            'slug'       => 'accounts',
            'listConfig' => $this->accountsListConfig(),
        ];
        return view('generic_list', $data);
    }

    /**
     * List accounts accordingly: the DataTables request is validated against the list configuration,
     * then forwarded to LINK_INTERNAL_API v1/accounts
     * @return ResponseInterface
     */
    public function accountsList(): ResponseInterface
    {
        $config = $this->accountsListConfig();
        $params = lb_request($this->request, $config);

        // the API works with field names, not the DB columns the Table Builder uses
        $fieldOf = array_column($params['columns'], 'data', 'db');
        $order   = [];
        foreach ($params['order'] as [$column, $dir]) {
            $order[] = ['field' => $fieldOf[$column], 'dir' => strtolower($dir)];
        }
        $filters = [];
        foreach ($params['filters'] as $item) {
            $value = (string) $item['value'];
            // select filters only accept the configured values
            if (in_array($value, array_column($item['def']['options'], 'value'), true)) {
                $filters[$item['def']['name']] = $value;
            }
        }

        [$code, $body] = $this->callApi('v1/staff/accounts', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'order'   => $order,
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // the Table Builder shows 'error' as a toast
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error' => $body['message'] ?? 'Could not load the accounts. Please try again.',
            ]);
        }

        $result = $body['data'] ?? [];
        $rows   = array_map(static function (array $row): array {
            $row['telephone_number'] = empty($row['telephone_number']) ? '' : formatPhoneNumber($row['telephone_number']);
            $row['password_expiry']  = empty($row['password_expiry']) ? '' : formatDate($row['password_expiry'], LOCALE);
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
     * Account form: 'create' opens an empty staff account, any other value is the uid of the account to view
     * (the Edit button switches the same page to edit mode)
     * @param string $uid
     * @return string
     */
    public function accountsEdit(string $uid): string
    {
        $mode   = 'create' === $uid ? 'create' : 'view';
        $type   = AuthMasterModel::TYPE_STAFF; // new accounts are always staff
        $values = [];
        if ('view' === $mode) {
            [$code, $body] = $this->callApi('v1/staff/account', ['uid' => $uid]);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                throw $code === 404
                    ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                    : new RuntimeException($body['message'] ?? 'Could not load the account.');
            }
            // keyed by field name, as the form looks values up by the field's 'name'
            $values = (array) ($body['data']['account'] ?? []) + (array) ($body['data']['profile'] ?? []);
            $type   = (string) ($values['account_type'] ?? '');
        }
        // staff profiles pick their department and line manager from lists the API provides
        $options    = AuthMasterModel::TYPE_STAFF === $type ? $this->staffFormOptions('view' === $mode ? $uid : null) : [];
        $sections   = $this->accountFormSections($type, $options, $values['avatar_path'] ?? null);
        $formConfig = [
            'form_id'  => 'account-form',
            'title'    => ['create' => 'New Account', 'view' => 'Account Detail'][$mode],
            'subtitle' => 'Fields marked with * are required',
            'mode'     => $mode,
            'id_field' => 'uid',
            'id'       => $values['uid'] ?? '',
            'method'   => 'POST',
            'locale'   => LOCALE,
            'urls'     => [
                'submit' => base_url('admin/accounts/save'),
                'delete' => base_url('admin/accounts/delete'),
                'edit'   => base_url('admin/accounts/edit/' . rawurlencode($uid)),
                'list'   => base_url('admin/accounts'),
            ],
            'values'   => $values,
            'fields'   => array_merge(...array_column($sections, 'fields')),
        ];
        $data       = [
            'page_title'  => $values['full_name'] ?? 'New Account',
            'slug'        => 'account-edit',
            'breadcrumbs' => [
                base_url('admin/accounts') => 'Accounts',
            ],
            'mode'        => $mode,
            'formConfig'  => $formConfig,
            'sections'    => $sections,
        ];
        return view('admin_accounts_edit', $data);
    }

    /**
     * Save an account: an empty uid creates a staff account, otherwise the account with that uid is updated.
     * Forwarded to LINK_INTERNAL_API v1/staff/account/update, which validates and decides the profile table
     * from the stored account_type
     * @return ResponseInterface
     */
    public function accountsEditPost(): ResponseInterface
    {
        $post  = (array) $this->request->getPost();
        $uid   = trim((string) ($post['uid'] ?? ''));
        $isNew = '' === $uid;

        // only the fields the form can show are forwarded; the form holds the profile of one account type only
        // read-only fields (avatar_path, set by the avatar upload) are never sent
        $authModel   = new AuthMasterModel();
        $accountKeys = array_diff(array_keys($authModel->getConfiguration()), AuthAccountModel::FORM_ACCOUNT_READONLY, self::ACCOUNT_SYSTEM_MANAGED, $isNew
            ? ['uid', 'account_type']
            : ['uid', 'account_type', 'email_address']); // fixed once the account exists
        $profileKeys = array_diff(array_unique(array_merge(
            array_keys((new StaffMasterModel())->getConfiguration()),
            array_keys((new ClientMasterModel())->getConfiguration())
        )), ['auth_id'], self::STAFF_SELF_MANAGED, self::CLIENT_SELF_MANAGED);
        $account = array_intersect_key($post, array_flip($accountKeys));
        // the disabled select options are set by the system only
        if (in_array((string) ($account['otp_mode'] ?? ''), self::OTP_MODE_DISABLED, true)) {
            unset($account['otp_mode']);
        }
        if (in_array((string) ($account['account_status'] ?? ''), self::STATUS_DISABLED, true)) {
            unset($account['account_status']);
        }
        if ($isNew) {
            $account['account_type'] = AuthMasterModel::TYPE_STAFF;
        }

        [$code, $body] = $this->callApi('v1/staff/account/update', [
            'mode'    => 'admin-edit',
            'uid'     => $isNew ? null : $uid,
            'account' => $account,
            'profile' => array_intersect_key($post, array_flip($profileKeys)),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save the account. Please try again.');
        }

        $savedUid = (string) ($body['data']['uid'] ?? $uid);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Account saved.',
            'redirect'  => base_url('admin/accounts/edit/' . rawurlencode($savedUid)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Form response of a failed v1/staff/account/update call
     * @param int    $code     HTTP status of the API call
     * @param array  $body     decoded API response
     * @param string $fallback message when the API gives none
     * @return ResponseInterface
     */
    private function saveFailed(int $code, array $body, string $fallback): ResponseInterface
    {
        // the API prefixes errors with their group (account.email_address); the form only knows the field name
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $errors[preg_replace('/^.*\./', '', (string) $key)] = $message;
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? $fallback,
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(), // keeps the form's token fresh when CSRF regenerates
        ]);
    }

    /**
     * Delete an account (and its profile) through LINK_INTERNAL_API v1/staff/account/delete
     * @return ResponseInterface
     */
    public function accountsDelete(): ResponseInterface
    {
        $uid = trim((string) $this->request->getPost('uid'));
        if ('' === $uid) {
            return $this->response->setStatusCode(422)->setJSON([
                'success'   => false,
                'message'   => 'No account was selected.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        [$code, $body] = $this->callApi('v1/staff/account/delete', ['uid' => $uid]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? 'Could not delete the account. Please try again.',
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Account deleted.',
            'redirect'  => base_url('admin/accounts'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Cards of the account form: the auth_master fields, then the profile of the account type grouped under headings.
     * Profile fields missing from the groups below are appended to the last group, so new model fields still show.
     * @param string $type    auth_master.account_type
     * @param array  $options staff only: ['departments' => [id => label], 'managers' => [staff id => label]], see staffFormOptions()
     * @param string|null $avatarPath auth_master.avatar_path, shown as a read-only preview (null = default avatar)
     * @param bool   $own     true: the signed-in staff member's own profile, which locks a different set of fields and has no avatar
     * @return array [['title', 'subtitle', 'fields'], ...]
     */
    private function accountFormSections(string $type, array $options = [], ?string $avatarPath = null, bool $own = false): array
    {
        $account = (new AuthMasterModel())->getConfiguration();
        if ($own) {
            // no avatar on the own profile for now, and the admin notes are not shown to the account holder
            unset($account['avatar_path'], $account['admin_notes']);
            $locked = self::OWN_ACCOUNT_LOCKED;
        } else {
            // avatar_path is never edited here (a separate upload sets it): shown as an image without an input, so it is not submitted
            $account['avatar_path'] = [
                'type'    => 'html',
                'show_in' => ['edit', 'view'],
                'html'    => '<div class="row mb-3"><div class="col-sm-4 text-sm-end">' . esc($account['avatar_path']['label']) . '</div>'
                    . '<div class="col-sm-8"><img src="' . esc(avatarUrl($avatarPath), 'attr') . '" alt="" class="rounded-circle" width="64" height="64"></div></div>',
            ];
            $account['email_address']['readonly_in'] = ['edit'];
            $account['account_type']['disabled_in']  = ['create', 'edit']; // create: always staff, edit: fixed
            $account['account_type']['default']      = AuthMasterModel::TYPE_STAFF;
            $locked = self::ACCOUNT_SYSTEM_MANAGED;
        }
        $account['uid']['show_in'] = ['view']; // submitted through the hidden id field instead
        foreach ($locked as $name) {
            $account[$name]['disabled_in'] = ['create', 'edit'];
        }
        // the system-only options stay listed (disabled) so a current value still shows
        $account['otp_mode']['options']       = self::disableOptions($account['otp_mode']['options'], self::OTP_MODE_DISABLED);
        $account['account_status']['options'] = self::disableOptions($account['account_status']['options'], self::STATUS_DISABLED);
        $sections = [['title' => 'Account', 'subtitle' => '', 'fields' => array_values($account)]];

        if (AuthMasterModel::TYPE_STAFF === $type) {
            $title   = 'Staff Profile';
            $profile = (new StaffMasterModel())->getConfiguration();
            $profile['department_id'] = ['type' => 'select', 'options' => $options['departments'] ?? []] + $profile['department_id'];
            $profile['manager_staff_id'] = ['type' => 'select', 'options' => $options['managers'] ?? []] + $profile['manager_staff_id'];
            $groups  = [
                'Employment'        => ['employee_code', 'job_title', 'department_id', 'manager_staff_id', 'employment_type', 'employment_status', 'employment_start_date', 'probation_end_date', 'employment_end_date', 'work_country_code', 'work_email_address', 'work_telephone_number'],
                'Personal'          => ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'preferred_name', 'gender', 'date_of_birth', 'nationality_code', 'national_id_type', 'national_id_number', 'tax_id_number'],
                'Address'           => ['address_line_1', 'address_line_2', 'address_city', 'subdivision_code', 'postal_code', 'country_code'],
                'Emergency Contact' => ['emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_telephone'],
                'Preferences'       => ['preferred_language', 'status_message', 'bio'],
                'Internal'          => ['hr_notes'],
            ];
            $locked  = $own ? self::OWN_STAFF_LOCKED : self::STAFF_SELF_MANAGED;
            if ($own) {
                // HR notes are not shown to the staff member themselves
                unset($groups['Internal'], $profile['hr_notes']);
            }
        } elseif (AuthMasterModel::TYPE_CLIENT === $type) {
            $title   = 'Client Profile';
            $profile = (new ClientMasterModel())->getConfiguration();
            $groups  = [
                'Personal'    => ['legal_first_name', 'legal_middle_name', 'legal_last_name', 'display_name', 'gender', 'date_of_birth', 'nationality_code', 'national_id_type', 'national_id_number'],
                'Preferences' => ['preferred_language', 'status_message', 'status_emoji', 'bio', 'marketing_opt_in'],
                'Internal'    => ['internal_notes'],
            ];
            $locked  = self::CLIENT_SELF_MANAGED;
        } else {
            return $sections;
        }
        unset($profile['auth_id']); // auth_id is set by the API
        // disabled inputs are not submitted, so the save leaves these values as they are
        foreach ($locked as $name) {
            if (isset($profile[$name])) {
                $profile[$name]['disabled_in'] = ['create', 'edit'];
            }
        }

        $fields = [];
        foreach ($groups as $heading => $names) {
            $fields[] = ['type' => 'heading', 'label' => $heading];
            foreach ($names as $name) {
                if (isset($profile[$name])) {
                    $fields[] = $profile[$name];
                    unset($profile[$name]);
                }
            }
        }
        $sections[] = ['title' => $title, 'subtitle' => '', 'fields' => array_merge($fields, array_values($profile))];

        return $sections;
    }

    /**
     * Turn value => label options into the form builder's list form, with the given values disabled
     * @param array $options value => label
     * @param array $disabled values that cannot be picked
     * @return array [['value', 'label', 'disabled'], ...]
     */
    private static function disableOptions(array $options, array $disabled): array
    {
        $out = [];
        foreach ($options as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label, 'disabled' => in_array((string) $value, $disabled, true)];
        }
        return $out;
    }

    /**
     * Select options of the staff profile, from LINK_INTERNAL_API: departments (v1/staff/departments)
     * and line managers (v1/staff/staff-members, i.e. staff accounts that have a staff_master row)
     * @param string|null $exceptUid account being edited, which cannot be its own line manager
     * @return array ['departments' => [id => label], 'managers' => [staff_master.id => label]]
     */
    private function staffFormOptions(?string $exceptUid): array
    {
        [$code, $body] = $this->callApi('v1/staff/departments', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the departments.');
        }
        $departments = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $row) {
            $departments[$row['id']] = $row['department_name'] . ' (' . $row['department_code'] . ')';
        }

        [$code, $body] = $this->callApi('v1/staff/staff-members', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the line managers.');
        }
        $managers = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $row) {
            if ($row['uid'] === $exceptUid) {
                continue;
            }
            $label = $row['full_name'] . ' (' . $row['email_address'] . ')';
            // inactive staff stay selectable so a current manager who has left still shows by name
            if (($row['account_status'] ?? AuthMasterModel::STATUS_ACTIVE) !== AuthMasterModel::STATUS_ACTIVE) {
                $label .= ' - ' . lang('AuthMaster.enum.account_status.' . $row['account_status']);
            }
            $managers[$row['id']] = $label;
        }

        return ['departments' => $departments, 'managers' => $managers];
    }

    // ---- business types ----

    /**
     * The list of business types
     * @return string
     */
    public function businessTypes(): string
    {
        $data = [
            'page_title' => 'Business Types',
            'slug'       => 'business-types',
            'listConfig' => $this->businessTypesListConfig(),
        ];
        return view('generic_list', $data);
    }

    /**
     * One page of business types. The API sends them all (they are few), so the search, filters, order and paging
     * of the DataTables request are applied here.
     * @return ResponseInterface
     */
    public function businessTypesList(): ResponseInterface
    {
        $config = $this->businessTypesListConfig();
        $params = lb_request($this->request, $config);
        try {
            $all = array_map([self::class, 'flattenTypeNames'], $this->businessTypeRows());
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
     * Business type form: 'create' opens an empty one, any other value is the public_id of the one to view
     * (the Edit button switches the same page to edit mode)
     * @param string $publicId
     * @return string
     */
    public function businessTypesEdit(string $publicId): string
    {
        $mode   = 'create' === $publicId ? 'create' : 'view';
        $values = [];
        if ('view' === $mode) {
            $row = array_column($this->businessTypeRows(), null, 'public_id')[$publicId] ?? null;
            if (null === $row) {
                throw PageNotFoundException::forPageNotFound('Business type not found.');
            }
            $values = self::flattenTypeNames($row);
        }

        $fields = (new BusinessTypeModel())->getConfiguration();
        $fields['type_code'] += ['maxlength' => 64, 'help' => 'Lowercase letters, digits and underscores, such as coffee_shop; fixed once created'];
        $fields['type_status']['default'] = BusinessTypeModel::STATUS_ACTIVE;
        $fields[] = ['type' => 'heading', 'label' => lang('BusinessType.fields.type_name')];
        foreach (listSupportedLanguages() as $language => $languageName) {
            $fields[] = [
                'name'      => self::typeNameField($language),
                'type'      => 'text',
                'label'     => $languageName,
                'rules'     => 'required|max_length[255]',
                'maxlength' => 255,
            ];
        }

        $title = 'view' === $mode
            ? ($values[self::typeNameField(BusinessTypeModel::DEFAULT_LANGUAGE)] ?? '') ?: $values['type_code']
            : 'New Business Type';
        $data  = [
            'page_title'  => $title,
            'slug'        => 'business-types',
            'breadcrumbs' => [base_url('admin/business-types') => 'Business Types'],
            'formConfig'  => [
                'form_id'  => 'business-type-form',
                'title'    => ['create' => 'New Business Type', 'view' => 'Business Type Detail'][$mode],
                'subtitle' => 'Fields marked with * are required; a name is needed in every language',
                'mode'     => $mode,
                'id_field' => 'public_id',
                'id'       => $values['public_id'] ?? '',
                'method'   => 'POST',
                'locale'   => LOCALE,
                'urls'     => [
                    'submit' => base_url('admin/business-types/save'),
                    'edit'   => base_url('admin/business-types/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business-types'),
                ],
                'values'   => $values,
                'fields'   => array_values($fields),
            ],
        ];
        return view('generic_form', $data);
    }

    /**
     * Save a business type: an empty public_id creates one through LINK_INTERNAL_API v1/staff/business-type/create,
     * otherwise the one with that public_id is updated through v1/staff/business-type/edit (type_code is fixed there)
     * @return ResponseInterface
     */
    public function businessTypesEditPost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $create   = '' === $publicId;

        $names = [];
        foreach (array_keys(listSupportedLanguages()) as $language) {
            $names[$language] = trim((string) ($post[self::typeNameField($language)] ?? ''));
        }
        $payload = ['type_status' => (string) ($post['type_status'] ?? ''), 'translations' => $names];
        $payload = $create
            ? ['type_code' => trim((string) ($post['type_code'] ?? ''))] + $payload
            : ['public_id' => $publicId] + $payload;

        [$code, $body] = $this->callApi('v1/staff/business-type/' . ($create ? 'create' : 'edit'), $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // the API names a name's error translations.{language}; the form's field is type_name_{language}
            $errors = [];
            foreach ((array) ($body['errors'] ?? []) as $field => $message) {
                $language          = str_starts_with($field, 'translations.') ? substr($field, strlen('translations.')) : null;
                $errors[null === $language ? $field : self::typeNameField($language)] = $message;
            }
            $body['errors'] = $errors ?: null;
            return $this->saveFailed($code, $body, 'Could not save the business type. Please try again.');
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Saved.',
            'redirect'  => base_url('admin/business-types/edit/' . rawurlencode((string) ($body['data']['public_id'] ?? $publicId))),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder configuration of the business type list, shared by the page and its JSON endpoint:
     * the code, a name column for every supported language, and the status
     * @return array
     */
    private function businessTypesListConfig(): array
    {
        $fields  = (new BusinessTypeModel())->getConfiguration();
        $columns = [BusinessTypeModel::toColumns(['type_code' => $fields['type_code']])[0]];
        foreach (listSupportedLanguages() as $language => $languageName) {
            $name      = self::typeNameField($language);
            $columns[] = ['data' => $name, 'db' => $name, 'title' => $languageName];
        }
        $columns[] = BusinessTypeModel::toColumns(['type_status' => $fields['type_status']])[0];
        return [
            'table_id'    => 'businessTypeTable',
            'title'       => 'Business Types',
            'subtitle'    => 'The types a client picks for a business, with their names in every supported language',
            'id_field'    => 'public_id',
            'urls'        => [
                'data'   => base_url('admin/business-types'),
                'create' => base_url('admin/business-types/create'),
                'edit'   => base_url('admin/business-types/edit/{public_id}'),
            ],
            'labels'      => ['add' => 'Add Business Type'],
            'columns'     => $columns,
            'filters'     => BusinessTypeModel::toFilters($fields, ['type_status']),
            'order'       => [['type_code', 'asc']],
            'page_length' => 25,
        ];
    }

    /**
     * Every business type with its names, from LINK_INTERNAL_API v1/staff/business-types
     * @return list<array{public_id: string, type_code: string, type_status: string, translations: array<string, string>}>
     * @throws RuntimeException When the API call fails, with the message to show
     */
    private function businessTypeRows(): array
    {
        [$code, $body] = $this->callApi('v1/staff/business-types', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the business types. Please try again.');
        }
        return (array) ($body['data']['rows'] ?? []);
    }

    /**
     * A business type from the API with its translations as type_name_{language} keys, for the table and the form
     * @param array $row
     * @return array
     */
    private static function flattenTypeNames(array $row): array
    {
        foreach ((array) ($row['translations'] ?? []) as $language => $name) {
            $row[self::typeNameField((string) $language)] = $name;
        }
        unset($row['translations']);
        return $row;
    }

    /**
     * The form field and table column of a business type's name in $language: type_name_en_US, type_name_th
     * @param string $language
     * @return string
     */
    private static function typeNameField(string $language): string
    {
        return 'type_name_' . str_replace('-', '_', $language);
    }

    /**
     * The signed-in staff member's own profile: the account form of their own uid, opened in view mode
     * (the Edit button switches the same page to edit mode). Employment, legal and admin fields stay disabled.
     * @return string
     */
    public function profile(): string
    {
        $uid = (string) (session('user')['uid'] ?? '');
        [$code, $body] = $this->callApi('v1/staff/account', ['uid' => $uid]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load your profile.');
        }
        // keyed by field name, as the form looks values up by the field's 'name'
        $values   = (array) ($body['data']['account'] ?? []) + (array) ($body['data']['profile'] ?? []);
        unset($values['admin_notes'], $values['hr_notes']); // not shown to the account holder, see accountFormSections()
        $sections = $this->accountFormSections(AuthMasterModel::TYPE_STAFF, $this->staffFormOptions($uid), null, true);
        // the session copy carries a version after an upload (see imageUpload()), so the new picture is not a cached one
        $avatar   = (string) ($values['avatar_path'] ?? '');
        $session  = (string) (session('user')['avatar_path'] ?? '');
        if ('' !== $avatar && strtok($session, '?') === $avatar) {
            $avatar = $session;
        }
        // with no picture, the session holds the initials avatar (from sign-in or imageDelete()), shown in its place
        $fallback = '' === $avatar ? avatarUrl($session) : avatarUrl(null);
        $data     = [
            'page_title' => 'Profile',
            'slug'       => 'profile',
            'formConfig' => [
                'form_id'  => 'profile-form',
                'title'    => 'My Profile',
                'subtitle' => 'Fields marked with * are required',
                'mode'     => 'view',
                'id_field' => 'uid',
                'id'       => $uid,
                'method'   => 'POST',
                'locale'   => LOCALE,
                'urls'     => [
                    'submit' => base_url('admin/profile/save'),
                    'edit'   => base_url('admin/profile'),
                ],
                'values'   => $values,
                'fields'   => array_merge(...array_column($sections, 'fields')),
            ],
            // a stand-alone upload form: the API saves the picture to avatar_path of auth_master row 'id'
            'extraForms' => [[
                'form_id'   => 'profile-picture-form',
                'title'     => 'Profile Picture',
                'subtitle'  => 'A square image of at least 512 × 512 pixels works best',
                'id_prefix' => 'picture-',
                'id_field'  => 'id',
                'id'        => (int) (session('user')['id'] ?? 0),
                'urls'      => ['submit' => base_url('admin/image-upload'), 'delete' => base_url('admin/image-delete')],
                'values'    => ['used-for' => 'profile-picture', 'image-name' => $uid],
                'fields'    => [
                    ['name' => 'used-for', 'type' => 'hidden'],
                    ['name' => 'image-name', 'type' => 'hidden'],
                    ['name' => 'image', 'type' => 'file', 'label' => 'Profile picture', 'rules' => 'required', 'preview' => '' === $avatar ? $fallback : avatarUrl($avatar),
                     'stored' => '' !== $avatar, 'default_preview' => $fallback],
                ],
            ], [
                // the API checks the current password and the new one's rules (8+ characters, a letter and a number)
                'form_id'   => 'change-password-form',
                'title'     => 'Change Password',
                'subtitle'  => 'Enter your current password, then the new one twice',
                'mode'      => 'edit',
                'id_prefix' => 'password-',
                'id_field'  => 'uid',
                'id'        => $uid,
                'urls'      => ['submit' => base_url('admin/profile/password')],
                'labels'    => ['save' => 'Change Password'],
                'messages'  => ['saved' => 'Your password has been changed.', 'saveFailed' => 'Could not change your password. Please try again.'],
                'fields'    => [
                    ['name' => 'current_password', 'type' => 'password', 'label' => 'Current password', 'rules' => 'required', 'autocomplete' => 'current-password'],
                    ['name' => 'new_password', 'type' => 'password', 'label' => 'New password', 'rules' => 'required|min_length[8]', 'autocomplete' => 'new-password',
                     'help' => 'At least 8 characters, with a letter and a number'],
                    ['name' => 'confirm_password', 'type' => 'password', 'label' => 'Confirm new password', 'rules' => 'required|matches[new_password]', 'autocomplete' => 'new-password',
                     'messages' => ['matches' => 'The new passwords do not match.']],
                ],
            ]],
        ];
        return view('generic_form', $data);
    }

    /**
     * Change the signed-in staff member's own password through LINK_INTERNAL_API v1/staff/change-password,
     * which checks the current password and the new one's rules
     * @return ResponseInterface
     */
    public function profilePasswordPost(): ResponseInterface
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        if ($new !== (string) $this->request->getPost('confirm_password')) {
            return $this->response->setStatusCode(422)->setJSON([
                'success'   => false,
                'message'   => 'The new passwords do not match.',
                'errors'    => ['confirm_password' => 'The new passwords do not match.'],
                'csrf_hash' => csrf_hash(),
            ]);
        }

        [$code, $body] = $this->callApi('v1/staff/change-password', ['current_password' => $current, 'new_password' => $new]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not change your password. Please try again.');
        }

        // the session user keeps the new expiry, as the sign-in response would
        $user = (array) session('user');
        session()->set('user', ['password_expiry' => $body['data']['password_expiry'] ?? null] + $user);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Your password has been changed.',
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Delete an image through LINK_INTERNAL_API v1/staff/image-delete, for the stand-alone upload forms:
     * { used-for, id }, forwarded as sent (the API checks who may change which row).
     * For a profile picture the API answers with 'url', the account's initials avatar (null when the name gives no
     * initials), which takes the picture's place: in the session for the layout when it is the signed-in staff
     * member's own, and as 'url' for the form's thumbnail ('' = the form's default_preview).
     * @return ResponseInterface
     */
    public function imageDelete(): ResponseInterface
    {
        $usedFor = (string) $this->request->getPost('used-for');
        $id      = (string) $this->request->getPost('id');
        [$code, $body] = $this->callApi('v1/staff/image-delete', ['used-for' => $usedFor, 'id' => $id]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not delete the image. Please try again.');
        }

        $url  = (string) ($body['data']['url'] ?? '');
        $user = (array) session('user');
        if ('profile-picture' === $usedFor && $id === (string) ($user['id'] ?? '')) {
            session()->set('user', ['avatar_path' => '' === $url ? null : $url] + $user);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Image deleted.',
            'url'       => '' === $url ? '' : avatarUrl($url),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Upload an image through LINK_INTERNAL_API v1/staff/image-upload, for the stand-alone upload forms:
     * multipart { used-for, image-name, id, image }, forwarded as sent (the API checks who may change which row).
     * A new picture of the signed-in staff member's own account also goes into the session for the layout.
     * @return ResponseInterface
     */
    public function imageUpload(): ResponseInterface
    {
        $file = $this->request->getFile('image');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $message  = $tooLarge ? 'The image is too large. Please choose a smaller one.' : 'Please choose an image.';
            return $this->response->setStatusCode($tooLarge ? 413 : 422)->setJSON([
                'success'   => false,
                'message'   => $message,
                'errors'    => ['image' => $message],
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $usedFor = (string) $this->request->getPost('used-for');
        $id      = (string) $this->request->getPost('id');
        [$code, $body] = $this->callApi('v1/staff/image-upload', [
            'used-for'   => $usedFor,
            'image-name' => (string) $this->request->getPost('image-name'),
            'id'         => $id,
            'image'      => new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName()),
        ], true);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not upload the image. Please try again.');
        }

        // the file name stays the same on a re-upload, so the version makes browsers fetch the new image
        $url  = (string) ($body['data']['url'] ?? '');
        $url .= ('' === $url ? '' : '?v=' . time());
        $user = (array) session('user');
        if ('profile-picture' === $usedFor && '' !== $url && $id === (string) ($user['id'] ?? '')) {
            session()->set('user', ['avatar_path' => $url] + $user);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Image uploaded.',
            'url'       => $url,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Save the signed-in staff member's own profile through LINK_INTERNAL_API v1/staff/account/update.
     * The uid comes from the session, never the form, and the locked fields are never forwarded
     * @return ResponseInterface
     */
    public function profileEditPost(): ResponseInterface
    {
        $post = (array) $this->request->getPost();
        $user = (array) session('user');
        $uid  = (string) ($user['uid'] ?? '');

        $accountKeys = array_diff(array_keys((new AuthMasterModel())->getConfiguration()),
            AuthAccountModel::FORM_ACCOUNT_READONLY, self::OWN_ACCOUNT_LOCKED, ['uid']);
        $profileKeys = array_diff(array_keys((new StaffMasterModel())->getConfiguration()), ['auth_id'], self::OWN_STAFF_LOCKED);
        $account     = array_intersect_key($post, array_flip($accountKeys));
        if (in_array((string) ($account['otp_mode'] ?? ''), self::OTP_MODE_DISABLED, true)) {
            unset($account['otp_mode']);
        }

        [$code, $body] = $this->callApi('v1/staff/account/update', [
            'mode'    => 'self-edit', // the API checks the uid is the caller's and leaves the locked fields out
            'uid'     => $uid,
            'account' => $account,
            'profile' => array_intersect_key($post, array_flip($profileKeys)),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save your profile. Please try again.');
        }

        // the layout reads the name and contact details from the session user
        session()->set('user', array_merge($user, array_intersect_key($account, $user)));
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Profile saved.',
            'redirect'  => base_url('admin/profile'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---- staff profiles: what every staff member may see of the others ----

    /**
     * The staff profiles gallery: every active staff member's picture with their name underneath,
     * each linking to their public profile
     * @return string
     */
    public function publicProfiles(): string
    {
        $departments = $this->departmentNames();
        $staff       = [];
        foreach ($this->staffLookup() as $row) {
            if (($row['account_status'] ?? '') !== AuthMasterModel::STATUS_ACTIVE) {
                continue;
            }
            $staff[] = [
                'uid'        => (string) $row['uid'],
                'full_name'  => (string) $row['full_name'],
                'job_title'  => (string) ($row['job_title'] ?? ''),
                'department' => $departments[$row['department_id'] ?? 0]['name'] ?? '',
                'avatar'     => avatarUrl($row['avatar_path'] ?? null),
            ];
        }

        return view('admin_public_profiles', [
            'page_title' => 'Staff’s Profiles',
            'slug'       => 'public-profiles',
            'staff'      => $staff,
        ]);
    }

    /**
     * One active staff member's public profile: only work-facing fields (job, department, line manager, work contact,
     * status message and bio). Personal, legal, identity, emergency and HR fields never reach the page.
     * @param string $uid auth_master.uid
     * @return string
     */
    public function publicProfile(string $uid): string
    {
        [$code, $body] = $this->callApi('v1/staff/account', ['uid' => $uid]);
        if ($code === 404 || $code === 400) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the profile.');
        }
        $account = (array) ($body['data']['account'] ?? []);
        $profile = (array) ($body['data']['profile'] ?? []);
        // client accounts and staff who are no longer active have no public profile
        if (($account['account_type'] ?? '') !== AuthMasterModel::TYPE_STAFF
            || ($account['account_status'] ?? '') !== AuthMasterModel::STATUS_ACTIVE) {
            throw PageNotFoundException::forPageNotFound();
        }

        // the lookup carries the initials avatar when there is no picture, and the line manager's name and uid
        $lookup = $this->staffLookup();
        $self   = null;
        $byId   = [];
        foreach ($lookup as $row) {
            $byId[$row['id']] = $row;
            if ($row['uid'] === $uid) {
                $self = $row;
            }
        }
        $manager = $byId[(int) ($profile['manager_staff_id'] ?? 0)] ?? null;
        if (null !== $manager && ($manager['account_status'] ?? '') !== AuthMasterModel::STATUS_ACTIVE) {
            $manager['uid'] = null; // shown by name, but their profile is not public any more
        }
        $department = $this->departmentNames()[(int) ($profile['department_id'] ?? 0)] ?? null;
        $countries  = listCountries();
        $country    = (string) ($profile['work_country_code'] ?? '');
        $type       = (string) ($profile['employment_type'] ?? '');

        return view('admin_public_profile', [
            'page_title' => (string) $account['full_name'],
            'slug'       => 'public-profiles',
            'person'     => [
                'uid'             => $uid,
                'own'             => $uid === (string) (session('user')['uid'] ?? ''),
                'full_name'       => (string) $account['full_name'],
                'preferred_name'  => (string) ($profile['preferred_name'] ?? ''),
                'avatar'          => avatarUrl($self['avatar_path'] ?? ($account['avatar_path'] ?? null)),
                'job_title'       => (string) ($profile['job_title'] ?? ''),
                'department'      => $department['name'] ?? '',
                'employment_type' => '' === $type ? '' : lang('StaffMaster.enum.employment_type.' . $type),
                'start_date'      => (string) ($profile['employment_start_date'] ?? ''),
                'work_country'    => '' === $country ? '' : ($countries[$country] ?? $country),
                'work_email'      => (string) ($profile['work_email_address'] ?? ''),
                'work_telephone'  => (string) ($profile['work_telephone_number'] ?? ''),
                'status_message'  => (string) ($profile['status_message'] ?? ''),
                'bio'             => (string) ($profile['bio'] ?? ''),
                'manager'         => null === $manager ? null : ['name' => (string) $manager['full_name'], 'uid' => $manager['uid']],
            ],
        ]);
    }

    /**
     * LINK_INTERNAL_API v1/staff/staff-members: every staff member, by name, with avatar_path (picture or initials avatar)
     * @return list<array>
     */
    private function staffLookup(): array
    {
        [$code, $body] = $this->callApi('v1/staff/staff-members', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the staff members.');
        }
        return (array) ($body['data']['rows'] ?? []);
    }

    /**
     * LINK_INTERNAL_API v1/staff/departments, keyed by otternova_department.id
     * @return array<int, array{name: string, code: string}>
     */
    private function departmentNames(): array
    {
        [$code, $body] = $this->callApi('v1/staff/departments', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Could not load the departments.');
        }
        $departments = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $row) {
            $departments[(int) $row['id']] = ['name' => (string) $row['department_name'], 'code' => (string) $row['department_code']];
        }
        return $departments;
    }

    // ---- inbox: the public contact form's messages, through LINK_INTERNAL_API v1/staff/inbox/* ----

    /** contact_message.status */
    public const MESSAGE_STATUSES = [
        'N' => ['label' => 'New', 'class' => 'bg-primary'],
        'R' => ['label' => 'Read', 'class' => 'bg-info text-dark'],
        'D' => ['label' => 'Done', 'class' => 'bg-success'],
        'S' => ['label' => 'Spam', 'class' => 'bg-secondary'],
    ];

    /**
     * The inbox: the contact form's messages, newest first; the new ones at first
     * @return string
     */
    public function inbox(): string
    {
        $config           = $this->inboxListConfig();
        $config['values'] = ['status' => 'N'];
        return view('generic_list', [
            'page_title' => 'Inbox',
            'slug'       => 'inbox',
            'listConfig' => $config,
        ]);
    }

    /**
     * One page of messages: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/inbox/messages, which
     * always orders newest first and searches the sender's name and email address and the subject
     * @return ResponseInterface
     */
    public function inboxList(): ResponseInterface
    {
        $config  = $this->inboxListConfig();
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi('v1/staff/inbox/messages', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $body['message'] ?? 'Could not load the messages. Please try again.']);
        }

        $result = (array) ($body['data'] ?? []);
        $rows   = array_map(static function (array $row): array {
            $row['sender']  = '<div>' . esc((string) $row['name']) . '</div><div class="small text-secondary">' . esc((string) $row['email_address']) . '</div>';
            $row['topic']   = '<div class="fw-semibold">' . esc((string) $row['subject']) . '</div><div class="small text-secondary">' . esc((string) ($row['excerpt'] ?? '')) . '</div>';
            $row['handled'] = null === ($row['handled_by_name'] ?? null) ? '—' : (string) $row['handled_by_name'];
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
     * One message; opening a new one marks it read (the API does)
     * @param string $id contact_message.id
     * @return string
     */
    public function inboxView(string $id): string
    {
        [$code, $body] = $this->callApi('v1/staff/inbox/message', ['id' => (int) $id]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw $code === 404
                ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                : new RuntimeException($body['message'] ?? 'Could not load the message.');
        }
        $message = (array) ($body['data']['message'] ?? []);

        return view('admin_inbox_message', [
            'page_title'  => (string) ($message['subject'] ?? 'Message'),
            'slug'        => 'inbox',
            'breadcrumbs' => [base_url('admin/inbox') => 'Inbox'],
            'message'     => $message,
        ]);
    }

    /**
     * Mark a message new, read, done or spam through LINK_INTERNAL_API v1/staff/inbox/message/status: { id, status }
     * @return ResponseInterface
     */
    public function inboxStatus(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/staff/inbox/message/status', [
            'id'     => (int) $this->request->getPost('id'),
            'status' => strtoupper(trim((string) $this->request->getPost('status'))),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? 'Could not update the message. Please try again.',
                'csrf_hash' => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Message updated.',
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder configuration of the inbox, shared by the page and its JSON endpoint
     * @return array
     */
    private function inboxListConfig(): array
    {
        $column = static fn (string $data, string $title, array $extra = []): array
            => $extra + ['data' => $data, 'db' => $data, 'title' => $title, 'orderable' => false, 'searchable' => false];
        return [
            'table_id'    => 'inboxTable',
            'title'       => 'Contact Form Messages',
            'subtitle'    => 'Newest first; the search looks in the sender\'s name and email address and the subject',
            'id_field'    => 'id',
            'urls'        => [
                'data' => base_url('admin/inbox'),
                'edit' => base_url('admin/inbox/view/{id}'),
            ],
            'columns'     => [
                $column('created_at', 'Received', ['type' => 'datetime']),
                $column('sender', 'From', ['type' => 'html', 'searchable' => true]),
                $column('topic', 'Subject', ['type' => 'html', 'searchable' => true]),
                $column('status', 'Status', ['type' => 'badge', 'badges' => self::MESSAGE_STATUSES]),
                $column('handled', 'Handled By'),
            ],
            'filters'     => [
                ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => array_map(static fn (array $b): string => $b['label'], self::MESSAGE_STATUSES)],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }
}