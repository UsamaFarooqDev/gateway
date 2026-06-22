<?php
// Helpers
function trendBadge(float $pct): string {
    if ($pct > 0) return "<span class='stat-trend up'><i class='bi bi-arrow-up-short'></i>{$pct}%</span>";
    if ($pct < 0) return "<span class='stat-trend down'><i class='bi bi-arrow-down-short'></i>" . abs($pct) . "%</span>";
    return "<span class='stat-trend flat'><i class='bi bi-dash'></i> Flat</span>";
}

function rideDuration(?string $from, ?string $to): string {
    if (!$from || !$to) return '—';
    $secs = max(0, strtotime($to) - strtotime($from));
    if ($secs < 60)   return $secs . 's';
    if ($secs < 3600) return floor($secs / 60) . 'm';
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return $m ? "{$h}h {$m}m" : "{$h}h";
}

function statusBadge(string $status): string {
    $map = [
        'completed' => ['badge-completed',  'Completed'],
        'cancelled' => ['badge-cancelled',  'Cancelled'],
        'searching' => ['badge-pending',    'Searching'],
        'assigned'  => ['badge-active',     'Assigned'],
        'enroute'   => ['badge-online',     'En Route'],
        'scheduled' => ['badge-inactive',   'Scheduled'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-inactive', ucfirst(str_replace('_', ' ', $status))];
    return "<span class='badge-pill {$cls}'>{$label}</span>";
}
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back — here's what's happening on PowerCabs today.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
    <span class="btn-glass" style="cursor:default;color:var(--text-subtle)">
      <i class="bi bi-calendar3"></i>
      <?= date('D, d M Y') ?>
    </span>
  </div>
</div>

<!-- ─── Stat Cards ─── -->
<div class="stats-grid">

  <!-- Rides Today -->
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-car-front-fill"></i></div>
    <div class="stat-value"><?= number_format($stats['rides_today']) ?></div>
    <div class="stat-label">Rides Today</div>
    <?= trendBadge($ridesTrend) ?>
    <div class="fs-12 text-muted mt-1">vs <?= number_format($stats['rides_yesterday']) ?> yesterday</div>
  </div>

  <!-- Active Drivers -->
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
    <div class="stat-value"><?= number_format($stats['active_drivers']) ?></div>
    <div class="stat-label">Drivers Online</div>
    <span class="stat-trend up"><span class="dot" style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;margin-right:4px;animation:pulse-dot 2s infinite"></span>Live</span>
    <div class="fs-12 text-muted mt-1"><?= number_format($stats['total_drivers']) ?> total active drivers</div>
  </div>

  <!-- Passengers -->
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
    <div class="stat-value"><?= number_format($stats['total_passengers']) ?></div>
    <div class="stat-label">Total Passengers</div>
    <span class="stat-trend up"><i class="bi bi-person-plus"></i> +<?= $stats['new_passengers_today'] ?> today</span>
    <div class="fs-12 text-muted mt-1">Registered passengers</div>
  </div>

  <!-- Revenue -->
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
    <div class="stat-value">€<?= number_format($stats['revenue_today'], 2) ?></div>
    <div class="stat-label">Revenue Today</div>
    <?= trendBadge($revTrend) ?>
    <div class="fs-12 text-muted mt-1">vs €<?= number_format($stats['revenue_yesterday'], 2) ?> yesterday</div>
  </div>

</div>

<?php
// Alert overlay data — rendered as fixed side panel, not inline card
$overlayJson = json_encode([
    'license_expiring' => $dashboardAlerts['license_expiring'] ?? [],
    'pending_drivers'  => $dashboardAlerts['pending_drivers']  ?? [],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<!-- ─── Charts Row ─── -->
<div class="dash-charts-row">

  <!-- Weekly rides chart -->
  <div class="glass-card" style="padding:22px">
    <div class="d-flex align-center justify-between mb-3">
      <div>
        <div style="font-size:15px;font-weight:600">Rides by Status — Last 7 Days</div>
        <div class="fs-12 text-muted">Daily breakdown by status</div>
      </div>
      <span class="badge-pill badge-active"><?= array_sum($weeklyRides) ?> total</span>
    </div>
    <div class="chart-container" style="height:200px">
      <canvas id="weeklyChart"></canvas>
    </div>
  </div>

  <!-- Driver status donut -->
  <div class="glass-card" style="padding:22px">
    <div class="mb-3">
      <div style="font-size:15px;font-weight:600">Driver Status</div>
      <div class="fs-12 text-muted">Current fleet snapshot</div>
    </div>
    <div class="chart-container" style="height:160px">
      <canvas id="driverChart"></canvas>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px">
        <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
        <span class="text-muted">Online</span>
        <span class="fw-600 ml-auto"><?= $driverCounts['online'] ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:6px;font-size:12px">
        <span style="width:10px;height:10px;border-radius:50%;background:#94a3b8;flex-shrink:0"></span>
        <span class="text-muted">Offline</span>
        <span class="fw-600"><?= $driverCounts['offline'] ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:6px;font-size:12px">
        <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;flex-shrink:0"></span>
        <span class="text-muted">Pending</span>
        <span class="fw-600"><?= $driverCounts['pending'] ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:6px;font-size:12px">
        <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
        <span class="text-muted">Suspended</span>
        <span class="fw-600"><?= $driverCounts['suspended'] ?></span>
      </div>
    </div>
  </div>

</div>

<!-- ─── Ride Status + Quick Actions ─── -->
<div class="dash-two-col">

  <!-- Today's ride status -->
  <div class="glass-card" style="padding:22px">
    <div style="font-size:15px;font-weight:600;margin-bottom:16px">Today's Ride Status</div>
    <?php
    $rideStatusItems = [
        ['label'=>'Completed',  'count'=>$rideCounts['completed'],  'color'=>'#818cf8', 'bg'=>'rgba(99,102,241,0.12)'],
        ['label'=>'En Route',   'count'=>$rideCounts['enroute'],    'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,0.12)'],
        ['label'=>'Assigned',   'count'=>$rideCounts['assigned'],   'color'=>'#F37A20', 'bg'=>'rgba(243,122,32,0.12)'],
        ['label'=>'Searching',  'count'=>$rideCounts['searching'],  'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.12)'],
        ['label'=>'Scheduled',  'count'=>$rideCounts['scheduled'],  'color'=>'#a78bfa', 'bg'=>'rgba(167,139,250,0.12)'],
        ['label'=>'Cancelled',  'count'=>$rideCounts['cancelled'],  'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,0.12)'],
    ];
    $total = max(1, array_sum(array_column($rideStatusItems, 'count')));
    foreach ($rideStatusItems as $item):
        $pct = round(($item['count'] / $total) * 100);
    ?>
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
        <span style="font-size:13px;color:var(--text-muted)"><?= $item['label'] ?></span>
        <span style="font-size:13px;font-weight:600;color:<?= $item['color'] ?>"><?= $item['count'] ?></span>
      </div>
      <div style="height:5px;background:var(--border);border-radius:99px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $item['color'] ?>;border-radius:99px;transition:width 1s ease"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick actions -->
  <div class="glass-card" style="padding:22px">
    <div style="font-size:15px;font-weight:600;margin-bottom:16px">Quick Actions</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php
      $quickActions = [
          ['?page=drivers&action=pending', 'bi-person-badge',   'Pending Drivers',   $stats['pending_drivers'] . ' waiting'],
          ['?page=drivers',               'bi-plus-circle',     'Add Driver',        'Register new driver'],
          ['?page=passengers',            'bi-people',          'View Passengers',   'Manage passengers'],
          ['?page=rides',                 'bi-car-front',       'Live Rides',        'Monitor active rides'],
          ['?page=finance',               'bi-cash-coin',       'Finance',           'Payments & payouts'],
          ['?page=support',               'bi-headset',         'Support Queue',     'Open tickets'],
      ];
      foreach ($quickActions as [$url, $icon, $label, $sub]): ?>
      <a href="<?= $url ?>" style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--hover-bg);border:1px solid var(--border);border-radius:var(--radius-sm);text-decoration:none;transition:var(--t)" onmouseover="this.style.background='#EDF2F7'" onmouseout="this.style.background='var(--hover-bg)'">
        <div style="width:36px;height:36px;border-radius:8px;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:16px;flex-shrink:0">
          <i class="bi <?= $icon ?>"></i>
        </div>
        <div>
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary)"><?= $label ?></div>
          <div style="font-size:11px;color:var(--text-muted)"><?= $sub ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- ─── Recent Rides Table ─── -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-clock-history" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Recent Rides</div>
      <div class="card-subtitle">Latest 10 ride requests</div>
    </div>
    <a href="?page=rides" class="btn-glass ms-auto">
      View All <i class="bi bi-arrow-right"></i>
    </a>
  </div>

  <div class="table-wrap">
    <?php if (empty($recentRides)): ?>
    <div class="empty-state">
      <i class="bi bi-car-front"></i>
      <h4>No rides yet</h4>
      <p>Ride data will appear here once the platform is active.</p>
    </div>
    <?php else: ?>
    <table class="glass-table" id="recentRidesTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Passenger</th>
          <th>Driver</th>
          <th>Pickup</th>
          <th>Fare</th>
          <th>Status</th>
          <th>Duration</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRides as $ride): ?>
        <tr>
          <td class="text-muted fs-12"><?= htmlspecialchars(substr($ride['id'], 0, 8)) ?>…</td>
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm"><?= strtoupper(substr($ride['passenger_name'] ?? '?', 0, 1)) ?></div>
              <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($ride['passenger_name'] ?? '—') ?></span>
            </div>
          </td>
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm" style="background:linear-gradient(135deg,#6366f1,#4f46e5)"><?= strtoupper(substr($ride['driver_name'] ?? '?', 0, 1)) ?></div>
              <span style="font-size:13px"><?= htmlspecialchars($ride['driver_name'] ?? '—') ?></span>
            </div>
          </td>
          <td>
            <span style="font-size:12.5px;color:var(--text-muted);max-width:160px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= htmlspecialchars(substr($ride['pickup_addr'] ?? '—', 0, 30)) ?>
            </span>
          </td>
          <td style="font-weight:600;color:var(--accent)">
            €<?= number_format((float)($ride['fare'] ?? 0), 2) ?>
          </td>
          <td><?= statusBadge($ride['status'] ?? 'pending') ?></td>
          <td class="text-muted fs-12"><?= rideDuration($ride['created_at'] ?? null, $ride['updated_at'] ?? null) ?></td>
          <td class="text-muted fs-12">
            <?= $ride['created_at'] ? date('H:i', strtotime($ride['created_at'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Fixed alert overlay (dismiss stores state in localStorage for today) -->
<div id="alertOverlay" style="display:none;position:fixed;right:20px;top:76px;width:330px;z-index:9998;border-radius:var(--radius);overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.16);border:1px solid rgba(217,119,6,0.35);flex-direction:column">
  <div style="padding:10px 14px;background:rgba(253,230,138);border-bottom:1px solid rgba(217,119,6,0.2);display:flex;align-items:center;gap:8px">
    <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:13px;flex-shrink:0"></i>
    <span style="font-weight:600;font-size:12.5px;flex:1;color:var(--text-primary)">Platform Alerts <span id="oCount" style="color:#d97706"></span></span>
    <a href="?page=notifications&tab=alerts" style="font-size:11px;color:var(--accent);text-decoration:none;margin-right:4px;white-space:nowrap">View All →</a>
    <button onclick="closeAlertOverlay()" title="Dismiss for today" style="background:none;border:none;cursor:pointer;padding:2px 4px;color:var(--text-muted);font-size:17px;line-height:1;border-radius:4px;display:flex;align-items:center" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='none'"><i class="bi bi-x"></i></button>
  </div>
  <div id="oList" style="max-height:360px;overflow-y:auto;padding:8px;background:var(--bg-card);display:flex;flex-direction:column;gap:5px"></div>
</div>
<style>
#alertOverlay { animation: pcSlideIn .3s ease; }
@keyframes pcSlideIn { from { opacity:0; transform:translateX(14px); } to { opacity:1; transform:translateX(0); } }
</style>
<script>
(function() {
  const DISMISS_KEY = 'pc_overlay_' + new Date().toISOString().slice(0, 10);
  if (localStorage.getItem(DISMISS_KEY)) return;

  const data = <?= $overlayJson ?>;
  const lic  = data.license_expiring || [];
  const pend = data.pending_drivers  || [];
  const tot  = lic.length + pend.length;
  if (tot === 0) return;

  document.getElementById('oCount').textContent = '(' + tot + ')';
  let html = '';

  lic.forEach(function(d) {
    var diff = Math.round((new Date(d.license_expiry) - Date.now()) / 86400000);
    var exp  = diff < 0;
    var c    = exp ? '#dc2626' : '#d97706';
    var msg  = exp ? 'Expired ' + Math.abs(diff) + ' day' + (Math.abs(diff) !== 1 ? 's' : '') + ' ago'
                   : 'Expires in ' + diff + ' day' + (diff !== 1 ? 's' : '');
    html += oItem(d.full_name, 'bi-card-text', c, 'License: ' + msg, '?page=drivers');
  });
  pend.forEach(function(d) {
    var hrs = Math.floor((Date.now() - new Date(d.created_at)) / 3600000);
    html += oItem(d.full_name, 'bi-person-x-fill', '#dc2626', 'Pending ' + hrs + 'h — awaiting approval', '?page=drivers&status=pending');
  });

  document.getElementById('oList').innerHTML = html;
  document.getElementById('alertOverlay').style.display = 'flex';

  function oItem(name, icon, color, sub, href) {
    var n = String(name || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    var s = String(sub  || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return '<div style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;border-left:3px solid ' + color + ';background:' + color + '14">' +
      '<i class="bi ' + icon + '" style="color:' + color + ';font-size:15px;flex-shrink:0"></i>' +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-size:12.5px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + n + '</div>' +
        '<div style="font-size:11.5px;color:var(--text-muted)">' + s + '</div>' +
      '</div>' +
      '<a href="' + href + '" style="font-size:11px;padding:3px 8px;border-radius:5px;background:' + color + '20;color:' + color + ';text-decoration:none;font-weight:600;white-space:nowrap;flex-shrink:0">View</a>' +
    '</div>';
  }

  window.closeAlertOverlay = function() {
    var ov = document.getElementById('alertOverlay');
    if (!ov) return;
    ov.style.transition = 'opacity .2s ease, transform .2s ease';
    ov.style.opacity    = '0';
    ov.style.transform  = 'translateX(14px)';
    setTimeout(function() { ov.style.display = 'none'; }, 210);
    localStorage.setItem(DISMISS_KEY, '1');
  };
})();
</script>

<!-- ─── Charts JS ─── -->
<?php
// Weekly chart data — by status for multi-line chart
$weeklyLabels   = array_map(fn($d) => date('D d', strtotime($d)), array_keys($weeklyByStatus));
$weeklyStatKeys = ['searching', 'assigned', 'enroute', 'completed', 'cancelled', 'scheduled'];
$weeklyDatasets = [];
foreach ($weeklyStatKeys as $s) {
    $weeklyDatasets[$s] = array_values(array_column($weeklyByStatus, $s));
}
$weeklyLabJson      = json_encode($weeklyLabels);
$weeklyDatasetsJson = json_encode($weeklyDatasets, JSON_NUMERIC_CHECK);

$driverJson = json_encode([
    $driverCounts['online'],
    $driverCounts['offline'],
    $driverCounts['pending'],
    $driverCounts['suspended'],
], JSON_NUMERIC_CHECK);
?>
<?php $extraScripts = <<<HTML
<script>
Chart.defaults.color = '#64748B';
Chart.defaults.borderColor = 'rgba(0,0,0,0.06)';
Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.font.size   = 12;

// Weekly rides — multi-line chart by status
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
const _WDS = {$weeklyDatasetsJson};
const _SC  = {
  searching: {label:'Searching', color:'#f59e0b'},
  assigned:  {label:'Assigned',  color:'#8b5cf6'},
  enroute:   {label:'En Route',  color:'#22c55e'},
  completed: {label:'Completed', color:'#6366f1'},
  cancelled: {label:'Cancelled', color:'#ef4444'},
  scheduled: {label:'Scheduled', color:'#06b6d4'},
};
new Chart(weeklyCtx, {
  type: 'line',
  data: {
    labels: {$weeklyLabJson},
    datasets: Object.entries(_WDS).map(([k, d]) => ({
      label:           _SC[k].label,
      data:            d,
      borderColor:     _SC[k].color,
      backgroundColor: _SC[k].color + '18',
      borderWidth: 2, pointRadius: 3, pointHoverRadius: 5,
      tension: 0.35, fill: false,
    }))
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 11 } } },
      tooltip: { mode: 'index', intersect: false }
    },
    scales: {
      x: { grid: { display: false } },
      y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
    }
  }
});

// Driver donut
const driverCtx = document.getElementById('driverChart').getContext('2d');
new Chart(driverCtx, {
  type: 'doughnut',
  data: {
    labels: ['Online','Offline','Pending','Suspended'],
    datasets: [{
      data: {$driverJson},
      backgroundColor: ['#22c55e','#94a3b8','#f59e0b','#ef4444'],
      borderWidth: 0,
      hoverOffset: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + ctx.parsed }
      }
    }
  }
});
</script>
HTML;
?>
