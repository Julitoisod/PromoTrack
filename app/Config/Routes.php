<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index', ['filter' => 'session']);

service('auth')->routes($routes, ['except' => ['register']]);

// Registration collects the PromoTrack profile fields (unit, AFP serial, full name)
// that Shield's own RegisterController does not know about.
$routes->get('register', 'Auth\RegisterController::registerView', ['as' => 'register']);
$routes->post('register', 'Auth\RegisterController::registerAction');

// The reservist's own surface. No record id in the URL: the record is
// always the one the session's account owns, so there is nothing here to
// tamper with. Uploads post to the shared package-documents routes, which
// re-check ownership via BaseController::canAccessPackageRow().
$routes->get('my/promotion', 'MyPromotionController::index', [
    'filter' => ['session', 'permission:promotions.view_own'],
]);
$routes->post('my/promotion/package', 'MyPromotionController::openPackage', [
    'filter' => ['session', 'permission:promotions.view_own'],
]);

$routes->group('reservists', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ReservistsController::index', ['filter' => 'permission:reservists.view']);
    $routes->get('create', 'ReservistsController::create', ['filter' => 'permission:reservists.create']);
    $routes->post('/', 'ReservistsController::store', ['filter' => 'permission:reservists.create']);
    $routes->get('(:num)', 'ReservistsController::show/$1', [
        'filter' => ['permission:reservists.view', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->get('(:num)/edit', 'ReservistsController::edit/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->put('(:num)', 'ReservistsController::update/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->post('(:num)', 'ReservistsController::update/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->delete('(:num)', 'ReservistsController::delete/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->post('(:num)/delete', 'ReservistsController::delete/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->post('(:num)/assessments', 'PromotionAssessmentsController::store/$1', [
        'filter' => ['permission:assessments.compute', 'unit-access:reservists,organizational_unit_id'],
    ]);

    // Service records feeding EligibilityService: trainings, fitness-tests,
    // eligibilities, rank-history. See ReservistRecordsController::TYPES.
    $routes->post('(:num)/records/(:segment)', 'ReservistRecordsController::store/$2/$1', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
    $routes->post('(:num)/records/(:segment)/(:num)/delete', 'ReservistRecordsController::delete/$2/$1/$3', [
        'filter' => ['permission:reservists.update', 'unit-access:reservists,organizational_unit_id'],
    ]);
});

$routes->post('assessments/(:num)/verify', 'PromotionAssessmentsController::verify/$1', [
    'filter' => ['session', 'permission:assessments.verify'],
]);

$routes->group('packages', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PromotionPackagesController::index', ['filter' => 'permission:reservists.view']);
    $routes->get('(:num)', 'PromotionPackagesController::show/$1', [
        'filter' => ['permission:reservists.view', 'unit-access:promotion_packages,organizational_unit_id'],
    ]);
    $routes->post('(:num)/submit', 'PromotionPackagesController::submit/$1', ['filter' => 'permission:packages.submit_to_cdc']);
});

$routes->post('reservists/(:num)/packages', 'PromotionPackagesController::store/$1', [
    'filter' => ['session', 'permission:packages.create', 'unit-access:reservists,organizational_unit_id'],
]);

$routes->group('package-documents', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->post('(:num)/upload', 'PackageDocumentsController::upload/$1', ['filter' => 'permission:documents.upload']);
    $routes->post('(:num)/verify', 'PackageDocumentsController::verify/$1', ['filter' => 'permission:documents.verify']);
    $routes->post('(:num)/reject', 'PackageDocumentsController::reject/$1', ['filter' => 'permission:documents.reject']);
    $routes->post('(:num)/waive', 'PackageDocumentsController::waive/$1', ['filter' => 'permission:waivers.approve']);
    // Two permissions, OR-ed by Shield's filter: staff reach any document in
    // their unit, a reservist reaches the ones on their own package. Which of
    // the two applies is decided per record by canAccessPackageRow(), not
    // here -- gating this on `reservists.view` alone locked reservists out of
    // the very files they had just uploaded.
    $routes->get('versions/(:num)/download', 'PackageDocumentsController::download/$1', ['filter' => 'permission:reservists.view,promotions.view_own']);
});

$routes->group('cdc', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->post('packages/(:num)/review', 'CDC\ValidationController::review/$1', ['filter' => 'permission:packages.validate_at_cdc']);
    $routes->post('packages/(:num)/validate', 'CDC\ValidationController::validatePackage/$1', ['filter' => 'permission:packages.validate_at_cdc']);
    $routes->post('packages/(:num)/return', 'CDC\ValidationController::returnToUnit/$1', ['filter' => 'permission:packages.return']);
    $routes->post('packages/(:num)/submit-to-rcdg', 'CDC\ValidationController::submitToRcdg/$1', ['filter' => 'permission:packages.submit_to_rcdg']);
    $routes->post('packages/(:num)/resubmit-to-rcdg', 'CDC\ValidationController::resubmitToRcdg/$1', ['filter' => 'permission:packages.submit_to_rcdg']);
});

$routes->group('rcdg', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->post('packages/(:num)/review', 'RCDG\ReviewController::review/$1', ['filter' => 'permission:packages.review_at_rcdg']);
    $routes->post('packages/(:num)/endorse', 'RCDG\ReviewController::endorse/$1', ['filter' => 'permission:packages.endorse']);
    $routes->post('packages/(:num)/return', 'RCDG\ReviewController::returnToUnit/$1', ['filter' => 'permission:packages.return']);
});

