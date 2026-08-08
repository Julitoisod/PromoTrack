<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index', ['filter' => 'session']);

service('auth')->routes($routes);

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
    $routes->get('versions/(:num)/download', 'PackageDocumentsController::download/$1', ['filter' => 'permission:reservists.view']);
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

$routes->group('reports', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('backlog', 'ReportsController::backlog', ['filter' => 'permission:reports.generate']);
    $routes->get('promotion-orders/(:num)', 'ReportsController::promotionOrder/$1', ['filter' => 'permission:reports.generate']);
    $routes->get('packages/(:num)/endorsements', 'ReportsController::endorsements/$1', ['filter' => 'permission:reports.generate']);
});

