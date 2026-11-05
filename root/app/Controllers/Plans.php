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
 * What the businesses can buy, behind the auth and department:EXE,FIN filters: the plans, their prices (plan
 * versions), the payment methods and the discount codes. Everything is read and saved through LINK_INTERNAL_API
 * v1/staff/plans/*, which checks the department again.
 * A plan is in the URL by its plan_code, a payment method by its method_code, a price and a discount by their public_id.
 * The API takes times with their offset and sends them back in UTC; the forms take a local date and time with the
 * timezone it is in (DEFAULT_TIMEZONE unless another is picked).
 */
class Plans extends BaseController
{
    /** Timezone the forms show and take their times in, unless another is picked */
    private const DEFAULT_TIMEZONE = 'Asia/Bangkok';

    /** plan_tier of the free plan, which has no prices */
    private const FREE_TIER = 0;

    /** country_code of the rest of the world */
    private const REST_OF_WORLD = 'ZZ';

    private const BILLING_CYCLES     = ['M' => 'Monthly', 'Y' => 'Yearly'];
    private const YES_NO             = ['Y' => 'Yes', 'N' => 'No'];
    private const CONFIRMATION_MODES = ['A' => 'Payment provider (automatic)', 'M' => 'Staff (manual check)'];
    private const DISCOUNT_TYPES     = ['P' => 'Percentage', 'F' => 'Fixed amount'];
    private const VERSION_STATES     = [
        'S' => ['label' => 'Scheduled', 'class' => 'bg-info'],
        'C' => ['label' => 'Current', 'class' => 'bg-success'],
        'E' => ['label' => 'Ended', 'class' => 'bg-secondary'],
    ];

    // ---- plans ----

    /**
     * Every plan, the free one first
     * @return string
     */
    public function plans(): string
    {
        return view('generic_list', [
            'page_title' => 'Plans',
            'slug'       => 'plans',
            'listConfig' => $this->plansListConfig(),
        ]);
    }

    /**
     * One page of plans; the API sends them all, so the DataTables request is applied here
     * @return ResponseInterface
     */
    public function plansList(): ResponseInterface
    {
        return $this->localList($this->plansListConfig(), fn (): array => $this->planRows(), 'plan_code');
    }

