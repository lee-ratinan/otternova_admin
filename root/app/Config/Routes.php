<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('login', 'Home::login');
$routes->post('verify-otp', 'Home::verifyOtp');
$routes->post('change-password', 'Home::changePassword');
$routes->get('logout', 'Home::logout');
$routes->get('forgot-password', 'Home::forgotPassword');
$routes->post('forgot-password', 'Home::submitForgotPassword');
$routes->get('reset-password', 'Home::resetPassword');
$routes->post('reset-password', 'Home::submitResetPassword');

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
    // Businesses
    $routes->get('businesses', 'Admin::businesses');
    $routes->get('business-types', 'Admin::businessTypes');
    $routes->post('business-types', 'Admin::businessTypesList');
    $routes->get('business-types/create', 'Admin::businessTypesEdit/create');
    $routes->get('business-types/edit/(:segment)', 'Admin::businessTypesEdit/$1');
    $routes->post('business-types/save', 'Admin::businessTypesEditPost');




    // the signed-in staff member's notifications, by public_id; see App\Controllers\Notifications
    $routes->get('notifications', 'Notifications::index');
    $routes->post('notifications/latest', 'Notifications::latest');
    $routes->post('notifications/list', 'Notifications::list');
    $routes->get('notifications/open/(:segment)', 'Notifications::open/$1');
    $routes->post('notifications/read-all', 'Notifications::readAll');
    $routes->post('notifications/delete', 'Notifications::delete');
    $routes->post('notifications/delete-read', 'Notifications::deleteRead');
    // every staff member's public profile, by uid; see App\Controllers\Admin::publicProfiles()
    $routes->get('public-profiles', 'Admin::publicProfiles');
    $routes->get('public-profile/(:segment)', 'Admin::publicProfile/$1');
    // the contact form's messages, by their id; see App\Controllers\Admin::inbox()
    $routes->get('inbox', 'Admin::inbox');
    $routes->post('inbox', 'Admin::inboxList');
    $routes->get('inbox/view/(:num)', 'Admin::inboxView/$1');
    $routes->post('inbox/status', 'Admin::inboxStatus');
    // the website's and the marketplace's traffic, from the tracker; the page's cards load through traffic/data/*; see App\Controllers\Traffic
    $routes->get('traffic', 'Traffic::index');
    $routes->get('traffic/(www|mart)', 'Traffic::show/$1');
    $routes->post('traffic/data/(overview|breakdown|visits|businesses)', 'Traffic::data/$1');
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

    // JSON lookups for the pages' scripts; see App\Controllers\ApiList
    $routes->post('api/telephone-format-e164', 'ApiList::telephoneFormatE164');
    $routes->match(['GET', 'POST'], 'api/subdivisions', 'ApiList::subdivisions');
    $routes->match(['GET', 'POST'], 'api/currencies', 'ApiList::currencies');
});

