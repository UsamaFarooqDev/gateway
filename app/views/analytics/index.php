<?php
$tabs = ['revenue' => 'Revenue', 'rides' => 'Ride Volume', 'drivers' => 'Drivers', 'passengers' => 'Passengers', 'heatmap' => 'Heatmap'];

// Prepare chart data from controller-injected variables
$revLabels  = array_keys($dailyRev ?? []);
$revValues  = array_values($dailyRev ?? []);
$rideLabels = array_keys($dailyRides ?? []);
$rideValues = array_values($dailyRides ?? []);
$hourlyData = array_values($hourly ?? array_fill(0, 24, 0));
$dowData    = array_values($dayOfWeek ?? ['Mon'=>0,'Tue'=>0,'Wed'=>0,'Thu'=>0,'Fri'=>0,'Sat'=>0,'Sun'=>0]);

// Format labels to short date
$revLabels  = array_map(fn($d) => date('d M', strtotime($d)), $revLabels);
$rideLabels = array_map(fn($d) => date('d M', strtotime($d)), $rideLabels);

$revJson    = json_encode($revValues,  JSON_NUMERIC_CHECK);
$revLabJson = json_encode($revLabels);
$ridJson    = json_encode($rideValues, JSON_NUMERIC_CHECK);
$ridLabJson = json_encode($rideLabels);
$houJson    = json_encode($hourlyData, JSON_NUMERIC_CHECK);
$dowJson    = json_encode($dowData,    JSON_NUMERIC_CHECK);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1>Analytics &amp; Reports</h1>
    <p>Revenue analytics, ride trends, driver performance, and passenger insights.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass" onclick="Toast.show('CSV export coming soon.','info')"><i class="bi bi-filetype-csv"></i> CSV</button>
    <button class="btn-glass" onclick="Toast.show('PDF export coming soon.','info')"><i class="bi bi-filetype-pdf"></i> PDF</button>
  </div>
</div>

<!-- MTD Stat Cards -->
<div class="stats-grid">
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#16a34a22;color:#16a34a"><i class="bi bi-graph-up"></i></div>
    <div class="stat-value" style="color:#16a34a">€<?= number_format($mtdStats['revenue'] ?? 0, 2) ?></div>
    <div class="stat-label">Revenue (MTD)</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-car-front"></i></div>
    <div class="stat-value"><?= number_format($mtdStats['totalRides'] ?? 0) ?></div>
    <div class="stat-label">Total Rides (MTD)</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#d9770622;color:#d97706"><i class="bi bi-star-fill"></i></div>
    <div class="stat-value" style="color:#d97706">
      <?= $mtdStats['avgRating'] !== null ? number_format($mtdStats['avgRating'], 1) : '—' ?>
    </div>
    <div class="stat-label">Avg Driver Rating</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#7c3aed22;color:#7c3aed"><i class="bi bi-person-plus"></i></div>
    <div class="stat-value" style="color:#7c3aed"><?= number_format($mtdStats['newPassengers'] ?? 0) ?></div>
    <div class="stat-label">New Passengers (MTD)</div>
  </div>
</div>

