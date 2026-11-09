<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * The signed-in staff member's notifications, through LINK_INTERNAL_API v1/staff/notifications/*: the header's
 * dropdown (assets/js/notification-center.js, on every page) and the notification centre page. Opening one marks it
 * read and goes to its target_url, a path in this app.
 */
class Notifications extends BaseController
{
    private const API = 'v1/staff/notifications/';

    /**
     * GET admin/notifications: the notification centre, filled by its script
     * @return string
     */
    public function index(): string
    {
        return view('admin_notifications', [
            'page_title' => 'Notifications',
            'slug'       => 'notifications',
        ]);
    }

    /**
     * POST admin/notifications/latest: the newest few and the unread count, for the header's dropdown
     * @return ResponseInterface
     */
    public function latest(): ResponseInterface
    {
        return $this->relay('latest', []);
    }

    /**
     * POST admin/notifications/list { start, length, status }: one page for the notification centre
     * @return ResponseInterface
     */
    public function list(): ResponseInterface
    {
        $status = (string) $this->input('status');

        return $this->relay('list', [
            'start'   => (int) $this->input('start'),
            'length'  => (int) ($this->input('length') ?: 20),
            'filters' => in_array($status, ['unread', 'read'], true) ? ['status' => $status] : [],
        ]);
    }

    /**
     * GET admin/notifications/open/{public_id}: mark it read and go where it points; back to the notification centre
     * when it has nowhere to go or is gone
     * @param string $publicId
     * @return RedirectResponse
     */
    public function open(string $publicId): RedirectResponse
    {
        [$code, $body] = $this->callApi(self::API . 'read', ['public_id' => $publicId]);
        $target        = (string) ($body['data']['notification']['target_url'] ?? '');
        if ($code !== 200 || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return redirect()->to(base_url('admin/notifications'));
        }

        return redirect()->to(base_url(ltrim($target, '/')));
    }

    /**
     * POST admin/notifications/read-all
     * @return ResponseInterface
     */
    public function readAll(): ResponseInterface
    {
        return $this->relay('read-all', []);
    }

    /**
     * POST admin/notifications/delete { public_id }
     * @return ResponseInterface
     */
    public function delete(): ResponseInterface
    {
        return $this->relay('delete', ['public_id' => (string) $this->input('public_id')]);
    }

    /**
     * POST admin/notifications/delete-read
     * @return ResponseInterface
     */
    public function deleteRead(): ResponseInterface
    {
        return $this->relay('delete-read', []);
    }

    /**
     * The API's answer as { success, message, data } for the scripts
     * @param string $action
     * @param array  $payload
     * @return ResponseInterface
     */
    private function relay(string $action, array $payload): ResponseInterface
    {
        [$code, $body] = $this->callApi(self::API . $action, $payload);
        $ok            = $code === 200 && ($body['status'] ?? '') === 'success';

        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 422 : $code))->setJSON([
            'success' => $ok,
            'message' => $body['message'] ?? ($ok ? '' : 'Could not load the notifications. Please try again.'),
            'data'    => $ok ? ($body['data'] ?? []) : null,
        ]);
    }

    /**
     * A field of the JSON or form body
     * @param string $name
     * @return mixed
     */
    private function input(string $name): mixed
    {
        return $this->request->getJsonVar($name) ?? $this->request->getPost($name);
    }

    /**
     * POST to LINK_INTERNAL_API with the signed-in staff member's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array  $payload
     * @return array{int, array}
     */
    private function callApi(string $endpoint, array $payload): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                'json'        => (object) $payload,
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
