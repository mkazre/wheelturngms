<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ------------------------------------------------------------------ public
$routes->get('/', static fn () => redirect()->to(site_url(session('user_id') ? (session('role') === 'customer' ? 'portal' : 'dashboard') : 'login')));

$routes->group('', ['filter' => 'guest'], static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attempt');
    $routes->get('forgot-password', 'Auth::forgot');
    $routes->post('forgot-password', 'Auth::sendReset');
    $routes->get('reset-password/(:segment)', 'Auth::reset/$1');
    $routes->post('reset-password/(:segment)', 'Auth::doReset/$1');
});
$routes->get('logout', 'Auth::logout');

// Customer-facing invoice payment & quote approval (token secured)
$routes->get('pay/(:segment)', 'Pay::show/$1');
$routes->get('pay/(:segment)/pdf', 'Pay::pdf/$1');
$routes->get('pay/(:segment)/success', 'Pay::success/$1');
$routes->post('pay/(:segment)/payfast', 'Pay::payfast/$1');
$routes->post('pay/(:segment)/ozow', 'Pay::ozow/$1');
$routes->get('q/(:segment)', 'Pay::quote/$1');
$routes->get('q/(:segment)/pdf', 'Pay::quotePdf/$1');
$routes->post('q/(:segment)/respond', 'Pay::quoteRespond/$1');
$routes->post('webhooks/payfast', 'Webhooks::payfast');
$routes->post('webhooks/ozow', 'Webhooks::ozow');

// ------------------------------------------------------------------ staff app
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth:dashboard']);
$routes->get('search', 'Search::index', ['filter' => 'auth']);
$routes->get('profile', 'Profile::index', ['filter' => 'auth']);
$routes->post('profile', 'Profile::update', ['filter' => 'auth']);

$routes->group('workshop', ['filter' => 'auth:workshop'], static function ($routes) {
    $routes->get('/', 'Workshop::index');
});

$routes->group('customers', ['filter' => 'auth:customers'], static function ($routes) {
    $routes->get('/', 'Customers::index');
    $routes->get('export/(:alpha)', 'Customers::export/$1');
    $routes->get('lookup', 'Customers::lookup');
    $routes->get('new', 'Customers::form');
    $routes->post('save', 'Customers::save');
    $routes->get('(:num)', 'Customers::show/$1');
    $routes->get('(:num)/edit', 'Customers::form/$1');
    $routes->post('(:num)/save', 'Customers::save/$1');
    $routes->post('(:num)/delete', 'Customers::delete/$1');
    $routes->get('(:num)/statement', 'Customers::statement/$1');
    $routes->post('(:num)/portal-access', 'Customers::portalAccess/$1');
});

$routes->group('vehicles', ['filter' => 'auth:vehicles'], static function ($routes) {
    $routes->get('/', 'Vehicles::index');
    $routes->get('export/(:alpha)', 'Vehicles::export/$1');
    $routes->get('lookup', 'Vehicles::lookup');
    $routes->get('new', 'Vehicles::form');
    $routes->post('save', 'Vehicles::save');
    $routes->get('(:num)', 'Vehicles::show/$1');
    $routes->get('(:num)/edit', 'Vehicles::form/$1');
    $routes->post('(:num)/save', 'Vehicles::save/$1');
    $routes->post('(:num)/delete', 'Vehicles::delete/$1');
});

$routes->group('job-cards', ['filter' => 'auth:job_cards'], static function ($routes) {
    $routes->get('/', 'JobCards::index');
    $routes->get('export/(:alpha)', 'JobCards::export/$1');
    $routes->get('new', 'JobCards::form');
    $routes->post('save', 'JobCards::save');
    $routes->get('(:num)', 'JobCards::show/$1');
    $routes->get('(:num)/edit', 'JobCards::form/$1');
    $routes->post('(:num)/save', 'JobCards::save/$1');
    $routes->post('(:num)/status', 'JobCards::status/$1');
    $routes->post('(:num)/inspection', 'JobCards::inspection/$1');
    $routes->post('(:num)/timer', 'JobCards::timer/$1');
    $routes->post('(:num)/signature', 'JobCards::signature/$1');
    $routes->post('(:num)/invoice', 'JobCards::invoice/$1');
    $routes->post('(:num)/delete', 'JobCards::delete/$1');
    $routes->get('(:num)/pdf', 'JobCards::pdf/$1');
});

