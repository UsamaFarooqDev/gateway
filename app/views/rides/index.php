<?php

function rideDuration(?string $from, ?string $to): string {
    if (!$from || !$to) return '—';
    $secs = max(0, strtotime($to) - strtotime($from));
    if ($secs < 60)   return $secs . 's';
    if ($secs < 3600) return floor($secs / 60) . 'm';
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return $m ? "{$h}h {$m}m" : "{$h}h";
}

function rideBadge(string $status): string {
    $map = [
        'completed' => ['#DCFCE7', '#16a34a', 'Completed'],
        'cancelled' => ['#FEE2E2', '#dc2626', 'Cancelled'],
        'enroute'   => ['#D1FAE5', '#059669', 'En Route'],
        'assigned'  => ['#EDE9FE', '#7c3aed', 'Assigned'],
        'searching' => ['#FEF9C3', '#854d0e', 'Searching'],
        'scheduled' => ['#DBEAFE', '#1d4ed8', 'Scheduled'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#F1F5F9', '#64748B', ucfirst(str_replace('_', ' ', $status))];
    return "<span style='display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600;background:{$bg};color:{$color}'>{$label}</span>";
}

// Build ride data map for JS
$rideDataMap = [];
foreach ($rides as $r) {
    $rideDataMap[$r['id']] = [
        'id'              => $r['id'],
        'status'          => $r['status'] ?? '',
        'fare'            => number_format((float)($r['fare'] ?? 0), 2),
        'created_at'      => $r['created_at'] ?? '',
        'pickup_addr'     => $r['pickup_addr'] ?? '',
        'dest_addr'       => $r['dest_addr'] ?? '',
        'pickup_lat'      => (float)($r['pickup_lat'] ?? 0),
        'pickup_lng'      => (float)($r['pickup_lng'] ?? 0),
        'dest_lat'        => (float)($r['dest_lat'] ?? 0),
        'dest_lng'        => (float)($r['dest_lng'] ?? 0),
        'driver_lat'      => (float)($r['driver_lat'] ?? 0),
        'driver_lng'      => (float)($r['driver_lng'] ?? 0),
        'driver_name'     => $r['driver_name'] ?? '',
        'driver_phone'    => $r['driver_phone'] ?? '',
        'passenger_name'  => $r['passenger_name'] ?? '',
        'passenger_phone' => $r['passenger_phone'] ?? '',
        'distance_km'     => $r['distance_km'] ?? null,
        'duration_min'    => $r['duration_min'] ?? null,
        'notes'           => $r['notes'] ?? '',
        'cancelled_by'    => $r['cancelled_by'] ?? null,
    ];
}

$activeFilter = $filters['status'] ?? 'all';
$searchQuery  = $filters['search'] ?? '';
$dateFrom     = $filters['date_from'] ?? '';
$dateTo       = $filters['date_to'] ?? '';
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1>Ride Management</h1>
    <p>Monitor and manage all rides across the platform.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass" onclick="Toast.show('Export coming soon.','info')">
      <i class="bi bi-download"></i> Export
    </button>
  </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-car-front-fill"></i></div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">Total Rides</div>
    <div class="fs-12 text-muted mt-1"><?= $activeFilter !== 'all' ? ucfirst($activeFilter) . ' filter active' : 'All statuses' ?></div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#D1FAE522;color:#059669"><i class="bi bi-broadcast"></i></div>
    <div class="stat-value" style="color:#059669"><?= number_format($counts['live']) ?></div>
    <div class="stat-label">En Route</div>
    <span class="stat-trend up"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#22c55e;margin-right:4px;animation:pulse-dot 2s infinite"></span>Live now</span>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#FEF9C322;color:#854d0e"><i class="bi bi-search"></i></div>
    <div class="stat-value" style="color:#854d0e"><?= number_format($counts['searching'] + $counts['assigned']) ?></div>
    <div class="stat-label">Searching / Assigned</div>
    <div class="fs-12 text-muted mt-1"><?= number_format($counts['searching']) ?> searching · <?= number_format($counts['assigned']) ?> assigned</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#DCFCE722;color:#16a34a"><i class="bi bi-check-circle-fill"></i></div>
    <div class="stat-value" style="color:#16a34a"><?= number_format($counts['completed']) ?></div>
    <div class="stat-label">Completed</div>
    <div class="fs-12 text-muted mt-1"><?= number_format($counts['cancelled']) ?> cancelled</div>
  </div>
</div>

<!-- Status Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <?php
  $tabDefs = [
      ['all',       'All Rides',  $total,                      ''],
      ['enroute',   'En Route',   $counts['enroute'],          '#059669'],
      ['assigned',  'Assigned',   $counts['assigned'],         '#7c3aed'],
      ['searching', 'Searching',  $counts['searching'],        '#854d0e'],
      ['scheduled', 'Scheduled',  $counts['scheduled'],        '#1d4ed8'],
      ['completed', 'Completed',  $counts['completed'],        '#16a34a'],
      ['cancelled', 'Cancelled',  $counts['cancelled'],        '#dc2626'],
  ];
  foreach ($tabDefs as [$slug, $label, $cnt, $dotColor]):
      $isActive = ($activeFilter === $slug);
  ?>
  <a href="?page=rides&status=<?= $slug ?>&search=<?= urlencode($searchQuery) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
     style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;transition:var(--t);border:1px solid <?= $isActive ? 'var(--accent)' : 'var(--border)' ?>;background:<?= $isActive ? 'var(--accent-soft)' : '#fff' ?>;color:<?= $isActive ? 'var(--accent)' : 'var(--text-muted)' ?>">
    <?php if ($dotColor && $slug !== 'all'): ?>
    <span style="width:7px;height:7px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0"></span>
    <?php endif; ?>
    <?= $label ?>
    <span style="font-size:11px;padding:1px 6px;border-radius:99px;background:<?= $isActive ? 'var(--accent)' : 'var(--border)' ?>;color:<?= $isActive ? '#fff' : 'var(--text-muted)' ?>"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="rides">
    <input type="hidden" name="status" value="<?= htmlspecialchars($activeFilter) ?>">
    <div class="filter-bar" style="padding:14px 18px;flex-wrap:wrap">
      <div class="glass-input-icon" style="flex:1;min-width:200px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input"
               placeholder="Search address, passenger..."
               value="<?= htmlspecialchars($searchQuery) ?>">
      </div>
      <input type="date" name="date_from" class="glass-input" style="width:145px"
             value="<?= htmlspecialchars($dateFrom) ?>">
      <input type="date" name="date_to" class="glass-input" style="width:145px"
             value="<?= htmlspecialchars($dateTo) ?>">
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Filter</button>
      <?php if ($searchQuery || $dateFrom || $dateTo): ?>
      <a href="?page=rides&status=<?= urlencode($activeFilter) ?>" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Rides Table -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-car-front-fill" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Rides</div>
      <div class="card-subtitle"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <?php if (empty($rides)): ?>
    <div class="empty-state">
      <i class="bi bi-car-front"></i>
      <h4>No rides found</h4>
      <p><?= $searchQuery ? 'Try a different search term.' : 'No rides match the selected filter.' ?></p>
    </div>
    <?php else: ?>
    <table class="glass-table" id="ridesTable">
      <thead>
        <tr>
          <th>Ride ID</th>
          <th>Passenger</th>
          <th>Driver</th>
          <th>Pickup</th>
          <th>Destination</th>
          <th>Fare</th>
          <th>Status</th>
          <th>Duration</th>
          <th>Time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rides as $r): ?>
        <?php
          $id     = $r['id'];
          $status = $r['status'] ?? '';
          $fare   = (float)($r['fare'] ?? 0);
        ?>
        <tr>
          <td class="text-muted fs-12"><?= htmlspecialchars(substr($id, 0, 8)) ?>…</td>

          <td>
            <div class="user-cell">
              <div class="user-avatar-sm" style="background:linear-gradient(135deg,#F37A20,#e06010)">
                <?= strtoupper(substr($r['passenger_name'] ?? '?', 0, 1)) ?>
              </div>
              <div class="user-cell-info">
                <div class="name"><?= htmlspecialchars($r['passenger_name'] ?? '—') ?></div>
                <div class="meta"><?= htmlspecialchars($r['passenger_phone'] ?? '') ?></div>
              </div>
            </div>
          </td>

          <td>
            <?php if (!empty($r['driver_name'])): ?>
            <div class="user-cell">
              <div class="user-avatar-sm" style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
                <?= strtoupper(substr($r['driver_name'], 0, 1)) ?>
              </div>
              <div class="user-cell-info">
                <div class="name"><?= htmlspecialchars($r['driver_name']) ?></div>
                <div class="meta"><?= htmlspecialchars($r['driver_phone'] ?? '') ?></div>
              </div>
            </div>
            <?php else: ?>
            <span class="text-muted fs-12">Unassigned</span>
            <?php endif; ?>
          </td>

          <td style="max-width:160px">
            <span style="font-size:12.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">
              <?= htmlspecialchars(substr($r['pickup_addr'] ?? '—', 0, 35)) ?>
            </span>
          </td>

          <td style="max-width:160px">
            <span style="font-size:12.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">
              <?= htmlspecialchars(substr($r['dest_addr'] ?? '—', 0, 35)) ?>
            </span>
          </td>

          <td style="font-weight:600;color:var(--accent)">€<?= number_format($fare, 2) ?></td>

          <td><?= rideBadge($status) ?></td>

          <td class="text-muted fs-12"><?= rideDuration($r['created_at'] ?? null, $r['updated_at'] ?? null) ?></td>

          <td class="text-muted fs-12">
            <?= !empty($r['created_at']) ? date('d M H:i', strtotime($r['created_at'])) : '—' ?>
          </td>

          <td>
            <div style="display:flex;gap:4px;flex-wrap:nowrap">
              <button class="btn-icon" title="View Details"
                onclick="viewRide(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-eye"></i>
              </button>

              <?php if ($status === 'completed'): ?>
              <button class="btn-icon" title="Invoice" style="color:#7c3aed"
                onclick="showInvoice(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-receipt"></i>
              </button>
              <?php endif; ?>

              <!-- Map: enroute (live) or assigned (driver heading to pickup) -->
              <?php if (in_array($status, ['enroute', 'assigned'], true)): ?>
              <button class="btn-icon" title="Live Map" style="color:#059669"
                onclick="showMap(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-map"></i>
              </button>
              <?php endif; ?>

              <?php if ($status === 'searching'): ?>
              <button class="btn-icon" title="Notify Dispatcher" style="color:#d97706"
                onclick="notifyDispatcher(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-bell"></i>
              </button>
              <?php endif; ?>

              <?php if (in_array($status, ['searching', 'assigned', 'scheduled'], true)): ?>
              <button class="btn-icon danger" title="Cancel Ride"
                onclick="cancelRide(<?= htmlspecialchars(json_encode($id)) ?>, this)">
                <i class="bi bi-x-circle"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination-bar">
    <span>Showing <?= ($page-1)*$perPage+1 ?>–<?= min($page*$perPage,$total) ?> of <?= number_format($total) ?></span>
    <div class="pagination-controls">
      <?php if ($page > 1): ?>
      <a href="?page=rides&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page-1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
      <a href="?page=rides&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $i ?>"
         class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=rides&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page+1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- View Ride Modal -->
<div class="modal-overlay" id="rideDetailModal">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <i class="bi bi-car-front-fill" style="color:var(--accent);font-size:20px"></i>
      <div class="flex-1">
        <div class="modal-title">Ride Details</div>
        <div style="font-size:12px;color:var(--text-muted)" id="rdId"></div>
      </div>
      <div id="rdStatus"></div>
      <button class="modal-close" onclick="Modal.close('rideDetailModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" id="rdBody"></div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('rideDetailModal')"><i class="bi bi-x"></i> Close</button>
    </div>
  </div>
</div>

<!-- Invoice Modal -->
<div class="modal-overlay" id="invoiceModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <i class="bi bi-receipt" style="color:#7c3aed;font-size:20px"></i>
      <span class="modal-title">Ride Invoice</span>
      <button class="modal-close" onclick="Modal.close('invoiceModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" id="invBody"></div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('invoiceModal')"><i class="bi bi-x"></i> Close</button>
      <button class="btn-primary-glass" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>
</div>

<!-- Map Modal -->
<div class="modal-overlay" id="mapModal">
  <div class="modal-box" style="max-width:720px;max-height:90vh">
    <div class="modal-header">
      <i class="bi bi-map" style="color:#059669;font-size:20px"></i>
      <div class="flex-1">
        <div class="modal-title">Live Ride Map</div>
        <div style="font-size:12px;color:var(--text-muted)" id="mapRideId"></div>
      </div>
      <button class="modal-close" onclick="closeMapModal()"><i class="bi bi-x"></i></button>
    </div>
    <!-- Legend -->
    <div style="display:flex;gap:16px;padding:10px 20px;background:var(--hover-bg);border-bottom:1px solid var(--border);font-size:12px;flex-wrap:wrap">
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#F37A20;flex-shrink:0"></span> Pickup</span>
      <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#7c3aed;flex-shrink:0"></span> Destination</span>
      <span style="display:flex;align-items:center;gap:5px"><span style="font-size:14px">🚕</span> Driver</span>
      <span style="display:flex;align-items:center;gap:5px"><span style="width:20px;height:3px;background:#F37A20;border-radius:2px;flex-shrink:0"></span> Route</span>
    </div>
    <div class="modal-body" style="padding:0">
      <div id="rideMap" style="width:100%;height:440px"></div>
    </div>
    <div class="modal-footer">
      <div id="mapDriverInfo" style="flex:1;font-size:13px;color:var(--text-muted)"></div>
      <button class="btn-glass" onclick="closeMapModal()"><i class="bi bi-x"></i> Close</button>
    </div>
  </div>
</div>

<!-- Leaflet CSS (loaded once, lazy) -->
<link id="leafletCss" rel="stylesheet" href="" disabled>

<?php
$rideDataJson = json_encode($rideDataMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$extraScripts = <<<'SCRIPTS'
<script>
const _RD = RIDE_DATA_PLACEHOLDER;

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-IE', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
}

// ─── Status badge ──────────────────────────────────────────────────
function rideStatusBadge(status) {
  const map = {
    completed: ['#DCFCE7','#16a34a','Completed'],
    cancelled: ['#FEE2E2','#dc2626','Cancelled'],
    enroute:   ['#D1FAE5','#059669','En Route'],
    assigned:  ['#EDE9FE','#7c3aed','Assigned'],
    searching: ['#FEF9C3','#854d0e','Searching'],
    scheduled: ['#DBEAFE','#1d4ed8','Scheduled'],
  };
  const [bg, color, label] = map[status] || ['#F1F5F9','#64748B', status];
  return `<span style="padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;background:${bg};color:${color}">${label}</span>`;
}

// ─── View Ride Detail ───────────────────────────────────────────────
function viewRide(id) {
  const r = _RD[id];
  if (!r) { Toast.show('Ride data not found.', 'error'); return; }
  document.getElementById('rdId').textContent = id.slice(0,8) + '…';
  document.getElementById('rdStatus').innerHTML = rideStatusBadge(r.status);

  const rows = [
    ['bi-person-fill',    'Passenger',    r.passenger_name  || '—'],
    ['bi-telephone',      'Pax Phone',    r.passenger_phone || '—'],
    ['bi-person-badge',   'Driver',       r.driver_name     || 'Unassigned'],
    ['bi-telephone-fill', 'Driver Phone', r.driver_phone    || '—'],
    ['bi-geo-alt',        'Pickup',       r.pickup_addr     || '—'],
    ['bi-geo-alt-fill',   'Destination',  r.dest_addr       || '—'],
    ['bi-cash-coin',      'Fare',         '€' + r.fare],
    ['bi-rulers',         'Distance',     r.distance_km ? r.distance_km + ' km' : '—'],
    ['bi-clock',          'Duration',     r.duration_min ? r.duration_min + ' min' : '—'],
    ['bi-calendar',       'Created',      fmtDate(r.created_at)],
    ['bi-chat-left-text', 'Notes',        r.notes || '—'],
  ];
  if (r.status === 'cancelled' && r.cancelled_by) {
    const byMap = {passenger: 'Passenger', driver: 'Driver', admin: 'Admin', system: 'System'};
    rows.push(['bi-x-circle-fill', 'Cancelled By', byMap[r.cancelled_by] || r.cancelled_by]);
  }

  const grid = rows.map(([icon, label, val]) => `
    <div style="padding:10px 12px;background:var(--hover-bg);border-radius:var(--radius-sm);border:1px solid var(--border)">
      <div style="font-size:10.5px;color:var(--text-subtle);margin-bottom:3px;display:flex;align-items:center;gap:5px">
        <i class="bi ${icon}" style="color:var(--accent)"></i>${label}
      </div>
      <div style="font-size:13px;font-weight:500;color:var(--text-primary)">${escHtml(val)}</div>
    </div>
  `).join('');

  document.getElementById('rdBody').innerHTML =
    `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">${grid}</div>`;
  Modal.open('rideDetailModal');
}

// ─── Invoice ───────────────────────────────────────────────────────
function showInvoice(id) {
  const r = _RD[id];
  if (!r) { Toast.show('Ride not found.', 'error'); return; }
  const fare = parseFloat(r.fare) || 0;
  const commission  = (fare * 0.15).toFixed(2);
  const driverEarns = (fare * 0.85).toFixed(2);

  document.getElementById('invBody').innerHTML = `
    <div style="text-align:center;padding-bottom:16px;border-bottom:1px solid var(--border);margin-bottom:16px">
      <div style="font-size:20px;font-weight:700;color:var(--accent)">PowerCabs</div>
      <div style="font-size:12px;color:var(--text-muted)">Ride Receipt · ${fmtDate(r.created_at)}</div>
      <div style="font-size:11px;color:var(--text-subtle);margin-top:4px">#${id.slice(0,8).toUpperCase()}</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">
      ${invRow('Passenger', r.passenger_name||'—')}
      ${invRow('Driver', r.driver_name||'—')}
      ${invRow('Pickup', r.pickup_addr||'—')}
      ${invRow('Destination', r.dest_addr||'—')}
      ${r.distance_km ? invRow('Distance', r.distance_km+' km') : ''}
      ${r.duration_min ? invRow('Duration', r.duration_min+' min') : ''}
    </div>
    <div style="background:var(--hover-bg);border-radius:var(--radius-sm);padding:14px;border:1px solid var(--border)">
      ${fareRow('Fare','€'+fare.toFixed(2))}
      ${fareRow('Commission (15%)','€'+commission)}
      ${fareRow('Driver Earnings','€'+driverEarns)}
      <div style="border-top:2px solid var(--border);margin:10px 0"></div>
      <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700">
        <span>Total Charged</span><span style="color:var(--accent)">€${fare.toFixed(2)}</span>
      </div>
    </div>
  `;
  Modal.open('invoiceModal');
}
function invRow(l,v) { return `<div style="display:flex;justify-content:space-between;font-size:13px"><span style="color:var(--text-muted)">${escHtml(l)}</span><span style="font-weight:500">${escHtml(v)}</span></div>`; }
function fareRow(l,v) { return `<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span style="color:var(--text-muted)">${escHtml(l)}</span><span style="font-weight:500">${escHtml(v)}</span></div>`; }

// ─── Live Map (Leaflet with OSRM routing) ─────────────────────────
let _leafMap = null;

function closeMapModal() {
  Modal.close('mapModal');
  if (_leafMap) { _leafMap.remove(); _leafMap = null; }
  document.getElementById('rideMap').innerHTML = '';
}

function showMap(id) {
  const r = _RD[id];
  if (!r) { Toast.show('Ride not found.', 'error'); return; }
  document.getElementById('mapRideId').textContent = id.slice(0,8) + '…';
  document.getElementById('mapDriverInfo').innerHTML = r.driver_name
    ? `<i class="bi bi-person-badge" style="color:var(--accent)"></i> ${escHtml(r.driver_name)} · ${escHtml(r.driver_phone||'')}`
    : '<span style="color:var(--text-subtle)">No driver assigned</span>';

  // Clean up any previous map
  if (_leafMap) { _leafMap.remove(); _leafMap = null; }
  document.getElementById('rideMap').innerHTML = '';

  Modal.open('mapModal');
  setTimeout(() => initLeafletMap(r), 150);
}

function initLeafletMap(r) {
  const mapEl = document.getElementById('rideMap');

  // Load Leaflet lazily
  function _doInit() {
    const hasPickup = r.pickup_lat && r.pickup_lng;
    const hasDest   = r.dest_lat   && r.dest_lng;
    const hasDriver = r.driver_lat && r.driver_lng;

    if (!hasPickup && !hasDriver) {
      mapEl.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:10px;color:var(--text-muted)">
        <i class="bi bi-geo-alt-fill" style="font-size:36px;color:var(--accent)"></i>
        <div>No location data available for this ride.</div>
      </div>`;
      return;
    }

    const center = hasDriver
      ? [r.driver_lat, r.driver_lng]
      : (hasPickup ? [r.pickup_lat, r.pickup_lng] : [53.3498, -6.2603]);

    _leafMap = L.map(mapEl, { zoomControl: true, scrollWheelZoom: true }).setView(center, 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(_leafMap);

    // ── Pickup marker (orange circle)
    if (hasPickup) {
      L.circleMarker([r.pickup_lat, r.pickup_lng], {
        radius: 10, fillColor: '#F37A20', color: '#fff', weight: 3, fillOpacity: 1,
      }).addTo(_leafMap).bindPopup(`<b>Pickup</b><br>${r.pickup_addr||''}`);
    }

    // ── Destination marker (purple)
    if (hasDest) {
      L.circleMarker([r.dest_lat, r.dest_lng], {
        radius: 10, fillColor: '#7c3aed', color: '#fff', weight: 3, fillOpacity: 1,
      }).addTo(_leafMap).bindPopup(`<b>Destination</b><br>${r.dest_addr||''}`);
    }

    // ── Driver marker (taxi emoji)
    if (hasDriver) {
      const taxiIcon = L.divIcon({
        html: '<div style="font-size:28px;line-height:1;filter:drop-shadow(0 2px 3px rgba(0,0,0,0.3))">🚕</div>',
        iconSize: [32, 32], iconAnchor: [16, 16], className: '',
      });
      L.marker([r.driver_lat, r.driver_lng], { icon: taxiIcon })
        .addTo(_leafMap)
        .bindPopup(`<b>${r.driver_name||'Driver'}</b>`);
    }

    // ── Fit bounds to show all markers
    const points = [];
    if (hasPickup) points.push([r.pickup_lat, r.pickup_lng]);
    if (hasDest)   points.push([r.dest_lat,   r.dest_lng]);
    if (hasDriver) points.push([r.driver_lat, r.driver_lng]);
    if (points.length > 1) _leafMap.fitBounds(L.latLngBounds(points), { padding: [40, 40] });

    // ── Route polyline via OSRM (free, no API key)
    if (hasPickup && hasDest) {
      const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${r.pickup_lng},${r.pickup_lat};${r.dest_lng},${r.dest_lat}?overview=full&geometries=geojson`;
      fetch(osrmUrl)
        .then(res => res.json())
        .then(data => {
          if (!_leafMap) return;
          const coords = data?.routes?.[0]?.geometry?.coordinates;
          if (coords) {
            const latlngs = coords.map(([lng, lat]) => [lat, lng]);
            L.polyline(latlngs, {
              color: '#F37A20', weight: 5, opacity: 0.75,
              dashArray: null,
            }).addTo(_leafMap);
          }
        })
        .catch(() => {
          // OSRM failed — draw straight dashed line fallback
          if (!_leafMap) return;
          L.polyline([[r.pickup_lat, r.pickup_lng],[r.dest_lat, r.dest_lng]], {
            color: '#F37A20', weight: 4, opacity: 0.6, dashArray: '8 6',
          }).addTo(_leafMap);
        });
    }
  }

  // Load Leaflet CSS + JS if not already loaded
  if (typeof L !== 'undefined') {
    _doInit();
  } else {
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);

    const js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = _doInit;
    document.head.appendChild(js);
  }
}

