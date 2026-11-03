<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('login', 'Home::login');
$routes->post('verify-otp', 'Home::verifyOtp');
$routes->post('change-password', 'Home::changePassword');
$routes->get('logout', 'Home::logout');

$routes->group('admin', ['filter' => 'auth'], static function($routes) {
    // Dashboard
    $routes->get('/', 'Admin::dashboard');
    $routes->get('dashboard', 'Admin::dashboard');
    // Users
    $routes->get('accounts', 'Admin::accounts');
    $routes->post('accounts', 'Admin::accountsList');
    $routes->get('accounts/create', 'Admin::accountsEdit/create');
    $routes->get('accounts/edit/(:any)', 'Admin::accountsEdit/$1');
    $routes->post('accounts/save', 'Admin::accountsEditPost');
    $routes->post('accounts/delete', 'Admin::accountsDelete');
    $routes->get('profile', 'Admin::profile');
    $routes->post('profile/save', 'Admin::profileEditPost');
    $routes->post('profile/password', 'Admin::profilePasswordPost');
    $routes->post('image-upload', 'Admin::imageUpload');
    $routes->post('image-delete', 'Admin::imageDelete');




    $routes->get('notifications', 'Admin::notifications');
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
    // Log Controller
    $routes->get('logs/errors', 'Logs::errors');
    $routes->get('logs/emails', 'Logs::emails');
    // Settings Controller
    $routes->get('settings/languages', 'Settings::languages'); // complete
    $routes->get('settings/countries', 'Settings::countries'); // complete
    $routes->get('settings/countries/view/(:alpha)', 'Settings::countriesView/$1');
});

// The ledger: staff of the Executive Office and Finance only (LEDGER_DEPARTMENTS; the API checks the same)
$routes->group('admin/ledger', ['filter' => ['auth', 'department:EXE,FIN']], static function($routes) {
    $routes->get('/', 'Ledger::entries');
    $routes->post('/', 'Ledger::entriesList');
    $routes->get('entries/create', 'Ledger::entriesEdit/create');
    $routes->get('entries/edit/(:num)', 'Ledger::entriesEdit/$1');
    $routes->post('entries/save', 'Ledger::entriesEditPost');
    $routes->post('entries/receipt-upload', 'Ledger::receiptUpload');
    $routes->post('entries/receipt-delete', 'Ledger::receiptDelete');
    $routes->get('entries/receipt/(:num)', 'Ledger::receipt/$1');
    // accounts, categories and counterparties share one set of pages
    $routes->get('(accounts|categories|counterparties)', 'Ledger::records/$1');
    $routes->post('(accounts|categories|counterparties)', 'Ledger::recordsList/$1');
    $routes->get('(accounts|categories|counterparties)/create', 'Ledger::recordsEdit/$1/create');
    $routes->get('(accounts|categories|counterparties)/edit/(:num)', 'Ledger::recordsEdit/$1/$2');
    $routes->post('(accounts|categories|counterparties)/save', 'Ledger::recordsEditPost/$1');
});