$routes->group('quotes', ['filter' => 'auth:quotes'], static function ($routes) {
    $routes->get('/', 'Quotes::index');
    $routes->get('export/(:alpha)', 'Quotes::export/$1');
    $routes->get('new', 'Quotes::form');
    $routes->post('save', 'Quotes::save');
    $routes->get('(:num)', 'Quotes::show/$1');
    $routes->get('(:num)/edit', 'Quotes::form/$1');
    $routes->post('(:num)/save', 'Quotes::save/$1');
    $routes->get('(:num)/pdf', 'Quotes::pdf/$1');
    $routes->post('(:num)/email', 'Quotes::email/$1');
    $routes->post('(:num)/status', 'Quotes::status/$1');
    $routes->post('(:num)/convert', 'Quotes::convert/$1');
    $routes->post('(:num)/delete', 'Quotes::delete/$1');
});

$routes->group('invoices', ['filter' => 'auth:invoices'], static function ($routes) {
    $routes->get('/', 'Invoices::index');
    $routes->get('export/(:alpha)', 'Invoices::export/$1');
    $routes->get('new', 'Invoices::form');
    $routes->post('save', 'Invoices::save');
    $routes->get('(:num)', 'Invoices::show/$1');
    $routes->get('(:num)/edit', 'Invoices::form/$1');
    $routes->post('(:num)/save', 'Invoices::save/$1');
    $routes->get('(:num)/pdf', 'Invoices::pdf/$1');
    $routes->post('(:num)/email', 'Invoices::email/$1');
    $routes->post('(:num)/remind', 'Invoices::remind/$1');
    $routes->post('(:num)/payment', 'Invoices::payment/$1');
    $routes->post('(:num)/void', 'Invoices::void/$1');
});

$routes->group('payments', ['filter' => 'auth:payments'], static function ($routes) {
    $routes->get('/', 'Payments::index');
    $routes->get('export/(:alpha)', 'Payments::export/$1');
    $routes->get('(:num)/receipt', 'Payments::receipt/$1');
    $routes->post('(:num)/delete', 'Payments::delete/$1');
});

$routes->group('expenses', ['filter' => 'auth:expenses'], static function ($routes) {
    $routes->get('/', 'Expenses::index');
    $routes->get('export/(:alpha)', 'Expenses::export/$1');
    $routes->get('new', 'Expenses::form');
    $routes->post('save', 'Expenses::save');
    $routes->get('(:num)/edit', 'Expenses::form/$1');
    $routes->post('(:num)/save', 'Expenses::save/$1');
    $routes->post('(:num)/delete', 'Expenses::delete/$1');
});

$routes->group('inventory', ['filter' => 'auth:inventory'], static function ($routes) {
    $routes->get('/', 'Products::index');
    $routes->get('export/(:alpha)', 'Products::export/$1');
    $routes->get('lookup', 'Products::lookup');
    $routes->get('new', 'Products::form');
    $routes->post('save', 'Products::save');
    $routes->get('(:num)/edit', 'Products::form/$1');
    $routes->post('(:num)/save', 'Products::save/$1');
    $routes->post('(:num)/adjust', 'Products::adjust/$1');
    $routes->post('(:num)/delete', 'Products::delete/$1');
});

$routes->group('suppliers', ['filter' => 'auth:suppliers'], static function ($routes) {
    $routes->get('/', 'Suppliers::index');
    $routes->get('new', 'Suppliers::form');
    $routes->post('save', 'Suppliers::save');
    $routes->get('(:num)/edit', 'Suppliers::form/$1');
    $routes->post('(:num)/save', 'Suppliers::save/$1');
    $routes->post('(:num)/delete', 'Suppliers::delete/$1');
});