$routes->group('records', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->post('packages/(:num)/forward', 'Records\ForwardingController::forward/$1', ['filter' => 'permission:forwarding.record']);
    $routes->post('packages/(:num)/decisions', 'Records\DecisionsController::record/$1', ['filter' => 'permission:external_decisions.record']);
    $routes->post('packages/(:num)/resume', 'Records\DecisionsController::resume/$1', ['filter' => 'permission:external_decisions.record']);
    $routes->post('packages/(:num)/promotion-order', 'PromotionOrdersController::record/$1', ['filter' => 'permission:promotion_orders.record']);
});

$routes->group('api/notifications', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Api\NotificationsController::index');
    $routes->post('(:num)/read', 'Api\NotificationsController::markRead/$1');
});

$routes->group('admin', ['filter' => 'session'], static function (RouteCollection $routes): void {
    // Reference data: units, ranks, training-types, education-tracks.
    // See Admin\ReferenceDataController::SETS.
    $routes->get('reference', 'Admin\ReferenceDataController::index', ['filter' => 'permission:units.manage']);
    $routes->get('reference/(:segment)', 'Admin\ReferenceDataController::index/$1', ['filter' => 'permission:units.manage']);
    $routes->post('reference/(:segment)', 'Admin\ReferenceDataController::store/$1', ['filter' => 'permission:units.manage']);
    $routes->post('reference/(:segment)/(:num)', 'Admin\ReferenceDataController::update/$1/$2', ['filter' => 'permission:units.manage']);
    $routes->post('reference/(:segment)/(:num)/toggle', 'Admin\ReferenceDataController::toggle/$1/$2', ['filter' => 'permission:units.manage']);

    // Versioned promotion rules. A threshold change inserts a new version and
    // expires the old one; see Admin\PromotionRulesController::storeVersion().
    $routes->get('rules', 'Admin\PromotionRulesController::index', ['filter' => 'permission:rules.manage']);
    $routes->post('rules', 'Admin\PromotionRulesController::store', ['filter' => 'permission:rules.manage']);
    $routes->get('rules/(:num)', 'Admin\PromotionRulesController::show/$1', ['filter' => 'permission:rules.manage']);
    $routes->post('rules/(:num)/versions', 'Admin\PromotionRulesController::storeVersion/$1', ['filter' => 'permission:rules.manage']);
    $routes->post('rules/(:num)/toggle', 'Admin\PromotionRulesController::toggle/$1', ['filter' => 'permission:rules.manage']);

    // Append-only audit trail.
    $routes->get('audit-logs', 'Admin\AuditLogsController::index', ['filter' => 'permission:audit_logs.view']);
    $routes->get('audit-logs/export', 'Admin\AuditLogsController::export', ['filter' => 'permission:audit_logs.view']);

    // System settings and database backups.
    $routes->get('settings', 'Admin\SettingsController::index', ['filter' => 'permission:settings.manage']);
    $routes->post('settings', 'Admin\SettingsController::save', ['filter' => 'permission:settings.manage']);
    $routes->post('backups', 'Admin\SettingsController::createBackup', ['filter' => 'permission:backups.manage']);
    $routes->get('backups/(:segment)/download', 'Admin\SettingsController::downloadBackup/$1', ['filter' => 'permission:backups.manage']);
    $routes->post('backups/(:segment)/delete', 'Admin\SettingsController::deleteBackup/$1', ['filter' => 'permission:backups.manage']);
});

$routes->group('reports', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('backlog', 'ReportsController::backlog', ['filter' => 'permission:reports.generate']);
    $routes->get('backlog.csv', 'ReportsController::backlogCsv', ['filter' => 'permission:reports.generate']);
    $routes->get('promotion-orders/(:num)', 'ReportsController::promotionOrder/$1', ['filter' => 'permission:reports.generate']);
    $routes->get('packages/(:num)/endorsements', 'ReportsController::endorsements/$1', ['filter' => 'permission:reports.generate']);
});

