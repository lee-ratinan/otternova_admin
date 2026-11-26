<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * The salary history and bank account of a staff member, on their account page (admin/accounts/edit/{uid}):
 * the Executive Office only (EXECUTIVE_DEPARTMENT; the route's department filter, and the API checks the same).
 * Each form posts here and comes back to the account page with a notice.
 */
class StaffSalaries extends BaseController
{
    /** staff_salary.rate_unit */
    public const RATE_UNITS = ['M' => 'per month', 'W' => 'per week', 'D' => 'per day', 'H' => 'per hour'];

    /** staff_salary.pay_frequency */
    public const FREQUENCIES = ['M' => 'Monthly', 'S' => 'Semi-monthly', 'W' => 'Weekly'];

    /** staff_salary.change_reason */
    public const CHANGE_REASONS = ['H' => 'Hired', 'P' => 'Promotion', 'A' => 'Annual review', 'C' => 'Correction', 'O' => 'Other'];

    /** The salary fields a form posts, as the API takes them */
    private const SALARY_FIELDS = ['entity', 'effective_date', 'end_date', 'currency_code', 'base_amount', 'rate_unit',
        'pay_frequency', 'standard_hours_week', 'change_reason', 'note'];

    /** The bank account fields */
    private const PAY_ACCOUNT_FIELDS = ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'];

    /**
     * What the account page shows of a staff member's pay: LINK_INTERNAL_API v1/staff/salary, or null with the error
     * @param string $uid auth_master.uid
     * @return array{data: array|null, error: string|null}
     */
    public static function load(string $uid): array
    {
        [$code, $body] = (new self())->callApi('v1/staff/salary', ['uid' => $uid]);
        $ok            = $code === 200 && ($body['status'] ?? '') === 'success' && is_array($body['data'] ?? null);

        return ['data' => $ok ? $body['data'] : null, 'error' => $ok ? null : ($body['message'] ?? 'Could not load the salary details.')];
    }

    /**
     * Add a salary after the history: POST uid and the salary fields
     * @return RedirectResponse
     */
    public function add(): RedirectResponse
    {
        $uid = trim((string) $this->request->getPost('uid'));
        [$code, $body] = $this->callApi('v1/staff/salary/add', ['uid' => $uid, 'salary' => $this->salaryFromPost()]);

        return $this->back($uid, $code, $body);
    }

    /**
     * Correct the latest salary: POST uid, public_id and the salary fields
     * @return RedirectResponse
     */
    public function edit(): RedirectResponse
    {
        $uid = trim((string) $this->request->getPost('uid'));
        [$code, $body] = $this->callApi('v1/staff/salary/edit', [
            'uid'       => $uid,
            'public_id' => trim((string) $this->request->getPost('public_id')),
            'salary'    => $this->salaryFromPost(),
        ]);

        return $this->back($uid, $code, $body);
    }

    /**
     * Save where the pay goes: POST uid and the bank account fields
     * @return RedirectResponse
     */
    public function payAccount(): RedirectResponse
    {
        $uid     = trim((string) $this->request->getPost('uid'));
        $account = [];
        foreach (self::PAY_ACCOUNT_FIELDS as $field) {
            $account[$field] = trim((string) $this->request->getPost($field));
        }
        [$code, $body] = $this->callApi('v1/staff/salary/pay-account', ['uid' => $uid, 'pay_account' => $account]);

        return $this->back($uid, $code, $body);
    }

    /**
     * @return array The posted salary as the API takes it
     */
    private function salaryFromPost(): array
    {
        $salary = [];
        foreach (self::SALARY_FIELDS as $field) {
            $salary[$field] = trim((string) $this->request->getPost($field));
        }
        $salary['entity']          = '' === $salary['entity'] ? null : $salary['entity'];
        $salary['social_security'] = '1' === (string) $this->request->getPost('social_security');

        return $salary;
    }

    /**
     * Back to the account page, with the API's message and, when it failed, its field errors
     * @param string $uid
     * @param int    $code
     * @param array  $body
     * @return RedirectResponse
     */
    private function back(string $uid, int $code, array $body): RedirectResponse
    {
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        session()->setFlashdata('salary_notice', [
            $ok ? 'success' : 'danger',
            $body['message'] ?? ($ok ? 'Saved.' : 'Could not save. Please try again.'),
            $ok ? [] : array_values(array_map('strval', (array) ($body['errors'] ?? []))),
        ]);
        $redirect = redirect()->to(base_url('admin/accounts/edit/' . rawurlencode($uid)) . '#salary');

        return $ok ? $redirect : $redirect->withInput();
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