$routes->group('attachments', ['filter' => 'auth'], static function ($routes) {
    $routes->post('upload', 'Attachments::upload');
    $routes->get('(:num)', 'Attachments::view/$1');
    $routes->get('(:num)/download', 'Attachments::download/$1');
    $routes->post('(:num)/delete', 'Attachments::delete/$1');
});

$routes->group('reports', ['filter' => 'auth:reports'], static function ($routes) {
    $routes->get('/', 'Reports::index');
    $routes->get('(:segment)', 'Reports::show/$1');
    $routes->get('(:segment)/export/(:alpha)', 'Reports::export/$1/$2');
    $routes->post('weekly/send', 'Reports::sendWeekly');
});

$routes->group('users', ['filter' => 'auth:users'], static function ($routes) {
    $routes->get('/', 'Users::index');
    $routes->get('new', 'Users::form');
    $routes->post('save', 'Users::save');
    $routes->get('(:num)/edit', 'Users::form/$1');
    $routes->post('(:num)/save', 'Users::save/$1');
    $routes->post('(:num)/delete', 'Users::delete/$1');
});

$routes->group('settings', ['filter' => 'auth:settings'], static function ($routes) {
    $routes->get('/', 'Settings::index');
    $routes->get('(:segment)', 'Settings::index/$1');
    $routes->post('(:segment)', 'Settings::save/$1');
    $routes->post('email/test', 'Settings::testEmail');
    $routes->post('branches/save', 'Settings::saveBranch');
});

// ------------------------------------------------------------------ customer portal (web)
$routes->group('portal', ['filter' => 'auth:portal'], static function ($routes) {
    $routes->get('/', 'Portal::index');
});

// ------------------------------------------------------------------ Phase 2: accounting
$routes->group('credit-notes', ['filter' => 'auth:invoices'], static function ($routes) {
    $routes->get('/', 'CreditNotes::index');
    $routes->get('export/(:alpha)', 'CreditNotes::export/$1');
    $routes->get('new', 'CreditNotes::form');
    $routes->post('save', 'CreditNotes::save');
    $routes->get('(:num)', 'CreditNotes::show/$1');
    $routes->get('(:num)/pdf', 'CreditNotes::pdf/$1');
    $routes->post('(:num)/void', 'CreditNotes::void/$1');
});

$routes->group('purchase-orders', ['filter' => 'auth:purchases'], static function ($routes) {
    $routes->get('/', 'PurchaseOrders::index');
    $routes->get('export/(:alpha)', 'PurchaseOrders::export/$1');
    $routes->get('new', 'PurchaseOrders::form');
    $routes->post('save', 'PurchaseOrders::save');
    $routes->get('(:num)', 'PurchaseOrders::show/$1');
    $routes->get('(:num)/edit', 'PurchaseOrders::form/$1');
    $routes->post('(:num)/save', 'PurchaseOrders::save/$1');
    $routes->get('(:num)/pdf', 'PurchaseOrders::pdf/$1');
    $routes->post('(:num)/email', 'PurchaseOrders::email/$1');
    $routes->post('(:num)/receive', 'PurchaseOrders::receive/$1');
    $routes->post('(:num)/cancel', 'PurchaseOrders::cancel/$1');
});

$routes->group('bills', ['filter' => 'auth:purchases'], static function ($routes) {
    $routes->get('/', 'Bills::index');
    $routes->get('export/(:alpha)', 'Bills::export/$1');
    $routes->get('new', 'Bills::form');
    $routes->post('save', 'Bills::save');
    $routes->get('(:num)', 'Bills::show/$1');
    $routes->get('(:num)/edit', 'Bills::form/$1');
    $routes->post('(:num)/save', 'Bills::save/$1');
    $routes->post('(:num)/pay', 'Bills::pay/$1');
    $routes->post('(:num)/void', 'Bills::void/$1');
});