// ─── Notify Dispatcher ─────────────────────────────────────────────
function notifyDispatcher(id) {
  const r = _RD[id];
  if (!r) return;
  if (!confirm(`Notify dispatcher for ride ${id.slice(0,8)}…?\nPassenger: ${r.passenger_name||'—'}\nPickup: ${r.pickup_addr||'—'}`)) return;
  Toast.show('Dispatcher notified — ride #' + id.slice(0,8) + ' is searching for a driver.', 'info');
}

// ─── Cancel Ride ───────────────────────────────────────────────────
async function cancelRide(id, btn) {
  if (!confirm('Cancel this ride? This cannot be undone.')) return;
  if (btn) btn.disabled = true;
  const fd = new FormData();
  fd.append('action', 'cancel_ride');
  fd.append('id', id);
  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.show('Ride cancelled.', 'success');
      setTimeout(() => location.reload(), 800);
    } else {
      Toast.show(data.message || 'Failed to cancel ride.', 'error');
      if (btn) btn.disabled = false;
    }
  } catch {
    Toast.show('Network error.', 'error');
    if (btn) btn.disabled = false;
  }
}
</script>
SCRIPTS;

$extraScripts = str_replace('RIDE_DATA_PLACEHOLDER', $rideDataJson, $extraScripts);
?>