<!-- Tab Nav -->
<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=analytics&tab=<?= $slug ?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;transition:var(--t);border:1px solid <?= $tab===$slug?'var(--accent)':'var(--border)' ?>;background:<?= $tab===$slug?'var(--accent-soft)':'#fff' ?>;color:<?= $tab===$slug?'var(--accent)':'var(--text-muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ─── Revenue Tab ─── -->
<?php if ($tab === 'revenue'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-graph-up" style="color:var(--accent)"></i>
    <div>
      <div class="card-title">Daily Revenue — Last 30 Days</div>
      <div class="card-subtitle">Completed rides only</div>
    </div>
    <span class="badge-pill badge-active ms-auto" style="margin-left:auto">
      €<?= number_format(array_sum($revValues), 2) ?> total
    </span>
  </div>
  <div style="padding:20px"><canvas id="revenueChart" height="160"></canvas></div>
</div>

<div class="glass-card mt-4">
  <div class="card-header-bar">
    <i class="bi bi-table" style="color:var(--accent)"></i>
    <div class="card-title">Daily Revenue Breakdown</div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Date</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php
        $revByDay = $dailyRev ?? [];
        arsort($revByDay);
        foreach ($revByDay as $date => $rev):
          if ($rev == 0) continue;
        ?>
        <tr>
          <td><?= date('D, d M Y', strtotime($date)) ?></td>
          <td style="font-weight:600;color:var(--accent)">€<?= number_format($rev, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (array_sum($revByDay) === 0.0): ?>
        <tr><td colspan="2"><div class="empty-state" style="padding:28px"><i class="bi bi-graph-up"></i><h4>No revenue yet this period</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ─── Rides Tab ─── -->
<?php elseif ($tab === 'rides'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-bar-chart" style="color:var(--accent)"></i>
    <div>
      <div class="card-title">Daily Ride Volume — Last 30 Days</div>
      <div class="card-subtitle"><?= array_sum($rideValues) ?> total rides</div>
    </div>
  </div>
  <div style="padding:20px"><canvas id="rideVolumeChart" height="160"></canvas></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
  <div class="glass-card">
    <div class="card-header-bar">
      <i class="bi bi-clock" style="color:var(--accent)"></i>
      <div class="card-title">Peak Hours (Last 30 Days)</div>
    </div>
    <div style="padding:20px"><canvas id="peakHoursChart" height="200"></canvas></div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar">
      <i class="bi bi-calendar-week" style="color:var(--accent)"></i>
      <div class="card-title">Rides by Day of Week</div>
    </div>
    <div style="padding:20px"><canvas id="dayOfWeekChart" height="200"></canvas></div>
  </div>
</div>

<!-- ─── Drivers Tab ─── -->
<?php elseif ($tab === 'drivers'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-trophy" style="color:#d97706"></i>
    <div class="card-title">Top Drivers by Total Rides</div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Driver</th>
          <th>Total Rides</th>
          <th>Total Earnings</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($topDrivers)): ?>
        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-trophy"></i><h4>No driver data yet</h4></div></td></tr>
        <?php else: ?>
        <?php foreach ($topDrivers as $i => $d): ?>
        <tr>
          <td>
            <?php if ($i < 3): ?>
            <span style="font-size:16px"><?= ['🥇','🥈','🥉'][$i] ?></span>
            <?php else: ?>
            <span class="text-muted fs-12">#<?= $i+1 ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm"><?= strtoupper(substr($d['full_name']??'?',0,1)) ?></div>
              <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($d['full_name']??'—') ?></span>
            </div>
          </td>
          <td style="font-weight:600"><?= number_format((int)($d['total_rides']??0)) ?></td>
          <td style="color:var(--accent);font-weight:600">€<?= number_format((float)($d['total_earnings']??0),2) ?></td>
          <td>
            <?php
              $dActive = in_array($d['status']??'', ['active','approved'], true);
              $dBg     = $dActive ? '#DCFCE7' : '#F1F5F9';
              $dClr    = $dActive ? '#16a34a' : '#64748B';
              $dLbl    = $dActive ? 'Active' : ucfirst($d['status']??'—');
            ?>
            <span style="font-size:11.5px;padding:3px 8px;border-radius:99px;background:<?= $dBg ?>;color:<?= $dClr ?>">
              <?= $dLbl ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ─── Passengers Tab ─── -->
<?php elseif ($tab === 'passengers'): ?>
<div class="glass-card">
  <div class="empty-state" style="padding:60px">
    <i class="bi bi-people" style="font-size:40px"></i>
    <h4>Passenger retention analytics</h4>
    <p>Cohort analysis and retention data will appear here as the platform grows.</p>
  </div>
</div>

<!-- ─── Heatmap Tab ─── -->
<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-map" style="color:var(--accent)"></i>
    <div class="card-title">Demand Heatmap</div>
  </div>
  <div style="background:var(--hover-bg);height:480px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;border-radius:0 0 var(--radius) var(--radius)">
    <i class="bi bi-map" style="font-size:56px;color:var(--text-subtle)"></i>
    <div style="font-weight:600;color:var(--text-muted)">Ride Demand Heatmap</div>
    <div style="font-size:12px;color:var(--text-subtle)">Google Maps heatmap overlay showing ride pickup/dropoff density.</div>
    <button class="btn-primary-glass mt-2" onclick="Toast.show('Google Maps API key required in Settings → Integrations.','info')">
      <i class="bi bi-geo-alt"></i> Configure Maps API
    </button>
  </div>
</div>
<?php endif; ?>

<?php $extraScripts = <<<SCRIPTS
<script>
Chart.defaults.color = '#64748B';
Chart.defaults.borderColor = 'rgba(0,0,0,0.06)';
Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.font.size   = 12;

const _scaleDefaults = {
  y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
  x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }
};

if (document.getElementById('revenueChart')) {
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: {$revLabJson},
      datasets: [{
        label: 'Revenue (€)',
        data: {$revJson},
        borderColor: '#F37A20',
        backgroundColor: 'rgba(243,122,32,0.08)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 5,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' €' + ctx.parsed.y.toFixed(2) } } },
      scales: _scaleDefaults,
    }
  });
}

if (document.getElementById('rideVolumeChart')) {
  new Chart(document.getElementById('rideVolumeChart'), {
    type: 'bar',
    data: {
      labels: {$ridLabJson},
      datasets: [{
        label: 'Rides',
        data: {$ridJson},
        backgroundColor: 'rgba(243,122,32,0.55)',
        borderColor: '#F37A20',
        borderWidth: 1,
        borderRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: _scaleDefaults,
    }
  });
}

if (document.getElementById('peakHoursChart')) {
  const hours = Array.from({length:24},(_,i)=> i===0?'12am':i<12?i+'am':i===12?'12pm':(i-12)+'pm');
  new Chart(document.getElementById('peakHoursChart'), {
    type: 'bar',
    data: {
      labels: hours,
      datasets: [{
        data: {$houJson},
        backgroundColor: 'rgba(243,122,32,0.55)',
        borderRadius: 3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { ..._scaleDefaults, x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } },
    }
  });
}

if (document.getElementById('dayOfWeekChart')) {
  new Chart(document.getElementById('dayOfWeekChart'), {
    type: 'bar',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        data: {$dowJson},
        backgroundColor: ['#6366f1','#6366f1','#6366f1','#6366f1','#F37A20','#F37A20','#94a3b8'],
        borderRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: _scaleDefaults,
    }
  });
}
</script>
SCRIPTS;
?>