$routes->group('accounting', ['filter' => 'auth:accounting'], static function ($routes) {
    $routes->get('/', 'Accounting::index');
    $routes->get('accounts', 'Accounting::accounts');
    $routes->post('accounts/save', 'Accounting::saveAccount');
    $routes->get('ledger/(:num)', 'Accounting::ledger/$1');
    $routes->get('journals', 'Accounting::journals');
    $routes->get('journals/new', 'Accounting::journalForm');
    $routes->post('journals/save', 'Accounting::journalSave');
    $routes->get('journals/(:num)', 'Accounting::journal/$1');
    $routes->post('journals/(:num)/reverse', 'Accounting::journalReverse/$1');
    $routes->post('lock', 'Accounting::lock');
    $routes->get('vat', 'Accounting::vat');
    $routes->post('vat/create', 'Accounting::vatCreate');
    $routes->post('vat/(:num)/pay', 'Accounting::vatPay/$1');
});

$routes->group('banking', ['filter' => 'auth:accounting'], static function ($routes) {
    $routes->get('(:num)', 'Banking::show/$1');
    $routes->post('(:num)/upload', 'Banking::upload/$1');
    $routes->post('(:num)/import', 'Banking::import/$1');
    $routes->post('(:num)/auto-match', 'Banking::autoMatch/$1');
    $routes->post('txn/(:num)/match', 'Banking::match/$1');
    $routes->post('txn/(:num)/create', 'Banking::create/$1');
    $routes->post('txn/(:num)/ignore', 'Banking::ignore/$1');
    $routes->post('txn/(:num)/unmatch', 'Banking::unmatch/$1');
});

// ------------------------------------------------------------------ Phase 4A: workshop operations
// Public
$routes->get('book', 'Diary::bookForm');
$routes->post('book', 'Diary::bookStore');
$routes->get('track/(:segment)', 'Track::show/$1');
$routes->get('track/(:segment)/job-card', 'Track::jobCardPdf/$1');
$routes->get('track/(:segment)/file/(:num)', 'Track::file/$1/$2');
$routes->post('track/(:segment)/decide', 'Track::decide/$1');

$routes->group('', ['filter' => 'auth:job_cards'], static function ($routes) {
    $routes->post('job-cards/(:num)/checklist', 'JobCards::checklist/$1');
    $routes->post('job-cards/(:num)/damage', 'JobCards::damage/$1');
});
$routes->group('', ['filter' => 'auth:workshop'], static function ($routes) {
    $routes->get('workshop/tv', 'Workshop::tv');
    $routes->get('diary', 'Diary::index');
    $routes->get('diary/events', 'Diary::events');
    $routes->post('diary/move', 'Diary::move');
    $routes->post('diary/bookings/(:num)/confirm', 'Diary::confirm/$1');
    $routes->post('diary/bookings/(:num)/decline', 'Diary::decline/$1');
    $routes->get('loans', 'Setup::loans');
    $routes->post('loans/vehicle', 'Setup::saveLoanVehicle');
    $routes->post('loans/out', 'Setup::loanOut');
    $routes->post('loans/(:num)/return', 'Setup::loanReturn/$1');
});
$routes->group('', ['filter' => 'auth:inventory'], static function ($routes) {
    $routes->get('canned-jobs', 'CannedJobs::index');
    $routes->get('canned-jobs/new', 'CannedJobs::form');
    $routes->post('canned-jobs/save', 'CannedJobs::save');
    $routes->get('canned-jobs/(:num)/edit', 'CannedJobs::form/$1');
    $routes->post('canned-jobs/(:num)/save', 'CannedJobs::save/$1');
    $routes->post('canned-jobs/(:num)/delete', 'CannedJobs::delete/$1');
});
$routes->get('canned-jobs/(:num)/lines', 'CannedJobs::lines/$1', ['filter' => 'auth:job_cards']);
$routes->get('checklists', 'Setup::checklists', ['filter' => 'auth:settings']);
$routes->post('checklists/save', 'Setup::saveChecklist', ['filter' => 'auth:settings']);