    /**
     * Plan form: 'create' opens an empty one, any other value is the plan_code of the one to view
     * @param string $code
     * @return string
     */
    public function plansEdit(string $code): string
    {
        $mode   = 'create' === $code ? 'create' : 'view';
        $values = [];
        if ('view' === $mode) {
            $values = array_column($this->planRows(), null, 'plan_code')[$code] ?? null;
            if (null === $values) {
                throw PageNotFoundException::forPageNotFound('Plan not found.');
            }
        }
        $isFree = 'view' === $mode && self::FREE_TIER === (int) $values['plan_tier'];

        $fields = [
            ['name' => 'plan_code', 'type' => 'text', 'label' => lang('Plans.fields.plan_code'), 'readonly_in' => ['edit'],
             'rules' => 'required|regex_match[/^[a-z][a-z0-9_]{1,19}$/]', 'maxlength' => 20,
             'help' => '2-20 lowercase letters, digits and underscores, starting with a letter, such as boost; fixed once created'],
            ['name' => 'plan_tier', 'type' => 'number', 'label' => lang('Plans.fields.plan_tier'), 'min' => 0, 'max' => 255, 'step' => 1,
             'rules' => 'required|is_natural|less_than_equal_to[255]', 'readonly_in' => $isFree ? ['edit'] : [],
             'help' => $isFree ? 'The free plan stays at tier 0' : 'Ranks the plans, each its own: 0 is the free plan, a higher tier is a bigger plan'],
            ['name' => 'plan_description', 'type' => 'textarea', 'label' => lang('Plans.fields.plan_description'), 'rows' => 4,
             'rules' => 'max_length[2000]', 'optional' => true, 'maxlength' => 2000],
        ];
        if ('view' === $mode) {
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Prices', $isFree
                ? '<span class="text-secondary">The free plan has no prices</span>'
                : '<a href="' . esc(base_url('admin/plan-prices') . '?plan=' . rawurlencode($code), 'attr') . '"><i class="bi bi-tags"></i> See the prices of this plan</a>')];
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Last updated', self::utcTime($values['updated_at'] ?? null))];
        }

        return view('generic_form', [
            'page_title'  => 'view' === $mode ? 'Plan: ' . $code : 'New Plan',
            'slug'        => 'plans',
            'breadcrumbs' => [base_url('admin/plans') => 'Plans'],
            'formConfig'  => $this->formConfig('plan-form', $mode, 'Plan', 'plan_code', $values['plan_code'] ?? '', 'admin/plans', 'admin/plans/edit/' . rawurlencode($code), $values, $fields),
        ]);
    }

    /**
     * Save a plan: an empty original_code creates one (v1/staff/plans/plan/create), otherwise that plan is updated
     * (v1/staff/plans/plan/edit, which keeps the plan_code)
     * @return ResponseInterface
     */
    public function plansEditPost(): ResponseInterface
    {
        $post   = (array) $this->request->getPost();
        $code   = trim((string) ($post['plan_code'] ?? ''));
        $create = '' === trim((string) ($post['original_code'] ?? ''));
        if (! $create) {
            $code = trim((string) $post['original_code']);
        }

        [$apiCode, $body] = $this->callApi('v1/staff/plans/plan/' . ($create ? 'create' : 'edit'), [
            'plan_code'        => $code,
            'plan_description' => trim((string) ($post['plan_description'] ?? '')),
            'plan_tier'        => trim((string) ($post['plan_tier'] ?? '')),
        ]);
        if ($apiCode !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($apiCode, $body, 'Could not save the plan. Please try again.');
        }

        return $this->saved($body, 'admin/plans/edit/' . rawurlencode((string) ($body['data']['plan_code'] ?? $code)));
    }

    // ---- prices ----

    /**
     * Every price of every plan; ?plan=boost opens the list on that plan
     * @return string
     */
    public function prices(): string
    {
        $config = $this->pricesListConfig($this->planOptions());
        $plan   = trim((string) $this->request->getGet('plan'));
        if ('' !== $plan) {
            $config['values'] = ['plan_code' => $plan];
        }

        return view('generic_list', [
            'page_title' => 'Plan Prices',
            'slug'       => 'plan-prices',
            'listConfig' => $config,
        ]);
    }

    /**
     * One page of prices. The API sends them all in its own order (plan tier, country, cycle, newest first), which
     * the list keeps; the filters and the search are applied here.
     * @return ResponseInterface
     */
    public function pricesList(): ResponseInterface
    {
        return $this->localList($this->pricesListConfig(null), function (): array {
            return array_map(static function (array $row): array {
                $row['country_label'] = self::countryLabel($row['country_code']);
                $row['price_label']   = self::money($row['currency_code'], $row['price']);
                $row['effective_to']  = (string) ($row['effective_to'] ?? '');
                return $row;
            }, $this->versionRows());
        }, 'public_id');
    }

    /**
     * Price form: 'create' sets a new price, any other value is the public_id of the price to view. A price cannot
     * be edited; one that has not started can be cancelled (the form's Delete button).
     * @param string $publicId
     * @return string
     */
    public function pricesEdit(string $publicId): string
    {
        $mode   = 'create' === $publicId ? 'create' : 'view';
        $values = [];
        $state  = null;
        if ('view' === $mode) {
            $values = array_column($this->versionRows(), null, 'public_id')[$publicId] ?? null;
            if (null === $values) {
                throw PageNotFoundException::forPageNotFound('Price not found.');
            }
            $state  = (string) $values['state'];
            $values = self::toLocalTimes($values, ['effective_from', 'effective_to'], self::DEFAULT_TIMEZONE);
        } else {
            $plan = trim((string) $this->request->getGet('plan'));
            if ('' !== $plan) {
                $values['plan_code'] = $plan;
            }
        }

        $fields = [
            ['name' => 'plan_code', 'type' => 'select', 'label' => lang('Plans.fields.plan_id'), 'rules' => 'required',
             'options' => $this->planOptions(), 'help' => 'The free plan has no prices'],
            ['name' => 'country_code', 'type' => 'select', 'label' => lang('Plans.fields.country_code'), 'rules' => 'required',
             'options' => self::countryOptions(), 'help' => 'Rest of the world is the price for every country that has none of its own'],
            ['name' => 'billing_cycle', 'type' => 'select', 'label' => lang('Plans.fields.billing_cycle'), 'rules' => 'required',
             'options' => self::BILLING_CYCLES],
            ['name' => 'currency_code', 'type' => 'select', 'label' => lang('Plans.fields.currency_code'), 'rules' => 'required',
             'options' => self::currencyOptions(), 'help' => 'A plan, country and cycle that already has a price keeps its currency'],
            ['name' => 'price', 'type' => 'number', 'label' => lang('Plans.fields.price'), 'rules' => 'required|greater_than[0]',
             'min' => 0.01, 'step' => 0.01, 'view' => static fn ($value, $field): string => self::money($values['currency_code'] ?? '', $value)],
            ['name' => 'effective_from', 'type' => 'datetime-local', 'label' => lang('Plans.fields.effective_from'), 'rules' => 'required',
             'help' => 'When this price starts; the plan\'s current price for the country and cycle ends then. A change must start in the future, after the last one set.',
             'view' => static fn ($value): string => self::viewTime($value, self::DEFAULT_TIMEZONE)],
        ];
        if ('view' === $mode) {
            $fields[] = ['name' => 'effective_to', 'type' => 'datetime-local', 'label' => 'Effective To', 'show_in' => ['view'],
                         'view' => static fn ($value): string => '' === (string) $value ? 'Until a new price starts' : self::viewTime($value, self::DEFAULT_TIMEZONE)];
            $badge    = self::VERSION_STATES[$state] ?? ['label' => $state, 'class' => 'bg-secondary'];
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('State', '<span class="badge ' . esc($badge['class'], 'attr') . '">' . esc($badge['label']) . '</span>')];
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Set at', self::utcTime($values['created_at'] ?? null))];
        } else {
            $fields[] = self::timezoneField();
        }

        $config = $this->formConfig('plan-price-form', $mode, 'Price', 'public_id', $values['public_id'] ?? '', 'admin/plan-prices', '#', $values, $fields);
        $config['editable'] = false; // a price is never edited: a change is a new price
        $config['subtitle'] = 'create' === $mode ? 'Fields marked with * are required' : 'A price cannot be changed; set a new price from a later time instead';
        if ('S' === $state) {
            // only a change that has not started yet can be taken back
            $config['urls']['delete'] = base_url('admin/plan-prices/cancel');
            $config['labels']         = ['delete' => 'Cancel Price Change'];
            $config['messages']       = ['confirmDelete' => 'Cancel this price change? The current price will run on with no end.'];
        }

        return view('generic_form', [
            'page_title'  => 'view' === $mode ? 'Price ' . $publicId : 'New Price',
            'slug'        => 'plan-prices',
            'breadcrumbs' => [base_url('admin/plan-prices') => 'Plan Prices'],
            'formConfig'  => $config,
        ]);
    }

    /**
     * Set a new price through LINK_INTERNAL_API v1/staff/plans/version/create
     * @return ResponseInterface
     */
    public function pricesEditPost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $timezone = self::timezoneOf($post['timezone'] ?? null);

        [$code, $body] = $this->callApi('v1/staff/plans/version/create', [
            'plan_code'      => trim((string) ($post['plan_code'] ?? '')),
            'country_code'   => trim((string) ($post['country_code'] ?? '')),
            'currency_code'  => trim((string) ($post['currency_code'] ?? '')),
            'billing_cycle'  => trim((string) ($post['billing_cycle'] ?? '')),
            'price'          => trim((string) ($post['price'] ?? '')),
            'effective_from' => self::toOffsetTime($post['effective_from'] ?? null, $timezone),
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save the price. Please try again.');
        }

        return $this->saved($body, 'admin/plan-prices/view/' . rawurlencode((string) ($body['data']['public_id'] ?? '')));
    }

    /**
     * Cancel a price change that has not started, through LINK_INTERNAL_API v1/staff/plans/version/cancel
     * @return ResponseInterface
     */
    public function pricesCancel(): ResponseInterface
    {
        $publicId = trim((string) $this->request->getPost('public_id'));
        [$code, $body] = $this->callApi('v1/staff/plans/version/cancel', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not cancel the price change. Please try again.');
        }

        return $this->saved($body, 'admin/plan-prices');
    }

    // ---- payment methods ----

    /**
     * Every payment method, in display order
     * @return string
     */
    public function paymentMethods(): string
    {
        return view('generic_list', [
            'page_title' => 'Payment Methods',
            'slug'       => 'payment-methods',
            'listConfig' => $this->paymentMethodsListConfig(),
        ]);
    }

    /**
     * One page of payment methods; the API sends them all, so the DataTables request is applied here
     * @return ResponseInterface
     */
    public function paymentMethodsList(): ResponseInterface
    {
        return $this->localList($this->paymentMethodsListConfig(), function (): array {
            return array_map(static function (array $row): array {
                $row['country_label']  = null === $row['country_code'] || '' === $row['country_code'] ? 'Every country' : self::countryLabel($row['country_code']);
                $row['provider_code'] = (string) ($row['provider_code'] ?? '');
                return $row;
            }, $this->methodRows());
        }, 'method_code');
    }

    /**
     * Payment method form: 'create' opens an empty one, any other value is the method_code of the one to view
     * @param string $code
     * @return string
     */
    public function paymentMethodsEdit(string $code): string
    {
        $mode   = 'create' === $code ? 'create' : 'view';
        $values = ['is_active' => 'Y', 'sort_order' => 0];
        if ('view' === $mode) {
            $values = array_column($this->methodRows(), null, 'method_code')[$code] ?? null;
            if (null === $values) {
                throw PageNotFoundException::forPageNotFound('Payment method not found.');
            }
        }

        $fields = [
            ['name' => 'method_code', 'type' => 'text', 'label' => lang('Plans.fields.method_code'), 'readonly_in' => ['edit'],
             'rules' => 'required|regex_match[/^[a-z][a-z0-9_]{1,29}$/]', 'maxlength' => 30,
             'help' => '2-30 lowercase letters, digits and underscores, starting with a letter, such as promptpay; fixed once created'],
            ['name' => 'country_code', 'type' => 'select', 'label' => lang('Plans.fields.country_code'), 'optional' => true,
             'options' => listSupportedCountries(), 'placeholder' => 'Every country', 'help' => 'The country of the businesses that can use it',
             'view' => static fn ($value): string => listSupportedCountries()[(string) $value] ?? 'Every country'],
            ['name' => 'currency_code', 'type' => 'select', 'label' => lang('Plans.fields.currency_code'), 'rules' => 'required',
             'options' => self::currencyOptions(), 'help' => 'Only invoices in this currency can be paid with it'],
            ['name' => 'confirmation_mode', 'type' => 'select', 'label' => lang('Plans.fields.confirmation_mode'), 'rules' => 'required',
             'options' => self::CONFIRMATION_MODES],
            ['name' => 'provider_code', 'type' => 'text', 'label' => lang('Plans.fields.provider_code'), 'optional' => true,
             'rules' => 'regex_match[/^[a-z][a-z0-9_]{1,29}$/]', 'maxlength' => 30,
             'help' => 'Required when a payment provider confirms the payments; lowercase, such as omise'],
            ['name' => 'is_active', 'type' => 'select', 'label' => lang('Plans.fields.is_active'), 'rules' => 'required', 'options' => self::YES_NO],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('Plans.fields.sort_order'), 'rules' => 'required|is_natural|less_than_equal_to[255]',
             'min' => 0, 'max' => 255, 'step' => 1, 'help' => 'Lower numbers are shown first'],
        ];
        if ('view' === $mode) {
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Last updated', self::utcTime($values['updated_at'] ?? null))];
        }

        return view('generic_form', [
            'page_title'  => 'view' === $mode ? 'Payment Method: ' . $code : 'New Payment Method',
            'slug'        => 'payment-methods',
            'breadcrumbs' => [base_url('admin/payment-methods') => 'Payment Methods'],
            'formConfig'  => $this->formConfig('payment-method-form', $mode, 'Payment Method', 'method_code', 'view' === $mode ? $code : '',
                'admin/payment-methods', 'admin/payment-methods/edit/' . rawurlencode($code), $values, $fields),
        ]);
    }

    /**
     * Save a payment method: an empty original_code creates one (v1/staff/plans/payment-method/create), otherwise
     * that method is updated (v1/staff/plans/payment-method/edit, which keeps the method_code)
     * @return ResponseInterface
     */
    public function paymentMethodsEditPost(): ResponseInterface
    {
        $post   = (array) $this->request->getPost();
        $create = '' === trim((string) ($post['original_code'] ?? ''));
        $code   = trim((string) ($create ? ($post['method_code'] ?? '') : $post['original_code']));

        $payload = ['method_code' => $code];
        foreach (['country_code', 'currency_code', 'confirmation_mode', 'provider_code', 'is_active', 'sort_order'] as $field) {
            $payload[$field] = trim((string) ($post[$field] ?? ''));
        }
        [$apiCode, $body] = $this->callApi('v1/staff/plans/payment-method/' . ($create ? 'create' : 'edit'), $payload);
        if ($apiCode !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($apiCode, $body, 'Could not save the payment method. Please try again.');
        }

        return $this->saved($body, 'admin/payment-methods/edit/' . rawurlencode((string) ($body['data']['method_code'] ?? $code)));
    }

    // ---- discounts ----

    /**
     * The discount codes, newest first
     * @return string
     */
    public function discounts(): string
    {
        return view('generic_list', [
            'page_title' => 'Discounts',
            'slug'       => 'discounts',
            'listConfig' => $this->discountsListConfig(),
        ]);
    }

    /**
     * One page of discounts: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/plans/discounts, which
     * always orders newest first and searches the code and the description
     * @return ResponseInterface
     */
    public function discountsList(): ResponseInterface
    {
        $config = $this->discountsListConfig();
        $params = lb_request($this->request, $config);
        [$code, $body] = $this->callApi('v1/staff/plans/discounts', [
            'start'  => $params['start'],
            'length' => $params['length'],
            'search' => $params['search'],
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error' => $body['message'] ?? 'Could not load the discounts. Please try again.',
            ]);
        }

        $result = (array) ($body['data'] ?? []);
        $rows   = array_map(static function (array $row): array {
            $row['value_label']   = self::discountValue($row);
            $row['applies_to']    = self::discountScope($row);
            $row['uses']          = $row['redemption_count'] . ' / ' . (null === $row['max_redemptions'] ? '∞' : $row['max_redemptions']);
            $row['discount_description'] = (string) ($row['discount_description'] ?? '');
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
     * Discount form: 'create' opens an empty one, any other value is the public_id of the one to view
     * @param string $publicId
     * @return string
     */
    public function discountsEdit(string $publicId): string
    {
        $mode   = 'create' === $publicId ? 'create' : 'view';
        $values = ['discount_type' => 'P', 'is_listed' => 'N', 'max_per_business' => 1];
        if ('view' === $mode) {
            [$code, $body] = $this->callApi('v1/staff/plans/discount', ['public_id' => $publicId]);
            if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
                throw $code === 404
                    ? PageNotFoundException::forPageNotFound($body['message'] ?? null)
                    : new RuntimeException($body['message'] ?? 'Could not load the discount.');
            }
            $values = self::toLocalTimes((array) ($body['data']['discount'] ?? []), ['valid_from', 'expires_at'], self::DEFAULT_TIMEZONE);
        }
        $values['timezone'] = self::DEFAULT_TIMEZONE;
        $timeView = static fn ($value): string => self::viewTime($value, self::DEFAULT_TIMEZONE);

        $heading = static fn (string $label): array => ['type' => 'heading', 'label' => $label];
        $fields  = [
            $heading('Discount'),
            ['name' => 'discount_code', 'type' => 'text', 'label' => lang('Plans.fields.discount_code'), 'rules' => 'required|regex_match[/^[A-Za-z0-9_-]{3,30}$/]',
             'maxlength' => 30, 'help' => '3-30 letters, digits, - and _, such as LAUNCH50; saved in capitals and typed in at checkout'],
            ['name' => 'discount_description', 'type' => 'textarea', 'label' => lang('Plans.fields.discount_description'), 'rows' => 2,
             'rules' => 'max_length[255]', 'maxlength' => 255, 'optional' => true],
            ['name' => 'discount_type', 'type' => 'select', 'label' => lang('Plans.fields.discount_type'), 'rules' => 'required', 'options' => self::DISCOUNT_TYPES],
            ['name' => 'discount_value', 'type' => 'number', 'label' => lang('Plans.fields.discount_value'), 'rules' => 'required|greater_than[0]',
             'min' => 0.01, 'step' => 0.01, 'help' => 'A percentage (up to 100) or an amount in the currency below',
             'view' => static fn ($value): string => self::discountValue(['discount_value' => $value] + $values)],
            ['name' => 'currency_code', 'type' => 'select', 'label' => lang('Plans.fields.currency_code'), 'optional' => true,
             'options' => self::currencyOptions(), 'placeholder' => 'None (percentage)', 'help' => 'Fixed amounts only',
             'view' => static fn ($value): string => self::currencyOptions()[(string) $value] ?? 'None'],
            $heading('Who can use it'),
            ['name' => 'country_code', 'type' => 'select', 'label' => lang('Plans.fields.country_code'), 'optional' => true,
             'options' => self::countryOptions(), 'placeholder' => 'Every country', 'help' => 'A fixed amount needs its country',
             'view' => static fn ($value): string => self::countryOptions()[(string) $value] ?? 'Every country'],
            ['name' => 'plan_code', 'type' => 'select', 'label' => lang('Plans.fields.plan_id'), 'optional' => true,
             'options' => $this->planOptions(), 'placeholder' => 'Every plan',
             'view' => static fn ($value): string => '' === (string) $value ? 'Every plan' : (string) $value],
            ['name' => 'billing_cycle', 'type' => 'select', 'label' => lang('Plans.fields.billing_cycle'), 'optional' => true,
             'options' => self::BILLING_CYCLES, 'placeholder' => 'Both cycles',
             'view' => static fn ($value): string => self::BILLING_CYCLES[(string) $value] ?? 'Both cycles'],
            ['name' => 'business_slug', 'type' => 'text', 'label' => lang('Plans.fields.business_id'), 'optional' => true, 'maxlength' => 100,
             'help' => 'The business slug, to limit it to that business only; empty for every business',
             'view' => static fn ($value): string => '' === (string) $value ? 'Every business' : trim(($values['business_name'] ?? '') . ' (' . $value . ')')],
            ['name' => 'is_listed', 'type' => 'select', 'label' => lang('Plans.fields.is_listed'), 'rules' => 'required', 'options' => self::YES_NO,
             'help' => 'Yes: offered to the businesses at checkout; No: only works when its code is typed in'],
            $heading('Limits'),
            ['name' => 'max_redemptions', 'type' => 'number', 'label' => lang('Plans.fields.max_redemptions'), 'optional' => true,
             'rules' => 'is_natural_no_zero', 'min' => 1, 'step' => 1, 'placeholder' => 'No limit', 'help' => 'In total, across every business; empty for no limit',
             'view' => static fn ($value): string => '' === (string) $value ? 'No limit' : (string) $value],
            ['name' => 'max_per_business', 'type' => 'number', 'label' => lang('Plans.fields.max_per_business'), 'optional' => true,
             'rules' => 'is_natural_no_zero|less_than_equal_to[255]', 'min' => 1, 'max' => 255, 'step' => 1, 'placeholder' => 'No limit',
             'view' => static fn ($value): string => '' === (string) $value ? 'No limit' : (string) $value],
            ['name' => 'valid_from', 'type' => 'datetime-local', 'label' => lang('Plans.fields.valid_from'), 'rules' => 'required', 'view' => $timeView],
            ['name' => 'expires_at', 'type' => 'datetime-local', 'label' => lang('Plans.fields.expires_at'), 'rules' => 'required', 'view' => $timeView],
            self::timezoneField(),
        ];
        if ('view' === $mode) {
            $fields[] = ['show_in' => ['view']] + $heading('Record');
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Times used', esc((string) ($values['redemption_count'] ?? 0)))];
            $created  = trim(esc((string) ($values['created_by_name'] ?? '')) . ' ' . self::utcTime($values['created_at'] ?? null));
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Created', $created)];
            $fields[] = ['type' => 'html', 'show_in' => ['view'], 'html' => self::infoRow('Last updated', self::utcTime($values['updated_at'] ?? null))];
        }

        return view('generic_form', [
            'page_title'  => 'view' === $mode ? 'Discount: ' . $values['discount_code'] : 'New Discount',
            'slug'        => 'discounts',
            'breadcrumbs' => [base_url('admin/discounts') => 'Discounts'],
            'formConfig'  => $this->formConfig('discount-form', $mode, 'Discount', 'public_id', $values['public_id'] ?? '',
                'admin/discounts', 'admin/discounts/edit/' . rawurlencode($publicId), $values, $fields),
        ]);
    }

    /**
     * Save a discount through LINK_INTERNAL_API v1/staff/plans/discount/update: an empty public_id creates one.
     * Every field is sent; an empty one is none (no limit).
     * @return ResponseInterface
     */
    public function discountsEditPost(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $timezone = self::timezoneOf($post['timezone'] ?? null);

        $payload = ['public_id' => '' === $publicId ? null : $publicId];
        foreach (['discount_code', 'discount_description', 'discount_type', 'discount_value', 'currency_code', 'country_code',
                  'business_slug', 'plan_code', 'billing_cycle', 'is_listed', 'max_redemptions', 'max_per_business'] as $field) {
            $payload[$field] = trim((string) ($post[$field] ?? ''));
        }
        $payload['discount_code'] = strtoupper($payload['discount_code']);
        $payload['valid_from']    = self::toOffsetTime($post['valid_from'] ?? null, $timezone);
        $payload['expires_at']    = self::toOffsetTime($post['expires_at'] ?? null, $timezone);

        [$code, $body] = $this->callApi('v1/staff/plans/discount/update', $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, 'Could not save the discount. Please try again.');
        }

        return $this->saved($body, 'admin/discounts/edit/' . rawurlencode((string) ($body['data']['public_id'] ?? $publicId)));
    }

    // ---- configuration ----

    /**
     * Table Builder configuration of the plan list, shared by the page and its JSON endpoint
     * @return array
     */
    private function plansListConfig(): array
    {
        return [
            'table_id'    => 'planTable',
            'title'       => 'Plans',
            'subtitle'    => 'What a business can subscribe to; tier 0 is the free plan',
            'id_field'    => 'plan_code',
            'urls'        => [
                'data'   => base_url('admin/plans'),
                'create' => base_url('admin/plans/create'),
                'edit'   => base_url('admin/plans/edit/{plan_code}'),
            ],
            'labels'      => ['add' => 'Add Plan'],
            'columns'     => [
                self::column('plan_tier', lang('Plans.fields.plan_tier'), ['class' => 'text-end', 'width' => '8%']),
                self::column('plan_code', lang('Plans.fields.plan_code')),
                self::column('plan_description', lang('Plans.fields.plan_description')),
                self::column('updated_at', 'Last Updated', ['type' => 'datetime', 'searchable' => false]),
            ],
            'filters'     => [],
            'order'       => [['plan_tier', 'asc']],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of the price list, shared by the page and its JSON endpoint
     * @param array|null $plans From planOptions(), for the plan filter; null for the JSON endpoint, which checks the
     *                          filter against the rows only
     * @return array
     */
    private function pricesListConfig(?array $plans): array
    {
        $fixed = ['orderable' => false];
        return [
            'table_id'    => 'planPriceTable',
            'title'       => 'Plan Prices',
            'subtitle'    => 'The price of a plan in a country for a billing cycle over time; a new price ends the one before it',
            'id_field'    => 'public_id',
            'urls'        => [
                'data'   => base_url('admin/plan-prices'),
                'create' => base_url('admin/plan-prices/create'),
                'edit'   => base_url('admin/plan-prices/view/{public_id}'),
            ],
            'labels'      => ['add' => 'Set a New Price'],
            'columns'     => [
                self::column('plan_code', lang('Plans.fields.plan_id'), $fixed),
                self::column('country_label', lang('Plans.fields.country_code'), $fixed),
                self::column('billing_cycle', lang('Plans.fields.billing_cycle'), $fixed + ['labels' => self::BILLING_CYCLES, 'searchable' => false]),
                self::column('price_label', lang('Plans.fields.price'), $fixed + ['class' => 'text-end text-nowrap']),
                self::column('effective_from', lang('Plans.fields.effective_from'), $fixed + ['type' => 'datetime', 'searchable' => false]),
                self::column('effective_to', 'Effective To', $fixed + ['type' => 'datetime', 'searchable' => false]),
                self::column('state', 'State', $fixed + ['type' => 'badge', 'badges' => self::VERSION_STATES, 'searchable' => false]),
            ],
            'filters'     => [
                ['name' => 'plan_code', 'type' => 'select', 'label' => lang('Plans.fields.plan_id'), 'options' => $plans ?? []],
                ['name' => 'country_code', 'type' => 'select', 'label' => lang('Plans.fields.country_code'), 'options' => self::countryOptions()],
                ['name' => 'billing_cycle', 'type' => 'select', 'label' => lang('Plans.fields.billing_cycle'), 'options' => self::BILLING_CYCLES],
                ['name' => 'state', 'type' => 'select', 'label' => 'State', 'options' => array_map(static fn (array $b): string => $b['label'], self::VERSION_STATES)],
            ],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of the payment method list, shared by the page and its JSON endpoint
     * @return array
     */
    private function paymentMethodsListConfig(): array
    {
        return [
            'table_id'    => 'paymentMethodTable',
            'title'       => 'Payment Methods',
            'subtitle'    => 'The ways a business can pay an invoice, in the order they are shown',
            'id_field'    => 'method_code',
            'urls'        => [
                'data'   => base_url('admin/payment-methods'),
                'create' => base_url('admin/payment-methods/create'),
                'edit'   => base_url('admin/payment-methods/edit/{method_code}'),
            ],
            'labels'      => ['add' => 'Add Payment Method'],
            'columns'     => [
                self::column('sort_order', lang('Plans.fields.sort_order'), ['class' => 'text-end', 'width' => '8%', 'searchable' => false]),
                self::column('method_code', lang('Plans.fields.method_code')),
                self::column('country_label', lang('Plans.fields.country_code')),
                self::column('currency_code', lang('Plans.fields.currency_code')),
                self::column('confirmation_mode', lang('Plans.fields.confirmation_mode'), ['labels' => self::CONFIRMATION_MODES, 'searchable' => false]),
                self::column('provider_code', lang('Plans.fields.provider_code')),
                self::column('is_active', lang('Plans.fields.is_active'), ['type' => 'badge', 'searchable' => false, 'badges' => [
                    'Y' => ['label' => 'Active', 'class' => 'bg-success'],
                    'N' => ['label' => 'Inactive', 'class' => 'bg-secondary'],
                ]]),
            ],
            'filters'     => [
                ['name' => 'is_active', 'type' => 'select', 'label' => lang('Plans.fields.is_active'), 'options' => self::YES_NO],
                ['name' => 'confirmation_mode', 'type' => 'select', 'label' => lang('Plans.fields.confirmation_mode'), 'options' => self::CONFIRMATION_MODES],
            ],
            'order'       => [['sort_order', 'asc']],
            'page_length' => 25,
        ];
    }

    /**
     * Table Builder configuration of the discount list, shared by the page and its JSON endpoint
     * @return array
     */
    private function discountsListConfig(): array
    {
        $fixed = ['orderable' => false, 'searchable' => false];
        return [
            'table_id'    => 'discountTable',
            'title'       => 'Discounts',
            'subtitle'    => 'Newest first; the search looks in the code and the description',
            'id_field'    => 'public_id',
            'urls'        => [
                'data'   => base_url('admin/discounts'),
                'create' => base_url('admin/discounts/create'),
                'edit'   => base_url('admin/discounts/edit/{public_id}'),
            ],
            'labels'      => ['add' => 'Add Discount'],
            'columns'     => [
                self::column('discount_code', lang('Plans.fields.discount_code'), ['orderable' => false]),
                self::column('discount_description', lang('Plans.fields.discount_description'), ['orderable' => false]),
                self::column('value_label', lang('Plans.fields.discount_value'), $fixed + ['class' => 'text-end text-nowrap']),
                self::column('applies_to', 'Applies To', $fixed),
                self::column('uses', 'Used', $fixed + ['class' => 'text-end text-nowrap']),
                self::column('valid_from', lang('Plans.fields.valid_from'), $fixed + ['type' => 'datetime']),
                self::column('expires_at', lang('Plans.fields.expires_at'), $fixed + ['type' => 'datetime']),
                self::column('is_listed', 'Listed', $fixed + ['type' => 'badge', 'badges' => [
                    'Y' => ['label' => 'Listed', 'class' => 'bg-success'],
                    'N' => ['label' => 'Code only', 'class' => 'bg-secondary'],
                ]]),
            ],
            'filters'     => [],
            'order'       => [],
            'page_length' => 25,
        ];
    }

    /**
     * Form Builder configuration shared by the plan, price, payment method and discount forms
     * @param string $formId
     * @param string $mode     'create' or 'view'
     * @param string $one      What the form holds, for its title: 'Plan'
     * @param string $idField  Hidden field naming the stored record; the plan and method forms send it as original_code
     * @param string $id       Empty for a new one
     * @param string $listPath
     * @param string $editPath '#' when the record cannot be edited
     * @param array  $values
     * @param array  $fields
     * @return array
     */
    private function formConfig(string $formId, string $mode, string $one, string $idField, string $id, string $listPath, string $editPath, array $values, array $fields): array
    {
        // the plan and method codes are fields of the form too, so the stored one goes in a hidden field of its own
        $hiddenId = in_array($idField, ['plan_code', 'method_code'], true) ? 'original_code' : $idField;
        return [
            'form_id'  => $formId,
            'title'    => ['create' => 'New ' . $one, 'view' => $one . ' Detail'][$mode],
            'subtitle' => 'Fields marked with * are required',
            'mode'     => $mode,
            'id_field' => $hiddenId,
            'id'       => $id,
            'method'   => 'POST',
            'locale'   => LOCALE,
            'urls'     => [
                'submit' => base_url($listPath . '/save'),
                'edit'   => '#' === $editPath ? '#' : base_url($editPath),
                'list'   => base_url($listPath),
            ],
            'values'   => $values,
            'fields'   => array_values($fields),
        ];
    }

    /**
     * A Table Builder column whose JSON key is also its search/order key
     * @param string $data
     * @param string $title
     * @param array  $extra
     * @return array
     */
    private static function column(string $data, string $title, array $extra = []): array
    {
        return $extra + ['data' => $data, 'db' => $data, 'title' => $title];
    }

    /**
     * The timezone select of the forms with times
     * @return array
     */
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
                'default' => self::DEFAULT_TIMEZONE, 'show_in' => ['create', 'edit'], 'help' => 'The timezone of the dates and times above'];
    }

    /**
     * A label and its content as a row of the form's view pane
     * @param string $label
     * @param string $html Trusted markup
     * @return string
     */
    private static function infoRow(string $label, string $html): string
    {
        return '<div class="row mb-3"><div class="col-sm-4 text-sm-end">' . esc($label) . '</div><div class="col-sm-8">'
            . ('' === $html ? '<span class="text-muted">&mdash;</span>' : $html) . '</div></div>';
    }

    /**
     * A <time data-utc> tag for a UTC time from the API, which main.js shows in the viewer's timezone.
     * (html_helper's localTime() is never defined: PHP's own localtime() already has that name.)
     * @param string|null $utc "2026-11-05 07:25:06"
     * @return string
     */
    private static function utcTime(?string $utc): string
    {
        return null === $utc || '' === $utc ? '' : '<time data-utc datetime="' . esc($utc, 'attr') . '">' . esc($utc) . ' UTC</time>';
    }

    // ---- lists ----

    /**
     * The JSON of a list whose rows all come from the API at once: filters, search, order and paging applied here
     * @param array    $config Table Builder configuration
     * @param callable $rows   fn(): list<array>, throws RuntimeException with the message to show
     * @param string   $tieKey Orders rows that are otherwise equal
     * @return ResponseInterface
     */
    private function localList(array $config, callable $rows, string $tieKey): ResponseInterface
    {
        $params = lb_request($this->request, $config);
        try {
            $all = $rows();
        } catch (RuntimeException $e) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $e->getMessage()]);
        }

        $columns  = array_column($params['columns'], null, 'data');
        $filtered = array_values(array_filter($all, static function (array $row) use ($params, $columns): bool {
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
        }));

        if ([] !== $params['order']) {
            $fieldOf = array_column($params['columns'], 'data', 'db');
            usort($filtered, static function (array $a, array $b) use ($params, $fieldOf, $tieKey): int {
                foreach ($params['order'] as [$db, $dir]) {
                    $cmp = strnatcasecmp((string) ($a[$fieldOf[$db]] ?? ''), (string) ($b[$fieldOf[$db]] ?? ''));
                    if (0 !== $cmp) {
                        return 'DESC' === $dir ? -$cmp : $cmp;
                    }
                }
                return strcmp((string) $a[$tieKey], (string) $b[$tieKey]);
            });
        }

        return $this->response->setJSON(lb_response(
            $params['draw'],
            count($all),
            count($filtered),
            lb_rows(array_slice($filtered, $params['start'], $params['length']), $config)
        ));
    }

    // ---- API ----

    /**
     * Every plan, from LINK_INTERNAL_API v1/staff/plans/plans
     * @return list<array>
     * @throws RuntimeException When the API call fails, with the message to show
     */
    private function planRows(): array
    {
        return $this->rowsOf('v1/staff/plans/plans', [], 'Could not load the plans. Please try again.');
    }

    /**
     * Every price, from LINK_INTERNAL_API v1/staff/plans/versions
     * @return list<array>
     * @throws RuntimeException
     */
    private function versionRows(): array
    {
        return $this->rowsOf('v1/staff/plans/versions', [], 'Could not load the prices. Please try again.');
    }

    /**
     * Every payment method, from LINK_INTERNAL_API v1/staff/plans/payment-methods
     * @return list<array>
     * @throws RuntimeException
     */
    private function methodRows(): array
    {
        return $this->rowsOf('v1/staff/plans/payment-methods', [], 'Could not load the payment methods. Please try again.');
    }

    /**
     * The plans that can have a price, as plan_code => label, for the dropdowns
     * @return array<string, string>
     */
    private function planOptions(): array
    {
        $options = [];
        foreach ($this->planRows() as $row) {
            if (self::FREE_TIER !== (int) $row['plan_tier']) {
                $options[$row['plan_code']] = $row['plan_code'] . ' (tier ' . $row['plan_tier'] . ')';
            }
        }
        return $options;
    }

    /**
     * @param string $endpoint
     * @param array  $payload
     * @param string $fallback
     * @return list<array>
     * @throws RuntimeException
     */
    private function rowsOf(string $endpoint, array $payload, string $fallback): array
    {
        [$code, $body] = $this->callApi($endpoint, $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? $fallback);
        }
        return (array) ($body['data']['rows'] ?? []);
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

    /**
     * Form response of a successful save
     * @param array  $body The API response
     * @param string $path Where the page goes next
     * @return ResponseInterface
     */
    private function saved(array $body, string $path): ResponseInterface
    {
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? 'Saved.',
            'redirect'  => base_url($path),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---- formatting ----

    /**
     * @return array<string, string> The supported countries and ZZ, the rest of the world
     */
    private static function countryOptions(): array
    {
        return listSupportedCountries() + [self::REST_OF_WORLD => 'Rest of the world'];
    }

    /**
     * @return array<string, string> code => "code — name"
     */
    private static function currencyOptions(): array
    {
        $options = [];
        foreach (listCurrencies() as $code => $name) {
            $options[$code] = $code . ' — ' . $name;
        }
        return $options;
    }

    /**
     * @param string $code
     * @return string
     */
    private static function countryLabel(string $code): string
    {
        return self::countryOptions()[$code] ?? $code;
    }

    /**
     * An amount from the API ("299.00") with its currency: "THB 299.00"
     * @param string|null $currency
     * @param mixed       $amount
     * @return string
     */
    private static function money(?string $currency, mixed $amount): string
    {
        if (null === $amount || '' === (string) $amount || ! is_numeric($amount)) {
            return '';
        }
        return trim($currency . ' ' . number_format((float) $amount, 2));
    }

    /**
     * A discount's value as it reads: "10%" or "THB 50.00"
     * @param array $row With discount_type, discount_value and currency_code
     * @return string
     */
    private static function discountValue(array $row): string
    {
        $value = $row['discount_value'] ?? null;
        if (null === $value || '' === (string) $value || ! is_numeric($value)) {
            return '';
        }
        return 'F' === ($row['discount_type'] ?? '')
            ? self::money($row['currency_code'] ?? '', $value)
            : rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%';
    }

    /**
     * Who a discount is for, in a few words: "TH · boost · Monthly · business-slug"
     * @param array $row
     * @return string
     */
    private static function discountScope(array $row): string
    {
        $parts = [];
        if (! empty($row['country_code'])) {
            $parts[] = self::REST_OF_WORLD === $row['country_code'] ? 'Rest of the world' : $row['country_code'];
        }
        if (! empty($row['plan_code'])) {
            $parts[] = $row['plan_code'];
        }
        if (! empty($row['billing_cycle'])) {
            $parts[] = self::BILLING_CYCLES[$row['billing_cycle']] ?? $row['billing_cycle'];
        }
        if (! empty($row['business_slug'])) {
            $parts[] = $row['business_name'] ?? $row['business_slug'];
        }
        return [] === $parts ? 'Everyone' : implode(' · ', $parts);
    }

    /**
     * The timezone a form was filled in, when it is one the forms offer
     * @param mixed $value
     * @return string
     */
    private static function timezoneOf(mixed $value): string
    {
        $value = is_string($value) ? $value : '';
        return array_key_exists($value, self::timezoneField()['options']) ? $value : self::DEFAULT_TIMEZONE;
    }

    /**
     * A datetime-local value in $timezone as the API takes it, with its offset: "2026-11-15T00:00:00+07:00".
     * An empty value stays empty (the API says it is required); one that cannot be read is sent as it is, so the
     * API reports it.
     * @param mixed  $local "2026-11-15T00:00"
     * @param string $timezone
     * @return string
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

    /**
     * The UTC times of an API row in $timezone, as the form's datetime-local fields show them
     * @param array        $row
     * @param list<string> $fields
     * @param string       $timezone
     * @return array
     */
    private static function toLocalTimes(array $row, array $fields, string $timezone): array
    {
        foreach ($fields as $field) {
            $utc = (string) ($row[$field] ?? '');
            if ('' === $utc) {
                continue;
            }
            try {
                $row[$field] = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d H:i:s');
            } catch (Exception) {
                // left as the API sent it
            }
        }
        return $row;
    }

    /**
     * How a local time of the form reads in view mode: "2026-11-15 00:00 (Asia/Bangkok)"
     * @param mixed  $value
     * @param string $timezone
     * @return string
     */
    private static function viewTime(mixed $value, string $timezone): string
    {
        $value = (string) $value;
        return '' === $value ? '' : substr(str_replace('T', ' ', $value), 0, 16) . ' (' . $timezone . ')';
    }
}
