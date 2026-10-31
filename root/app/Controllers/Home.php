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
     * Responds with either {status: 'otp_required'} or {status: 'success', redirect}.
     */
    public function login(): ResponseInterface
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->fail('Please enter a valid email address and password.', 422);
        }

        [$code, $body] = $this->callApi('login', [
            'email_address' => $email,
            'password'      => $password,
            'account_type'  => self::USER_TYPE,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->fail($body['message'] ?? 'Invalid email address or password.', $code === 200 ? 401 : $code);
        }

        $data    = $body['data'] ?? [];
        $session = session();
        $session->remove(['otp_token', 'otp_email']);

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

        // otp_mode = 'T' or 'X': signed in without OTP
        return $this->createSession($data);
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

        [$code, $body] = $this->callApi('verify-otp', [
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
            $this->callApi('logout', [], $token);
        }
        $session->destroy();
        return redirect()->to(base_url());
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

    private function fail(string $message, int $code): ResponseInterface
    {
        $code = ($code >= 400 && $code < 600) ? $code : 500;
        return $this->response->setStatusCode($code)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}