// ------------------------------------------------------------------ Phase 4B: customer engagement
$routes->get('feedback/(:segment)', 'Engage::feedbackForm/$1');
$routes->post('feedback/(:segment)', 'Engage::feedbackSubmit/$1');
$routes->group('', ['filter' => 'auth:customers'], static function ($routes) {
    $routes->get('follow-ups', 'Engage::followUps');
    $routes->post('follow-ups/(:num)/done', 'Engage::followUpDone/$1');
    $routes->get('feedback', 'Engage::feedback');
    $routes->get('campaigns', 'Engage::campaigns');
    $routes->post('campaigns/send', 'Engage::sendCampaign');
    $routes->post('campaigns/statements', 'Engage::sendStatements');
});
$routes->get('job-cards/(:num)/health-report', 'JobCards::healthReport/$1', ['filter' => 'auth:job_cards']);
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('tasks', 'Tasks::index');
    $routes->post('tasks/save', 'Tasks::save');
    $routes->post('tasks/(:num)/complete', 'Tasks::complete/$1');
    $routes->get('notifications', 'Tasks::notifications');
});

// ------------------------------------------------------------------ Phase 4C: stock & finance
$routes->group('', ['filter' => 'auth:payments'], static function ($routes) {
    $routes->get('deposits', 'Deposits::index');
    $routes->post('deposits/save', 'Deposits::save');
    $routes->post('deposits/(:num)/refund', 'Deposits::refund/$1');
});
$routes->group('', ['filter' => 'auth:inventory'], static function ($routes) {
    $routes->get('stock/barcode', 'Stock::barcode');
    $routes->get('stock/pricing', 'Stock::pricing');
    $routes->post('stock/pricing', 'Stock::savePricing');
    $routes->get('stock-takes', 'Stock::takes');
    $routes->post('stock-takes/new', 'Stock::newTake');
    $routes->get('stock-takes/(:num)', 'Stock::take/$1');
    $routes->post('stock-takes/(:num)/save', 'Stock::saveCounts/$1');
});
$routes->group('', ['filter' => 'auth:purchases'], static function ($routes) {
    $routes->get('stock/reorder', 'Stock::reorder');
    $routes->post('stock/reorder', 'Stock::createOrders');
});
$routes->group('exports', ['filter' => 'auth:accounting'], static function ($routes) {
    $routes->get('/', 'Exports::index');
    $routes->get('xero-journals', 'Exports::xeroJournals');
    $routes->get('xero-invoices', 'Exports::xeroInvoices');
    $routes->get('sage-journals', 'Exports::sageJournals');
    $routes->get('chart', 'Exports::chart');
});

// ------------------------------------------------------------------ Phase 4D: platform & intelligence
$routes->get('login/2fa', 'Auth::twoFactor');
$routes->post('login/2fa', 'Auth::twoFactorVerify');
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->post('profile/2fa', 'Profile::twoFactor');
    $routes->get('palette', 'Admin::palette');
    $routes->post('branch', 'Admin::switchBranch');
});
$routes->group('', ['filter' => 'auth:job_cards'], static function ($routes) {
    $routes->get('assistant', 'Assistant::index');
    $routes->post('assistant/ask', 'Assistant::ask');
    $routes->post('assistant/fault-codes', 'Assistant::faultCodes');
    $routes->post('assistant/summary/(:num)', 'Assistant::summary/$1');
    $routes->post('assistant/upsell/(:num)', 'Assistant::upsell/$1');
});
$routes->group('', ['filter' => 'auth:vehicles'], static function ($routes) {
    $routes->get('vehicles/decode-vin', 'Vehicles::decodeVin');
    $routes->post('vehicles/(:num)/transfer', 'Vehicles::transfer/$1');
});
$routes->group('admin', ['filter' => 'auth:settings'], static function ($routes) {
    $routes->get('audit', 'Admin::audit');
    $routes->get('backups', 'Admin::backups');
    $routes->post('backups/run', 'Admin::backupNow');
    $routes->get('backups/(:segment)', 'Admin::backupDownload/$1');
    $routes->get('import', 'Admin::import');
    $routes->post('import/upload', 'Admin::importUpload');
    $routes->post('import/run', 'Admin::importRun');
});

// ------------------------------------------------------------------ mobile API (Phase 3)
$routes->group('api/v1', static function ($routes) {
    $routes->post('auth/login', 'Api\Auth::login');
    $routes->get('me', 'Api\Auth::me', ['filter' => 'api']);
});
