<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Home extends BaseController
{
    // Only staff members (auth_master.account_type = 'S') may sign in to this system
    private const USER_TYPE = 'S';
    // Hard cap on the session lifetime, whatever the API returns
    private const MAX_SESSION_SECONDS = 8 * 3600;
    // Quick check before the API applies its full password policy
    private const MIN_PASSWORD_LENGTH = 8;
    // Shown when a reset link is missing, used up or expired and the API gives no message of its own
    private const RESET_INVALID = 'This password reset link is invalid or has expired. Please request a new one.';

    /**
     * Show the login form, or go straight to the dashboard when already signed in.
     */
    public function index(): string|RedirectResponse
    {
        if (hasActiveSession()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('public_login');
    }

    /**
     * POST login: forward the credentials to LINK_INTERNAL_API.
     * Responds with {status: 'otp_required'}, {status: 'password_change_required'} or {status: 'success', redirect}.
     */
    public function login(): ResponseInterface
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->fail('Please enter a valid email address and password.', 422);
        }

        [$code, $body] = $this->callApi('v1/login', [
            'email_address' => $email,
            'password'      => $password,
            'account_type'  => self::USER_TYPE,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->fail($body['message'] ?? 'Invalid email address or password.', $code === 200 ? 401 : $code);
        }

        $data    = $body['data'] ?? [];
        $session = session();
        $session->remove(['otp_token', 'otp_email', 'password_change_token']);

        if (! empty($data['otp_required'])) {
            // otp_mode = 'E': the API has emailed the code, keep the challenge token server-side only
            if (empty($data['otp_token'])) {
                return $this->fail('Unable to start the verification. Please try again.', 502);
            }
            $session->set([
                'otp_token' => $data['otp_token'],
                'otp_email' => $email,
            ]);
            return $this->response->setJSON([
                'status'  => 'otp_required',
                'message' => 'A verification code has been sent to ' . $email . '.',
            ]);
        }

        // otp_mode = 'T' or 'X': signed in without OTP, unless the password has expired
        return $this->completeSignIn($data);
    }

    /**
     * POST verify-otp: verify the emailed code with LINK_INTERNAL_API, then create the session.
     */
    public function verifyOtp(): ResponseInterface
    {
        $session  = session();
        $otpToken = $session->get('otp_token');
        if (empty($otpToken)) {
            return $this->fail('Your verification has expired. Please sign in again.', 410);
        }

        $otp = preg_replace('/\D/', '', (string) $this->request->getPost('otp'));
        if (strlen($otp) !== 6) {
            return $this->fail('Please enter the 6-digit verification code.', 422);
        }

        [$code, $body] = $this->callApi('v1/verify-otp', [
            'otp_token' => $otpToken,
            'otp'       => $otp,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // 410: the challenge is gone (expired or too many attempts), start over
            if ($code === 410) {
                $session->remove(['otp_token', 'otp_email']);
            }
            return $this->fail($body['message'] ?? 'The verification code is incorrect.', $code === 200 ? 401 : $code);
        }

        $session->remove(['otp_token', 'otp_email']);
        return $this->completeSignIn($body['data'] ?? []);
    }

    /**
     * POST change-password: set a new password when the current one has expired, then create the session.
     * Only reachable after the password (and the OTP, when the account has one) was accepted.
     */
    public function changePassword(): ResponseInterface
    {
        $session     = session();
        $changeToken = $session->get('password_change_token');
        if (empty($changeToken)) {
            return $this->fail('Your password change has expired. Please sign in again.', 410);
        }

        $password = (string) $this->request->getPost('new_password');
        $confirm  = (string) $this->request->getPost('confirm_password');
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return $this->fail('The new password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.', 422);
        }
        if ($password !== $confirm) {
            return $this->fail('The new passwords do not match.', 422);
        }

        [$code, $body] = $this->callApi('v1/change-expired-password', [
            'password_change_token' => $changeToken,
            'new_password'          => $password,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // 410: the change token is gone (expired or already used), start over
            if ($code === 410) {
                $session->remove('password_change_token');
            }
            return $this->fail($body['message'] ?? 'Unable to change your password. Please try again.', $code === 200 ? 422 : $code);
        }

        $session->remove('password_change_token');
        return $this->createSession($body['data'] ?? []);
    }

    /**
     * GET logout: revoke the JWT at LINK_INTERNAL_API (best effort), then end the session.
     */
    public function logout(): RedirectResponse
    {
        $session = session();
        $token   = $session->get('access_token');
        if (! empty($token)) {
            $this->callApi('v1/logout', [], $token);
        }
        $session->destroy();
        return redirect()->to(base_url());
    }

    /**
     * GET forgot-password: the form asking for the email address to send the reset link to.
     */
    public function forgotPassword(): string|RedirectResponse
    {
        if (hasActiveSession()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('public_forgotPassword');
    }

    /**
     * POST forgot-password: ask LINK_INTERNAL_API to email a reset link.
     * The answer is the same whether or not the address has an account, so it does not tell anyone which do.
     */
    public function submitForgotPassword(): ResponseInterface
    {
        $email = trim((string) $this->request->getPost('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Please enter a valid email address.', 422, ['email' => 'Please enter a valid email address.']);
        }

        [$code, $body] = $this->callApi('v1/request-password-reset', [
            'email_address' => $email,
            'account_type'  => self::USER_TYPE,
        ]);
        // 429 and 503 are about the request, not the account, so they may be shown as they are
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->fail($body['message'] ?? 'Unable to send the reset link. Please try again.', $code === 200 ? 502 : $code);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'If ' . $email . ' has an account, a link to reset its password has been sent to it.',
        ]);
    }

    /**
     * GET reset-password?token=: the link from the reset email. A valid token is kept server-side and the token is
     * dropped from the address bar (so it does not leak through the history or the Referer header);
     * GET reset-password then shows the new password form, or the error when there is no usable token.
     */
    public function resetPassword(): string|RedirectResponse
    {
        $session = session();
        $token   = trim((string) $this->request->getGet('token'));
        if ($token !== '') {
            $session->remove('reset_token');
            [$code, $body] = $this->callApi('v1/check-password-reset', ['token' => $token]);
            if ($code === 200 && ($body['status'] ?? '') === 'success') {
                $session->set('reset_token', $token);
            } else {
                $session->setFlashdata('reset_error', $body['message'] ?? self::RESET_INVALID);
            }
            return redirect()->to(base_url('reset-password'));
        }

        return view('public_resetPassword', [
            'valid' => ! empty($session->get('reset_token')),
            'error' => $session->getFlashdata('reset_error') ?? self::RESET_INVALID,
        ]);
    }

    /**
     * POST reset-password: set the new password with the token kept by resetPassword(), then send the user to sign in.
     * Responds with {status: 'success', redirect} or {status: 'error', message, errors}; 410 when the link is no longer usable.
     */
    public function submitResetPassword(): ResponseInterface
    {
        $session = session();
        $token   = $session->get('reset_token');
        if (empty($token)) {
            return $this->fail(self::RESET_INVALID, 410);
        }

        $password = (string) $this->request->getPost('new_password');
        $confirm  = (string) $this->request->getPost('confirm_password');
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $message = 'The new password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
            return $this->fail($message, 422, ['new_password' => $message]);
        }
        if ($password !== $confirm) {
            $message = 'The new passwords do not match.';
            return $this->fail($message, 422, ['confirm_password' => $message]);
        }

        [$code, $body] = $this->callApi('v1/reset-password', [
            'token'        => $token,
            'new_password' => $password,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // 410: the link is used up or expired, so a new one is needed
            if ($code === 410) {
                $session->remove('reset_token');
            }
            return $this->fail($body['message'] ?? 'Unable to reset your password. Please try again.', $code === 200 ? 422 : $code, (array) ($body['errors'] ?? []));
        }

        $session->remove('reset_token');
        $session->setFlashdata('auth_alert', ['success', $body['message'] ?? 'Your password has been reset. Please sign in with the new password.']);
        return $this->response->setJSON([
            'status'   => 'success',
            'redirect' => base_url(),
        ]);
    }

    /**
     * Credentials (and OTP) accepted: sign in, or hold the user at the password change when the password has expired.
     * No session is created until the password is changed.
     */
    private function completeSignIn(array $data): ResponseInterface
    {
        if (empty($data['password_change_required'])) {
            return $this->createSession($data);
        }
        if (empty($data['password_change_token'])) {
            return $this->fail('Unable to start the password change. Please try again.', 502);
        }
        // keep the change token server-side only, like the OTP token
        session()->set('password_change_token', $data['password_change_token']);
        return $this->response->setJSON([
            'status'  => 'password_change_required',
            'message' => 'Your password has expired. Please choose a new password to continue.',
        ]);
    }

    /**
     * Store the JWT and the user profile returned by LINK_INTERNAL_API in the session.
     */
    private function createSession(array $data): ResponseInterface
    {
        if (empty($data['access_token']) || empty($data['user'])) {
            return $this->fail('Unable to sign in. Please try again.', 502);
        }

        $maxExpiry = time() + self::MAX_SESSION_SECONDS;
        $expiresAt = (int) ($data['expires_at'] ?? $maxExpiry);

        $session = session();
        $session->regenerate(true);
        $session->set([
            'access_token' => $data['access_token'],
            'expires_at'   => min($expiresAt, $maxExpiry),
            'user'         => $data['user'],
        ]);

        return $this->response->setJSON([
            'status'   => 'success',
            'redirect' => base_url('admin/dashboard'),
        ]);
    }

    /**
     * POST JSON to LINK_INTERNAL_API and return [http status, decoded body].
     */
    private function callApi(string $endpoint, array $payload, ?string $token = null): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                'json'        => $payload,
                'headers'     => $headers,
                'timeout'     => 15,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            if (! is_array($body) || $code === 404 || $code >= 500) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => 'The authentication service is unavailable. Please try again later.']];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => 'The authentication service is unavailable. Please try again later.']];
        }
    }

    /**
     * @param array $errors Field => message, for the form to highlight; left out when empty
     */
    private function fail(string $message, int $code, array $errors = []): ResponseInterface
    {
        $code = ($code >= 400 && $code < 600) ? $code : 500;
        $body = [
            'status'  => 'error',
            'message' => $message,
        ];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        return $this->response->setStatusCode($code)->setJSON($body);
    }
}
