<?php

namespace App\Controllers;

use App\Models\AuthMasterModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Admin extends BaseController
{

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
            if (! is_array($body) || $code === 404 || $code >= 500) {
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
        unset($fields['uid'], $fields['admin_notes']);
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

    public function accountsEdit(string $uid): string
    {
        $values = [];
        $mode   = 'create';
        if ('create' != $uid) {
            // keyed by field name, as the form looks values up by the field's 'name'
            $values = [
                'id'               => 15,
                'uid'              => 'eikinotternovacom',
                'email_address'    => 'eikin@otternova.com',
                'telephone_number' => '+6597754577',
                'password_expiry'  => '2027-11-15',
                'full_name'        => '力川栄欽',
                'otp_mode'         => 'E',
                'account_status'   => 'A',
                'account_type'     => 'S',
                'admin_notes'      => 'He is the CEO!',
            ];
            $mode   = 'view';
        }
        $authModel  = new AuthMasterModel();
        $formConfig = [
            'form_id'  => 'account-form',
            'title'    => ['create' => 'New Account', 'edit' => 'Edit Account', 'view' => 'Account Detail'][$mode],
            'subtitle' => 'Fields marked with * are required',
            'mode'     => $mode,
            'id'       => $values['id'] ?? 0,
            'method'   => 'POST',
            'urls'     => [
                'submit' => base_url('admin/accounts/save'),
                'delete' => base_url('admin/accounts/delete'),
                'edit'   => base_url('admin/accounts/edit/' . $uid),
                'list'   => base_url('admin/accounts'),
            ],
            'values'   => $values,
            'fields'   => array_values($authModel->getConfiguration()),
        ];
        $data       = [
            'page_title'  => 'Account',
            'slug'        => 'account',
            'breadcrumbs' => [
                base_url('admin/accounts') => 'Accounts',
            ],
            'mode'        => $mode,
            'formConfig'  => $formConfig
        ];
        return view('generic_form', $data);
    }

    /**
     * Save an account (create or edit)
     * TEST ONLY: nothing is validated or saved yet, it always reports success to the form
     * @return ResponseInterface
     */
    public function accountsEditPost(): ResponseInterface
    {
        return $this->response->setJSON([
            'success'   => true,
            'message'   => 'Account saved (test only, nothing was written).',
            'csrf_hash' => csrf_hash(), // keeps the form's token fresh when CSRF regenerates
        ]);
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
     * Profile page
     * @param string $mode
     * @return string
     */
    public function profile(string $mode): string
    {
        $mode = (empty($mode)) ? 'view' : $mode;
        $id   = 1; // user id
        $values = [
            'id' => $id, 'name' => 'Ergonomic chair', 'sku' => 'CH-001', 'category_id' => 3, 'price' => '249.90',
            'release_date' => '2026-03-15', 'color' => '#198754', 'tags' => ['new', 'eco'], 'status' => 'P',
            'is_active' => 1, 'notes' => "Line one\nLine two", 'internal_ref' => 'REF-77',
        ];
        $data = [
            'page_title' => 'Profile',
            'slug'       => 'profile',
            'mode'       => $mode,
            'formConfig' => [
                'form_id'  => 'product-form',
                'title'    => ['create' => 'New Product', 'edit' => 'Edit Product', 'view' => 'Product Details'][$mode],
                'subtitle' => 'Fields marked with * are required',
                'mode'     => $mode,
                'id'       => $id,
                'method'   => 'POST',
                'urls'     => [
                    'submit' => site_url('formexample/save'),
                    'delete' => site_url('formexample/delete'),
                    'edit'   => site_url('formexample/edit/' . $id),
                    'list'   => site_url('formexample'),
                ],
                'values'   => $values,
                'fields' => [
                    ['type' => 'heading', 'label' => 'General'],
                    [
                        'name' => 'name',
                        'type' => 'text',
                        'label' => 'Product name',
                        'placeholder' => 'e.g. Ergonomic chair',
                        'help' => '3-80 characters',
                        'rules' => 'required|min_length[3]|max_length[80]',
                    ],
                    [
                        'name' => 'sku',
                        'type' => 'text',
                        'label' => 'SKU',
                        'badge' => 'New',
                        'readonly_in' => ['edit'],                       // editable on create only
                        'rules' => 'required|alpha_dash|max_length[20]',
                        'messages' => ['alpha_dash' => 'SKU may only contain letters, digits, - and _.'],
                    ],
                    [
                        'name' => 'category_id',
                        'type' => 'select',
                        'label' => 'Category',
                        'options' => [1 => 'Electronics', 2 => 'Books', 3 => 'Home & Garden'],   // value => label
                        'attrs' => ['placeholder' => 'Choose a category'],
                        'rules' => 'required',
                    ],
                    [
                        'name' => 'price',
                        'type' => 'number',
                        'label' => 'Price',
                        'attrs' => ['min' => 0, 'max' => 99999, 'step' => '0.01', 'required' => true],
                        'help' => 'Two decimals max',
                    ],
                    [
                        'name' => 'release_date', 'type' => 'date', 'label' => 'Release date', 'optional' => true,
                    ],
                    [
                        'name' => 'color', 'type' => 'color', 'label' => 'Brand colour', 'optional' => true,
                        'default' => '#0d6efd',
                    ],
                    ['type' => 'divider'],
                    ['type' => 'heading', 'label' => 'Options'],
                    [
                        'name' => 'tags', 'type' => 'checkbox', 'label' => 'Tags', 'inline' => true,
                        'options' => ['new' => 'New arrival', 'sale' => 'On sale', 'eco' => 'Eco friendly'],
                        'rules' => 'required', 'messages' => ['required' => 'Pick at least one tag.'],
                    ],
                    [
                        'name' => 'status', 'type' => 'radio', 'label' => 'Status', 'default' => 'D',
                        'options' => [['value' => 'D', 'label' => 'Draft'], ['value' => 'P', 'label' => 'Published'], ['value' => 'A', 'label' => 'Archived', 'disabled' => true]],
                        'rules' => 'required',
                    ],
                    [
                        'name' => 'is_active', 'type' => 'switch', 'label' => 'Active', 'default' => 1,
                        'view_labels' => ['on' => 'Active', 'off' => 'Inactive'],
                    ],
                    [
                        'name' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'optional' => true,
                        'attrs' => ['rows' => 4, 'maxlength' => 500],
                    ],
                    ['name' => 'internal_ref', 'type' => 'text', 'label' => 'Internal ref', 'attrs' => ['disabled' => true], 'show_in' => ['edit', 'view']],
                ],
            ],
        ];
        return view('generic_form', $data);
    }

    /**
     * Edit own profile page
     * @return string
     */
    public function profileEdit(): string
    {
        $data = [
            'page_title' => 'Edit Profile',
            'slug'       => 'profile-edit',
        ];
        return view('generic_form', $data);
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
                    ['data' => 'created_at', 'title' => 'Created', 'db' => 'p.created_at', 'searchable' => false],
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