<?php
// $pageTitle and $pageCrumbs are set by each controller
$pageTitle  = $pageTitle  ?? 'Dashboard';
$pageCrumbs = $pageCrumbs ?? [];
$adminName  = $_SESSION['admin_name'] ?? 'Admin';
$adminInitials = strtoupper(substr($adminName, 0, 1) . (strpos($adminName, ' ') !== false ? $adminName[strpos($adminName,' ')+1] : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — PowerCabs Admin</title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Theme -->
  <link rel="stylesheet" href="assets/css/theme.css">

  <link rel="icon" href="assets/img/logo.png" type="image/svg+xml">
  <script>
  if(localStorage.getItem('pc_sidebar_collapsed')==='1'){
    document.documentElement.classList.add('sidebar-will-collapse');
  }
  </script>
  <style>
  .sidebar-will-collapse .sidebar{width:72px!important}
  .sidebar-will-collapse .page-body{margin-left:72px!important;width:calc(100% - 72px)!important}
  .sidebar-will-collapse .top-header{left:72px!important}
  </style>
</head>
<body>

<div class="admin-shell">
<?php require_once 'includes/sidebar.php'; ?>

<div class="page-body" id="pageBody">

  <!-- Top Header -->
  <header class="top-header" id="topHeader">

    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>

    <div class="page-breadcrumb">
      <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
      <?php if (!empty($pageCrumbs)): ?>
      <div class="breadcrumb-trail">
        <i class="bi bi-house" style="font-size:11px"></i>
        <?php foreach ($pageCrumbs as $i => $crumb): ?>
          <?php if (is_array($crumb)): ?>
            <span>/</span><a href="<?= htmlspecialchars($crumb['url']) ?>" style="color:var(--text-subtle);text-decoration:none"><?= htmlspecialchars($crumb['label']) ?></a>
          <?php else: ?>
            <span>/</span><span><?= htmlspecialchars($crumb) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="header-actions">
      <!-- Notifications -->
      <div style="position:relative">
        <button class="header-btn" id="notifBtn" title="Notifications">
          <i class="bi bi-bell"></i>
          <span class="notif-dot"></span>
          <span id="notifBadge" style="display:none;position:absolute;top:-3px;right:-3px;background:#ef4444;color:#fff;font-size:9px;font-weight:700;border-radius:99px;min-width:16px;height:16px;line-height:16px;text-align:center;padding:0 3px;box-shadow:0 0 0 2px var(--bg-page)"></span>
        </button>
        <div id="notifDropdown" style="display:none;flex-direction:column;position:absolute;right:0;top:calc(100% + 10px);width:330px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 8px 30px rgba(0,0,0,0.14);z-index:9999;overflow:hidden">
          <div style="padding:10px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <i class="bi bi-bell-fill" style="color:var(--accent);font-size:14px;flex-shrink:0"></i>
            <span style="font-weight:600;font-size:13px;color:var(--text-primary);flex:1">Alerts</span>
            <button onclick="markAllRead()" title="Mark all as read" style="background:none;border:none;cursor:pointer;font-size:11.5px;color:var(--text-muted);padding:3px 6px;border-radius:4px;display:flex;align-items:center;gap:3px" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='none'"><i class="bi bi-check-all"></i> Mark read</button>
            <a href="?page=notifications&tab=alerts" style="font-size:11.5px;color:var(--accent);text-decoration:none;white-space:nowrap">View all →</a>
          </div>
          <div id="notifList" style="max-height:360px;overflow-y:auto;padding:10px">
            <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px"><i class="bi bi-arrow-repeat"></i> Loading…</div>
          </div>
        </div>
      </div>

      <!-- Fullscreen -->
      <button class="header-btn" id="fullscreenBtn" title="Fullscreen">
        <i class="bi bi-fullscreen"></i>
      </button>

      <!-- Admin user -->
      <div style="position:relative">
        <button type="button" class="header-admin" id="accountMenuBtn">
          <div class="ha-avatar"><?= htmlspecialchars($adminInitials) ?></div>
          <span class="ha-name"><?= htmlspecialchars($adminName) ?></span>
          <i class="bi bi-chevron-down" style="font-size:10px;color:var(--text-subtle)"></i>
        </button>
        <div id="accountDropdown" class="account-dropdown" style="display:none">
          <a href="?page=admins" class="account-dropdown-item">Admin</a>
          <a href="?page=settings" class="account-dropdown-item">Settings</a>
          <a href="?page=integrations" class="account-dropdown-item">Integrations</a>
          <div class="account-dropdown-divider"></div>
          <a href="logout.php" class="account-dropdown-item danger">Sign Out</a>
        </div>
      </div>
    </div>

  </header>

  <!-- Page content wrapper -->
  <main class="content-area">
<script>
(function () {
  const STORE    = 'pc_read_alerts';
  let notifLoaded = false;
  let _data       = null;

  const btn = document.getElementById('notifBtn');
  const dd  = document.getElementById('notifDropdown');
  if (!btn || !dd) return;

  // ── localStorage helpers ──────────────────────────────────────────
  function getRead() {
    try { return JSON.parse(localStorage.getItem(STORE) || '[]'); } catch { return []; }
  }
  function saveRead(ids) {
    const merged = [...new Set([...getRead(), ...ids])];
    localStorage.setItem(STORE, JSON.stringify(merged.slice(-500)));
  }
  function alertKey(type, item) {
    if (type === 'lic')   return 'lic_'   + item.id + '_' + (item.license_expiry || '').slice(0, 10);
    if (type === 'pend')  return 'pend_'  + item.id;
    if (type === 'stale') return 'stale_' + item.id;
    return '';
  }

  // ── Toggle dropdown ───────────────────────────────────────────────
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    const open = dd.style.display === 'flex';
    dd.style.display = open ? 'none' : 'flex';
    if (!open && !notifLoaded) loadAlerts();
  });
  document.addEventListener('click', () => { dd.style.display = 'none'; });
  dd.addEventListener('click', e => e.stopPropagation());

  // ── Account dropdown ─────────────────────────────────────────────
  const acctBtn = document.getElementById('accountMenuBtn');
  const acctDd  = document.getElementById('accountDropdown');
  if (acctBtn && acctDd) {
    acctBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      acctDd.style.display = acctDd.style.display === 'flex' ? 'none' : 'flex';
    });
    document.addEventListener('click', () => { acctDd.style.display = 'none'; });
    acctDd.addEventListener('click', e => e.stopPropagation());
  }

  // ── Fetch alerts ──────────────────────────────────────────────────
  async function loadAlerts() {
    try {
      const res = await fetch('?page=notifications&action=get_alerts');
      _data = await res.json();
      notifLoaded = true;
      renderAlerts(_data);
    } catch {
      document.getElementById('notifList').innerHTML =
        '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">Failed to load alerts.</div>';
    }
  }

  // ── Render with read filtering ────────────────────────────────────
  function renderAlerts(data) {
    const read    = getRead();
    const license = (data.license_expiring || []).filter(d => !read.includes(alertKey('lic',  d)));
    const pending = (data.pending_drivers  || []).filter(d => !read.includes(alertKey('pend', d)));
    const stale   = (data.stale_searching  || []).filter(r => !read.includes(alertKey('stale',r)));
    const total   = license.length + pending.length + stale.length;

    const badge = document.getElementById('notifBadge');
    if (badge) { badge.textContent = total > 9 ? '9+' : total; badge.style.display = total > 0 ? 'block' : 'none'; }

    if (total === 0) {
      document.getElementById('notifList').innerHTML =
        '<div style="padding:28px;text-align:center;color:var(--text-muted)">' +
        '<i class="bi bi-check-circle-fill" style="font-size:24px;color:#16a34a;display:block;margin-bottom:8px"></i>' +
        '<div style="font-size:13px;font-weight:500">All caught up</div>' +
        '<div style="font-size:12px;margin-top:3px">No unread alerts</div></div>';
      return;
    }

    let html = '';
    license.forEach(function(d) {
      const diff    = Math.round((new Date(d.license_expiry) - Date.now()) / 86400000);
      const expired = diff < 0;
      html += notifItem(
        alertKey('lic', d), 'bi-card-text', '#d97706', esc(d.full_name),
        expired ? 'License expired ' + Math.abs(diff) + ' day' + (Math.abs(diff) !== 1 ? 's' : '') + ' ago'
                : 'License expires in ' + diff + ' day' + (diff !== 1 ? 's' : ''),
        '?page=drivers');
    });
    pending.forEach(function(d) {
      const hours = Math.floor((Date.now() - new Date(d.created_at)) / 3600000);
      html += notifItem(alertKey('pend', d), 'bi-person-exclamation', '#dc2626', esc(d.full_name),
        'Pending approval for ' + hours + ' hour' + (hours !== 1 ? 's' : ''), '?page=drivers&status=pending');
    });
    stale.forEach(function(r) {
      const mins = Math.floor((Date.now() - new Date(r.created_at)) / 60000);
      html += notifItem(alertKey('stale', r), 'bi-car-front-fill', '#7c3aed',
        'Ride searching ' + mins + ' min' + (mins !== 1 ? 's' : ''), esc(r.pickup_addr || 'Unknown pickup'),
        '?page=rides&status=searching');
    });
    document.getElementById('notifList').innerHTML = html;
  }

  function notifItem(key, icon, color, title, sub, href) {
    return '<div style="display:flex;align-items:center;gap:9px;padding:8px 9px;border-radius:8px;background:' + color + '10;margin-bottom:5px">' +
      '<i class="bi ' + icon + '" style="color:' + color + ';font-size:16px;flex-shrink:0"></i>' +
      '<div style="min-width:0;flex:1">' +
        '<a href="' + href + '" style="display:block;font-size:12.5px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none">' + title + '</a>' +
        '<div style="font-size:11.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + sub + '</div>' +
      '</div>' +
      '<button onclick="dismissAlert(\'' + key + '\')" title="Mark as read" style="background:none;border:none;cursor:pointer;padding:4px 5px;color:var(--text-subtle);border-radius:4px;font-size:15px;flex-shrink:0;line-height:1" onmouseover="this.style.color=\'var(--accent)\'" onmouseout="this.style.color=\'var(--text-subtle)\'"><i class="bi bi-check-lg"></i></button>' +
    '</div>';
  }

  function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  // ── Mark individual / all as read ────────────────────────────────
  window.dismissAlert = function(key) {
    saveRead([key]);
    if (_data) renderAlerts(_data);
  };

  window.markAllRead = function() {
    if (!_data) { loadAlerts().then(() => { if (_data) _markAll(); }); return; }
    _markAll();
  };
  function _markAll() {
    const ids = [
      ...(_data.license_expiring || []).map(d => alertKey('lic',   d)),
      ...(_data.pending_drivers  || []).map(d => alertKey('pend',  d)),
      ...(_data.stale_searching  || []).map(r => alertKey('stale', r)),
    ];
    saveRead(ids);
    renderAlerts(_data);
  }

  // ── Auto-update badge after page load (non-blocking) ─────────────
  setTimeout(async function() {
    try {
      const res = await fetch('?page=notifications&action=get_alerts');
      _data = await res.json();
      const read  = getRead();
      const total =
        (_data.license_expiring || []).filter(d => !read.includes(alertKey('lic',  d))).length +
        (_data.pending_drivers  || []).filter(d => !read.includes(alertKey('pend', d))).length +
        (_data.stale_searching  || []).filter(r => !read.includes(alertKey('stale',r))).length;
      const badge = document.getElementById('notifBadge');
      if (badge) { badge.textContent = total > 9 ? '9+' : total; badge.style.display = total > 0 ? 'block' : 'none'; }
    } catch {}
  }, 1500);
})();
</script>