// Plans, prices, payment methods, discounts and the businesses' subscriptions: staff of the Executive Office and Finance only (PLAN_DEPARTMENTS; the API checks the same)
$routes->group('admin', ['filter' => ['auth', 'department:EXE,FIN']], static function($routes) {
    // plans are in the URL by plan_code, payment methods by method_code, prices and discounts by public_id
    $routes->get('plans', 'Plans::plans');
    $routes->post('plans', 'Plans::plansList');
    $routes->get('plans/create', 'Plans::plansEdit/create');
    $routes->get('plans/edit/(:segment)', 'Plans::plansEdit/$1');
    $routes->post('plans/save', 'Plans::plansEditPost');
    $routes->get('plan-prices', 'Plans::prices');
    $routes->post('plan-prices', 'Plans::pricesList');
    $routes->get('plan-prices/create', 'Plans::pricesEdit/create');
    $routes->get('plan-prices/view/(:segment)', 'Plans::pricesEdit/$1');
    $routes->post('plan-prices/save', 'Plans::pricesEditPost');
    $routes->post('plan-prices/cancel', 'Plans::pricesCancel');
    $routes->get('payment-methods', 'Plans::paymentMethods');
    $routes->post('payment-methods', 'Plans::paymentMethodsList');
    $routes->get('payment-methods/create', 'Plans::paymentMethodsEdit/create');
    $routes->get('payment-methods/edit/(:segment)', 'Plans::paymentMethodsEdit/$1');
    $routes->post('payment-methods/save', 'Plans::paymentMethodsEditPost');
    $routes->get('discounts', 'Plans::discounts');
    $routes->post('discounts', 'Plans::discountsList');
    $routes->get('discounts/create', 'Plans::discountsEdit/create');
    $routes->get('discounts/edit/(:segment)', 'Plans::discountsEdit/$1');
    $routes->post('discounts/save', 'Plans::discountsEditPost');
    // the businesses' subscriptions, invoices and payments, by public_id; see App\Controllers\ClientBilling
    $routes->get('clients/subscriptions', 'ClientBilling::subscriptions');
    $routes->post('clients/subscriptions', 'ClientBilling::subscriptionsList');
    $routes->get('clients/subscriptions/view/(:segment)', 'ClientBilling::subscriptionView/$1');
    $routes->post('clients/subscriptions/status', 'ClientBilling::setStatus');
    // an invoice or receipt page as the business gets it, by its INV-/RCT- number; ?download=1 saves it
    $routes->get('clients/documents/(invoice|receipt)/(:segment)', 'ClientBilling::document/$1/$2');
    $routes->get('clients/invoices', 'ClientBilling::invoices');
    $routes->post('clients/invoices', 'ClientBilling::invoicesList');
    $routes->get('clients/invoices/view/(:segment)', 'ClientBilling::invoiceView/$1');
    $routes->get('clients/invoices/slip/(:segment)', 'ClientBilling::slip/$1');
    $routes->post('clients/invoices/payment/approve', 'ClientBilling::approvePayment');
    $routes->post('clients/invoices/payment/reject', 'ClientBilling::rejectPayment');
    $routes->get('clients/withholding', 'ClientBilling::withholding');
    $routes->post('clients/withholding/review', 'ClientBilling::reviewWithholding');
    $routes->post('clients/withholding/certificate/accept', 'ClientBilling::acceptCertificate');
    $routes->post('clients/withholding/certificate/reject', 'ClientBilling::rejectCertificate');
    $routes->get('clients/withholding/certificate/(:segment)', 'ClientBilling::certificateFile/$1');
});

// The activity log: staff of the Executive Office only (LOG_DEPARTMENTS; the API checks the same)
$routes->group('admin/logs', ['filter' => ['auth', 'department:EXE']], static function($routes) {
    // ?table=plan_master&id=12&search=PLUS links to one record's history; see App\Controllers\Logs
    $routes->get('activities', 'Logs::activities');
    $routes->post('activities', 'Logs::activitiesList');
});

// The ledger: staff of the Executive Office and Finance only (LEDGER_DEPARTMENTS; the API checks the same)
$routes->group('admin/ledger', ['filter' => ['auth', 'department:EXE,FIN']], static function($routes) {
    $routes->get('/', 'Ledger::entries');
    $routes->post('/', 'Ledger::entriesList');
    $routes->get('entries/create', 'Ledger::entriesEdit/create');
    $routes->get('entries/edit/(:segment)', 'Ledger::entriesEdit/$1');
    $routes->post('entries/save', 'Ledger::entriesEditPost');
    $routes->post('entries/receipt-upload', 'Ledger::receiptUpload');
    $routes->post('entries/receipt-delete', 'Ledger::receiptDelete');
    $routes->get('entries/receipt/(:segment)', 'Ledger::receipt/$1');
    // every record is in the URL by its public_id; accounts, categories and counterparties share one set of pages
    $routes->get('(accounts|categories|counterparties)', 'Ledger::records/$1');
    $routes->post('(accounts|categories|counterparties)', 'Ledger::recordsList/$1');
    $routes->get('(accounts|categories|counterparties)/create', 'Ledger::recordsEdit/$1/create');
    $routes->get('(accounts|categories|counterparties)/edit/(:segment)', 'Ledger::recordsEdit/$1/$2');
    $routes->post('(accounts|categories|counterparties)/save', 'Ledger::recordsEditPost/$1');
});
