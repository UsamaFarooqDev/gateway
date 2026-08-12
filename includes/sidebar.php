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

    <?php $showRides = Permission::can('gateway','rides','view') || Permission::can('gateway','dispatcher','view'); ?>
    <?php if ($showRides): ?>
    <div class="nav-section-label">Rides &amp; Dispatch</div>
    <?php if (Permission::can('gateway','rides','view')): ?>
    <?= navItem('rides',      'bi-car-front-fill', 'Ride Management',    $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','dispatcher','view')): ?>
    <?= navItem('dispatcher', 'bi-broadcast',      'Dispatcher Console', $currentPage) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php $showPeople = Permission::can('gateway','drivers','view') || Permission::can('gateway','passengers','view') || Permission::can('gateway','corporate','view'); ?>
    <?php if ($showPeople): ?>
    <div class="nav-section-label">People</div>
    <?php if (Permission::can('gateway','drivers','view')): ?>
    <?= navItem('drivers',    'bi-person-badge',   'Driver Management',    $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','passengers','view')): ?>
    <?= navItem('passengers', 'bi-people-fill',    'Passenger Management', $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','corporate','view')): ?>
    <?= navItem('corporate',  'bi-building',       'Corporate Accounts',   $currentPage) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php $showOps = Permission::can('gateway','fleet','view') || Permission::can('gateway','finance','view') || Permission::can('gateway','promotions','view') || Permission::can('gateway','zones','view'); ?>
    <?php if ($showOps): ?>
    <div class="nav-section-label">Operations</div>
    <?php if (Permission::can('gateway','fleet','view')): ?>
    <?= navItem('fleet',      'bi-truck-front-fill', 'Fleet Management',       $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','finance','view')): ?>
    <?= navItem('finance',    'bi-cash-coin',         'Finance &amp; Payments', $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','promotions','view')): ?>
    <?= navItem('promotions', 'bi-tag-fill',           'Promotions &amp; Pricing', $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','zones','view')): ?>
    <?= navItem('zones',      'bi-geo-alt-fill',       'Zones &amp; Coverage',   $currentPage) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php $showTools = Permission::can('gateway','notifications','view') || Permission::can('gateway','analytics','view') || Permission::can('gateway','support','view') || Permission::can('gateway','ratings','view'); ?>
    <?php if ($showTools): ?>
    <div class="nav-section-label">Tools</div>
    <?php if (Permission::can('gateway','notifications','view')): ?>
    <?= navItem('notifications', 'bi-bell-fill',       'Notifications &amp; Alerts', $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','analytics','view')): ?>
    <?= navItem('analytics',     'bi-bar-chart-fill',  'Analytics &amp; Reports',    $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','support','view')): ?>
    <?= navItem('support',       'bi-headset',         'Support &amp; Disputes',     $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','ratings','view')): ?>
    <?= navItem('ratings',       'bi-star-fill',       'Ratings &amp; Reviews',      $currentPage) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php $showSystem = Permission::can('gateway','settings','view') || Permission::can('gateway','admins','view') || Permission::can('gateway','integrations','view'); ?>
    <?php if ($showSystem): ?>
    <div class="nav-section-label">System</div>
    <?php if (Permission::can('gateway','settings','view')): ?>
    <?= navItem('settings',     'bi-gear-fill',    'Settings &amp; Config', $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','admins','view')): ?>
    <?= navItem('admins',       'bi-shield-lock',  'Admin Users',           $currentPage) ?>
    <?php endif; ?>
    <?php if (Permission::can('gateway','integrations','view')): ?>
    <?= navItem('integrations', 'bi-plug-fill',    'Integrations',          $currentPage) ?>
    <?php endif; ?>
    <?php endif; ?>

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
