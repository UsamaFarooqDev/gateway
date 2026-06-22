<?php
// $currentPage is set by each controller before including this file
$currentPage = $currentPage ?? 'dashboard';

$adminName  = $_SESSION['admin_name']  ?? 'Admin';
$adminRole  = $_SESSION['admin_role']  ?? 'super_admin';
$adminInitials = strtoupper(substr($adminName, 0, 1) . (strpos($adminName, ' ') !== false ? $adminName[strpos($adminName,' ')+1] : ''));

$roleLabels = [
    'super_admin'  => 'Super Admin',
    'dispatcher'   => 'Dispatcher',
    'finance'      => 'Finance',
    'support'      => 'Support',
    'fleet_manager'=> 'Fleet Manager',
];
$roleLabel = $roleLabels[$adminRole] ?? ucfirst($adminRole);

function navItem(string $page, string $icon, string $label, string $current, ?string $badge = null): string {
    $active = ($page === $current) ? ' active' : '';
    $url = '?page=' . $page;
    $badgeHtml = $badge ? "<span class='nav-badge'>{$badge}</span>" : '';
    return "<a href='{$url}' class='nav-item{$active}'>
        <i class='bi {$icon} nav-icon'></i>
        <span class='nav-label'>{$label}</span>
        {$badgeHtml}
    </a>";
}
?>
<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <a href="?page=dashboard" class="sidebar-logo">
    <img src="assets/img/logo.png" class="logo-icon" alt="PowerCabs">
    <!-- <div class="logo-text">
      <span class="tagline">Admin Panel</span>
    </div> -->
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav" id="sidebarNav">

    <?= navItem('dashboard', 'bi-speedometer2', 'Dashboard', $currentPage) ?>

    <div class="nav-section-label">Rides &amp; Dispatch</div>
    <?= navItem('rides',      'bi-car-front-fill', 'Ride Management',    $currentPage) ?>
    <?= navItem('dispatcher', 'bi-broadcast',      'Dispatcher Console', $currentPage) ?>

    <div class="nav-section-label">People</div>
    <?= navItem('drivers',    'bi-person-badge',       'Driver Management',   $currentPage) ?>
    <?= navItem('passengers', 'bi-people-fill',        'Passenger Management',$currentPage) ?>
    <?= navItem('corporate',  'bi-building',           'Corporate Accounts',  $currentPage) ?>

    <div class="nav-section-label">Operations</div>
    <?= navItem('fleet',      'bi-truck-front-fill',   'Fleet Management',    $currentPage) ?>
    <?= navItem('finance',    'bi-cash-coin',          'Finance &amp; Payments', $currentPage) ?>
    <?= navItem('promotions', 'bi-tag-fill',           'Promotions &amp; Pricing',$currentPage) ?>
    <?= navItem('zones',      'bi-geo-alt-fill',       'Zones &amp; Coverage',   $currentPage) ?>

    <div class="nav-section-label">Tools</div>
    <?= navItem('notifications','bi-bell-fill',        'Notifications &amp; Alerts', $currentPage) ?>
    <?= navItem('analytics',    'bi-bar-chart-fill',   'Analytics &amp; Reports', $currentPage) ?>
    <?= navItem('support',      'bi-headset',          'Support &amp; Disputes',  $currentPage) ?>
    <?= navItem('ratings',      'bi-star-fill',        'Ratings &amp; Reviews',   $currentPage) ?>

    <div class="nav-section-label">System</div>
    <?= navItem('settings',  'bi-gear-fill',      'Settings &amp; Config', $currentPage) ?>
    <?= navItem('admins',    'bi-shield-lock',    'Admin Users',           $currentPage) ?>
    <?= navItem('integrations','bi-plug-fill',    'Integrations',          $currentPage) ?>

  </nav>

  <!-- User -->
  <div class="sidebar-footer">
    <div class="sidebar-user" onclick="window.location='?page=admins'">
      <div class="user-avatar"><?= htmlspecialchars($adminInitials) ?></div>
      <div class="user-info">
        <div class="user-role"><?= htmlspecialchars($roleLabel) ?></div>
        <div class="user-name">Admin Panel</div>
      </div>
    </div>
  </div>

</aside>
