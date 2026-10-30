<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('admin', [], static function($routes) {
    // Admin Controller
    $routes->get('dashboard', 'Admin::dashboard');
    $routes->get('notifications', 'Admin::notifications');
    $routes->get('profile', 'Admin::profile/view');
    $routes->get('profile/edit', 'Admin::profile/edit');
    $routes->get('public-profile/(:any)', 'Admin::publicProfile/$1');
    $routes->get('inbox', 'Admin::inbox');
    // Our own users
    $routes->get('otternauts', 'Admin::otternauts');
    $routes->get('otternauts/edit/(:any)', 'Admin::otternautsEdit/$1');
    // Client Controller
    $routes->get('client/contracts', 'Client::contracts');
    $routes->get('client/businesses', 'Client::businesses');
    $routes->get('client/users', 'Client::users');
    $routes->get('client/reports', 'Client::reports');
    // Finance Controller
    $routes->get('finance/ledger', 'Finance::ledger');
    $routes->get('finance/report', 'Finance::report');
    // Log Controller
    $routes->get('logs/errors', 'Logs::errors');
    $routes->get('logs/emails', 'Logs::emails');
    // Settings Controller
    $routes->get('settings/languages', 'Settings::languages');
    $routes->get('settings/countries', 'Settings::countries');
});