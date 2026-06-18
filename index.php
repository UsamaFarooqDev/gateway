<?php
session_start();

// Business timezone — all date/time calculations use Dublin/Irish time
date_default_timezone_set('Europe/Dublin');

// Auth guard
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// Sanitise page param
$page = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['page'] ?? 'dashboard'));

// Route map: page slug → controller file + class
$routes = [
    'dashboard'     => ['file' => 'app/controllers/DashboardController.php',   'class' => 'DashboardController'],
    'drivers'       => ['file' => 'app/controllers/DriversController.php',     'class' => 'DriversController'],
    'passengers'    => ['file' => 'app/controllers/PassengersController.php',  'class' => 'PassengersController'],
    'rides'         => ['file' => 'app/controllers/RidesController.php', 'class' => 'RidesController'],
    'dispatcher'    => null,
    'corporate'     => null,
    'fleet'         => ['file' => 'app/controllers/FleetController.php', 'class' => 'FleetController'],
    'finance'       => ['file' => 'app/controllers/FinanceController.php', 'class' => 'FinanceController'],
    'promotions'    => ['file' => 'app/controllers/PromotionsController.php', 'class' => 'PromotionsController'],
    'zones'         => null,
    'notifications' => ['file' => 'app/controllers/NotificationsController.php', 'class' => 'NotificationsController'],
    'analytics'     => ['file' => 'app/controllers/AnalyticsController.php',      'class' => 'AnalyticsController'],
    'support'       => null,
    'ratings'       => null,
    'settings'      => null,
    'admins'        => null,
    'integrations'  => ['file' => 'app/controllers/IntegrationsController.php',   'class' => 'IntegrationsController'],
];

// Coming-soon page icons & labels
$pageLabels = [
    'rides'         => ['bi-car-front-fill',   'Ride Management'],
    'dispatcher'    => ['bi-broadcast',         'Dispatcher Console'],
    'corporate'     => ['bi-building',          'Corporate Accounts'],
    'fleet'         => ['bi-truck-front-fill',  'Fleet Management'],
    'finance'       => ['bi-cash-coin',         'Finance & Payments'],
    'promotions'    => ['bi-tag-fill',          'Promotions & Pricing'],
    'zones'         => ['bi-geo-alt-fill',      'Zones & Coverage'],
    'notifications' => ['bi-bell-fill',         'Notifications & Alerts'],
    'analytics'     => ['bi-bar-chart-fill',    'Analytics & Reports'],
    'support'       => ['bi-headset',           'Support & Disputes'],
    'ratings'       => ['bi-star-fill',         'Ratings & Reviews'],
    'settings'      => ['bi-gear-fill',         'Settings & Configuration'],
    'admins'        => ['bi-shield-person',     'Admin Users'],
    'integrations'  => ['bi-plug-fill',         'Integrations'],
];

if (!array_key_exists($page, $routes)) {
    $page = 'dashboard';
}

$route = $routes[$page];

if ($route !== null && file_exists($route['file'])) {
    require_once $route['file'];
    $controller = new $route['class'](getDB());
    $controller->index();
} else {
    $info = $pageLabels[$page] ?? ['bi-grid', ucfirst($page)];
    $currentPage = $page;
    $pageTitle   = $info[1];
    $pageCrumbs  = [$info[1]];

    $viewFile = "app/views/{$page}/index.php";

    require_once 'includes/header.php';

    if (file_exists($viewFile)) {
        require_once $viewFile;
    } else {
        ?>
        <div class="coming-soon-page">
          <div class="cs-icon"><i class="bi <?= htmlspecialchars($info[0]) ?>"></i></div>
          <h2><?= htmlspecialchars($info[1]) ?></h2>
          <p>This module is under construction and will be available soon.</p>
          <a href="?page=dashboard" class="btn-primary-glass mt-3">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
          </a>
        </div>
        <?php
    }

    require_once 'includes/footer.php';
}
