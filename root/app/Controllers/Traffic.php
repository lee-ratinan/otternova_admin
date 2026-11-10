<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * The traffic the tracker counted on the website (admin/traffic/www) and the marketplace (admin/traffic/mart), read
 * through LINK_INTERNAL_API v1/staff/traffic/*. The page itself is a shell: its script (assets/js/traffic.js) loads
 * each card through admin/traffic/data/*, which forwards the request to the API as it is, keeping only the fields
 * the API takes. ?from=&to= (YYYY-MM-DD) and, on the marketplace, ?business= (a business_slug) set the period and
 * the business, so a view can be linked to.
 */
class Traffic extends BaseController
{
    /** site => [page title, sidebar slug] */
    private const SITES = [
        'www'  => ['Website Traffic', 'traffic-www'],
        'mart' => ['Marketplace Traffic', 'traffic-mart'],
    ];

    /** The API endpoint behind each data route, and the fields of the request it takes */
    private const DATA = [
        'overview'   => ['site', 'business_slug', 'from', 'to'],
        'breakdown'  => ['site', 'business_slug', 'from', 'to', 'dimension', 'limit'],
        'visits'     => ['site', 'business_slug', 'from', 'to', 'start', 'length'],
        'businesses' => ['from', 'to', 'limit'],
    ];

    /**
     * admin/traffic: the website's
     * @return RedirectResponse
     */
    public function index(): RedirectResponse
    {
        return redirect()->to(base_url('admin/traffic/www'));
    }

    /**
     * admin/traffic/www or admin/traffic/mart
     * @param string $site
     * @return string
     */
    public function show(string $site): string
    {
        [$title, $slug] = self::SITES[$site];
        $get = fn (string $name): string => trim((string) $this->request->getGet($name));
        $date = static fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';

        return view('admin_traffic', [
            'page_title' => $title,
            'slug'       => $slug,
            'site'       => $site,
            'from'       => $date($get('from')),
            'to'         => $date($get('to')),
            'business'   => $site === 'mart' && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $get('business')) ? $get('business') : '',
        ]);
    }

    /**
     * POST admin/traffic/data/{overview|breakdown|visits|businesses}: the API's answer as it is,
     * { status, message?, data? }, with the API's HTTP status
     * @param string $endpoint
     * @return ResponseInterface
     */
    public function data(string $endpoint): ResponseInterface
    {
        $input = $this->request->getJSON(true);
        $input = is_array($input) ? $input : [];
        $payload = array_intersect_key($input, array_flip(self::DATA[$endpoint]));
        [$code, $body] = $this->callApi('v1/staff/traffic/' . $endpoint, $payload);

        return $this->response->setStatusCode($code)->setJSON($body + ['csrf_hash' => csrf_hash()]);
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
                'json'        => (object) $payload,
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
                return [503, ['status' => 'error', 'message' => 'The service is unavailable. Please try again later.']];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['status' => 'error', 'message' => 'The service is unavailable. Please try again later.']];
        }
    }
}
