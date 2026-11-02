<?php

namespace App\Controllers;

use App\Models\AuthAccountModel;
use App\Models\AuthMasterModel;
use App\Models\ClientMasterModel;
use App\Models\StaffMasterModel;
use CodeIgniter\Exceptions\PageNotFoundException;
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
     * POST JSON to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array $payload
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
            unset($account['avatar_path']); // not on the own profile for now
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

    /**
     * Notification page
     * @return string
     */
    public function notifications(): string
    {
        $data = [
            'page_title' => 'Notifications',
            'slug'       => 'notifications',
        ];
        return view('admin_notifications', $data);
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
        $sections = $this->accountFormSections(AuthMasterModel::TYPE_STAFF, $this->staffFormOptions($uid), null, true);
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
        ];
        return view('generic_form', $data);
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

    /**
     * Public Profile page
     * @param string $otternautSlug
     * @return string
     */
    public function publicProfile(string $otternautSlug): string
    {
        $data = [
            'page_title' => 'Public Profile',
            'slug'       => 'public-profile',
        ];
        return view('admin_public_profile', $data);
    }

    /**
     * Inbox
     * @return string
     */
    public function inbox(): string
    {
        $data = [
            'page_title' => 'Inbox',
            'slug'       => 'inbox',
        ];
        return view('generic_list', $data);
    }

    /**
     * Otternauts - our own users page
     * @return string
     */
    public function otternauts(): string
    {
        $data = [
            'page_title' => 'Oternauts',
            'slug'       => 'otternauts',
            'listConfig' => [
                'table_id' => 'mainDataTables',
                'title'    => 'Products',
                'subtitle' => 'All products in the catalogue',
                'ajax_url' => site_url('listexample/data'),
                'row_id'   => 'id',
                // Leave 'url' empty (or omit 'add') to hide the button, e.g. for users without permission.
                'add'  => ['url' => site_url('formexample/create'), 'label' => 'Add Product'],
                'edit' => ['url' => site_url('formexample/edit/{id}')],   // {id} is replaced per row by lb_rows()
                'columns' => [
                    ['data' => 'sku', 'title' => 'SKU', 'db' => 'p.sku'],
                    ['data' => 'name', 'title' => 'Name', 'db' => 'p.name'],
                    ['data' => 'category', 'title' => 'Category', 'db' => 'c.name'],
                    ['data'  => 'price', 'title' => 'Price', 'db' => 'p.price', 'type' => 'number', 'decimals' => 2,
                     'class' => 'text-end'
                    ],
                    [
                        'data' => 'status', 'title' => 'Status', 'db' => 'p.status', 'type' => 'badge', 'badges' => [
                        'D' => ['label' => 'Draft', 'class' => 'bg-secondary'],
                        'P' => ['label' => 'Published', 'class' => 'bg-success'],
                        'A' => ['label' => 'Archived', 'class' => 'bg-dark'],
                    ]
                    ],
                    ['data'       => 'is_active', 'title' => 'Active', 'db' => 'p.is_active', 'type' => 'boolean',
                     'searchable' => false
                    ],
                    ['data' => 'created_at', 'title' => 'Created', 'db' => 'p.created_at', 'type' => 'datetime', 'searchable' => false],
                ],
                'filters' => [
                    ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'column' => 'p.status',
                     'options' => ['D' => 'Draft', 'P' => 'Published', 'A' => 'Archived']],
                    ['name' => 'from', 'type' => 'date', 'label' => 'Created from', 'column' => 'p.created_at', 'operator' => '>='],
                    ['name' => 'to', 'type' => 'date', 'label' => 'Created to', 'column' => 'p.created_at', 'operator' => '<='],
                ],
                'order'       => [['created_at', 'desc']],
                'page_length' => 25,
            ]
        ];
        return view('generic_list', $data);
    }

    /**
     * Edit Otternaut page
     * @param string $otternautSlug
     * @return string
     */
    public function otternautsEdit(string $otternautSlug): string
    {
        $data = [
            'page_title' => 'Edit Oternauts',
            'slug'       => 'otternauts-edit',
        ];
        return view('generic_form', $data);
    }

}