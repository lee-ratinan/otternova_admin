<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Only staff of the departments given may reach the routes this filter guards: 'department:EXE,FIN'.
 * Goes after 'auth'. Pages get the "no access" page; AJAX and other non-GET requests get a 403 JSON.
 * The API checks the department again on every call.
 */
class DepartmentFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');
        if (inDepartment((array) $arguments)) {
            return null;
        }

        $message = 'Your department does not have access to this page.';
        /** @var IncomingRequest $request */
        if ($request->isAJAX() || $request->getMethod() !== 'GET') {
            return service('response')->setStatusCode(403)->setJSON([
                'status'    => 'error',
                'success'   => false,
                'message'   => $message,
                'csrf_hash' => csrf_hash(),
            ]);
        }

        return service('response')->setStatusCode(403)->setBody(view('admin_forbidden', [
            'page_title' => 'No Access',
            'slug'       => 'forbidden',
            'message'    => $message,
        ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
