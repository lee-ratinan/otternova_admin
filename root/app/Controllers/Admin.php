<?php

namespace App\Controllers;

use App\Models\AuthMasterModel;
use CodeIgniter\HTTP\ResponseInterface;

class Admin extends BaseController
{

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
        $authModel = new AuthMasterModel();
        $columns   = $authModel->getListViewConfiguration();
        unset($columns['uid']);
        unset($columns['password_hash']);
        unset($columns['admin_notes']);
        $data      = [
            'page_title' => 'Account',
            'slug'       => 'accounts',
            'listConfig' => [
                'table_id'    => 'accountTable',
                'title'       => 'All User Accounts',
                'subtitle'    => 'This table includes staff and client accounts',
                'ajax_url'    => base_url('admin/accounts'),
                'row_id'      => 'uid',
                'add'         => ['url' => base_url('admin/accounts/edit/create'), 'label' => 'Add Account'],
                'edit'        => ['url' => base_url('admin/accounts/edit/{uid}')],
                'columns'     => $columns,
                'filters'     => [
                    [
                        'name'    => 'otp_mode',
                        'type'    => 'select',
                        'label'   => lang('AuthMaster.fields.otp_mode'),
                        'column'  => 'p.otp_mode',
                        'options' => [
                            'E' => lang('AuthMaster.enum.otp_mode.E'),
                            'T' => lang('AuthMaster.enum.otp_mode.T'),
                            'X' => lang('AuthMaster.enum.otp_mode.X')
                        ]
                    ],
                    [
                        'name'    => 'account_status',
                        'type'    => 'select',
                        'label'   => lang('AuthMaster.fields.account_status'),
                        'column'  => 'p.account_status',
                        'options' => [
                            'P' => lang('AuthMaster.enum.account_status.P'),
                            'A' => lang('AuthMaster.enum.account_status.A'),
                            'B' => lang('AuthMaster.enum.account_status.B'),
                            'S' => lang('AuthMaster.enum.account_status.S'),
                        ]
                    ],
                    [
                        'name'    => 'account_type',
                        'type'    => 'select',
                        'label'   => lang('AuthMaster.fields.account_type'),
                        'column'  => 'p.account_type',
                        'options' => [
                            'S' => lang('AuthMaster.enum.account_type.S'),
                            'C' => lang('AuthMaster.enum.account_type.C'),
                        ]
                    ]
                ],
                'order'       => [['email_address', 'asc']],
                'page_length' => 25,
            ]
        ];
        return view('generic_list', $data);
    }

    /**
     * List accounts accordingly
     * @return ResponseInterface
     */
    public function accountsList(): ResponseInterface
    {
        $data = [
            ['edit_url' => base_url('admin/accounts/edit/eikinotternovacom'), 'password_expiry' => '2027-11-15', 'full_name' => '力川栄欽', 'email_address' => 'eikin@otternova.com', 'telephone_number' => '+6597754577', 'otp_mode' => 'E', 'account_status' => 'A', 'account_type' => 'S'],
            ['edit_url' => base_url('admin/accounts/edit/fauziotternovacom'), 'password_expiry' => '2027-11-15', 'full_name' => 'Fauzi', 'email_address' => 'fauzi@otternova.com', 'telephone_number' => '+6285658201275', 'otp_mode' => 'E', 'account_status' => 'A', 'account_type' => 'S'],
            ['edit_url' => base_url('admin/accounts/edit/jateotternovacom'), 'password_expiry' => '2027-11-15', 'full_name' => 'เจต', 'email_address' => 'jate@otternova.com', 'telephone_number' => '+66897828331', 'otp_mode' => 'E', 'account_status' => 'P', 'account_type' => 'S'],
            ['edit_url' => base_url('admin/accounts/edit/harrypotterhogwartsedu'), 'password_expiry' => '2027-11-15', 'full_name' => 'Harry Potter', 'email_address' => 'harry.potter@hogwarts.edu', 'telephone_number' => '+447700900000', 'otp_mode' => 'E', 'account_status' => 'A', 'account_type' => 'C'],
            ['edit_url' => base_url('admin/accounts/edit/ronweasleyhogwartsedu'), 'password_expiry' => '2027-11-15', 'full_name' => 'Ron Weasley', 'email_address' => 'ron.weasley@hogwarts.edu', 'telephone_number' => '+447700900001', 'otp_mode' => 'E', 'account_status' => 'B', 'account_type' => 'C'],
            ['edit_url' => base_url('admin/accounts/edit/hermionegrangerhogwartsedu'), 'password_expiry' => '2027-11-15', 'full_name' => 'Hermione Granger', 'email_address' => 'hermione.granger@hogwarts.edu', 'telephone_number' => '+447700900002', 'otp_mode' => 'T', 'account_status' => 'A', 'account_type' => 'C'],
            ['edit_url' => base_url('admin/accounts/edit/dracomalfoyhogwartsedu'), 'password_expiry' => '2027-11-15', 'full_name' => 'Draco Malfoy', 'email_address' => 'draco.malfoy@hogwarts.edu', 'telephone_number' => '+447700900003', 'otp_mode' => 'X', 'account_status' => 'S', 'account_type' => 'C'],
        ];
        $data = array_map(static function (array $row): array {
            $row['telephone_number'] = formatPhoneNumber($row['telephone_number']);
            $row['password_expiry'] = formatDate($row['password_expiry'], LOCALE);
            return $row;
        }, $data);
        return $this->response->setJSON([
            'draw'            => $this->request->getPost('draw'),
            'recordsTotal'    => count($data),
            'recordsFiltered' => count($data),
            'data'            => $data
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
                        'name' => 'name', 'type' => 'text', 'label' => 'Product name',
                        'placeholder' => 'e.g. Ergonomic chair', 'help' => '3-80 characters',
                        'rules' => 'required|min_length[3]|max_length[80]',
                    ],
                    [
                        'name' => 'sku', 'type' => 'text', 'label' => 'SKU', 'badge' => 'New',
                        'readonly_in' => ['edit'],                       // editable on create only
                        'rules' => 'required|alpha_dash|max_length[20]',
                        'messages' => ['alpha_dash' => 'SKU may only contain letters, digits, - and _.'],
                    ],
                    [
                        'name' => 'category_id', 'type' => 'select', 'label' => 'Category',
                        'options' => [1 => 'Electronics', 2 => 'Books', 3 => 'Home & Garden'],   // value => label
                        'attrs' => ['placeholder' => 'Choose a category'], 'rules' => 'required',
                    ],
                    [
                        'name' => 'price', 'type' => 'number', 'label' => 'Price',
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
            'values' => [
                'full_name'      => 'Nat Lee',
                'email'          => 'nat@example.com',
                'salary'         => '8500.00',
                'description'    => 'Software engineering and technical leadership.',
                'department'     => 'eng',
                'is_active'      => 'Y',
                'theme'          => 'dark',
                'interests'      => ['tech', 'art'],
                'rating'         => 8,
                'favorite_color' => '#336699',
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