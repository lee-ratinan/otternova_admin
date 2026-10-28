<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('admin', [], static function($routes) {
    // Admin Controller
    $routes->get('dashboard', 'Admin::dashboard');
    $routes->get('profile', 'Admin::profile');
    $routes->get('user', 'Admin::user');
    $routes->get('user/edit/(:any)', 'Admin::userEdit/$1');
    // Client Controller
    $routes->get('client', 'Client::index');
    $routes->get('client/view/(:any)', 'Client::view');
    // Business Controller
    $routes->get('business', 'Business::index');
    $routes->get('business/view/(:any)', 'Business::view/$1');
});