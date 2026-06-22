<?php
$activeFilter = $filters['status'] ?? 'all';
$searchQuery  = $filters['search'] ?? '';

function driverStatusBadge(string $status, bool $online = false): string {
    if (in_array($status, ['active','approved'], true) && $online) {
        return "<span class='badge-pill badge-online'><span class='dot'></span>Online</span>";
    }
    $map = [
        'active'    => ['badge-active',    'Active'],
        'approved'  => ['badge-active',    'Active'],   // treat same as active
        'pending'   => ['badge-pending',   'Pending'],
        'suspended' => ['badge-suspended', 'Suspended'],
        'inactive'  => ['badge-inactive',  'Inactive'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-inactive', ucfirst($status)];
    return "<span class='badge-pill {$cls}'><span class='dot'></span>{$label}</span>";
}

// Fully defensive: Supabase returns JSONB as PHP array; older rows may be a string or nested array
function vehicleTypeBadge(mixed $typeInput): string {
    if (empty($typeInput)) return '<span class="text-muted fs-12">—</span>';

    if (is_string($typeInput)) {
        $decoded = json_decode($typeInput, true);
        $types   = is_array($decoded) ? $decoded : [$typeInput];
    } elseif (is_array($typeInput)) {
        $types = $typeInput;
    } else {
        return '<span class="text-muted fs-12">—</span>';
    }

    $out = '';
    foreach ($types as $t) {
        $label = null;
        if (is_array($t)) {
            $label = $t['label']      ?? null;
            $emoji = $t['icon_emoji'] ?? null;
            $t     = $t['type']       ?? reset($t) ?? '';
        }
        $t = (string)($t ?? '');
        if ($t === '') continue;
        if (!$label) {
            $label = match($t) {
                'economy'    => 'Economy',
                'economy_xl' => 'Economy XL',
                default      => ucwords(str_replace('_', ' ', $t)),
            };
        }
        $out .= "<span class='badge-pill' style='background:#EDE9FE;color:#7c3aed;margin-right:4px;font-size:10.5px'>" . htmlspecialchars($label) . "</span>";
    }
    return $out ?: '<span class="text-muted fs-12">—</span>';
}

function docsStatus(array $d): string {
    $done = !empty($d['license_url']) && !empty($d['vehicle_reg_url']) && !empty($d['insurance_url']);
    return $done
        ? "<span style='color:#16a34a;font-size:12px'><i class='bi bi-patch-check-fill'></i> Complete</span>"
        : "<span style='color:#d97706;font-size:12px'><i class='bi bi-exclamation-triangle'></i> Incomplete</span>";
}

// Build per-driver data array for the JS lookup table
$driverDataMap = [];
foreach ($drivers as $d) {
    $id   = $d['id'];
    $name = trim($d['full_name'] ?? '');

    // Parse current type field into array of name strings for the ride-type modal
    $rawType = $d['type'] ?? null;
    $typeArr = [];
    if (is_string($rawType))   $typeArr = json_decode($rawType, true) ?? [];
    elseif (is_array($rawType)) $typeArr = $rawType;
    $currentTypeNames = [];
    foreach ($typeArr as $t) {
        if (is_string($t))       $currentTypeNames[] = $t;
        elseif (is_array($t))    $currentTypeNames[] = $t['type'] ?? (string)reset($t);
    }
    $currentTypeNames = array_values(array_filter($currentTypeNames));

    $driverDataMap[$id] = [
        'id'             => $id,
        'name'           => $name,
        'email'          => $d['email']  ?? '',
        'phone'          => $d['phone']  ?? '',
        'status'         => $d['status'] ?? '',
        'vehicle'        => trim(($d['vehicle_make'] ?? '') . ' ' . ($d['vehicle_model'] ?? '')),
        'plate'          => $d['plate_no']  ?? '',
        'seats'          => (string)($d['no_seats'] ?? ''),
        'rides'          => (int)($d['total_rides'] ?? 0),
        'earnings'       => '€' . number_format((float)($d['total_earnings'] ?? 0), 2),
        'joined'         => !empty($d['created_at'])      ? date('d M Y', strtotime($d['created_at']))      : '—',
        'license_expiry' => !empty($d['license_expiry'])  ? date('d M Y', strtotime($d['license_expiry']))  : '—',
        'types'          => $currentTypeNames,
        'docs' => [
            ['type' => 'license',     'label' => 'Driving Licence',      'url' => $d['license_url']      ?? ''],
            ['type' => 'vehicle_reg', 'label' => 'Vehicle Registration',  'url' => $d['vehicle_reg_url']  ?? ''],
            ['type' => 'insurance',   'label' => 'Insurance',             'url' => $d['insurance_url']    ?? ''],
            ['type' => 'nct',         'label' => 'NCT Certificate',       'url' => $d['nct_cert']         ?? ''],
            ['type' => 'rt',          'label' => 'Road Tax',              'url' => $d['rt_cert']          ?? ''],
            ['type' => 'suitability', 'label' => 'Suitability Cert',      'url' => $d['suitability_cert'] ?? ''],
        ],
    ];
}
?>

<?php
// License expiry alerts: expired or expiring within 7 days
$today       = date('Y-m-d');
$alertWindow = date('Y-m-d', strtotime('+7 days'));
$expiryAlerts = array_filter($drivers, function($d) use ($alertWindow) {
    if (empty($d['license_expiry'])) return false;
    $exp = substr($d['license_expiry'], 0, 10);
    return $exp <= $alertWindow;
});
?>

<?php if (!empty($expiryAlerts)): ?>
<div style="background:#FEF3C7;border:1px solid #fbbf24;border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
  <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:18px;margin-top:1px;flex-shrink:0"></i>
  <div>
    <div style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:4px">
      Licence Expiry Alert — <?= count($expiryAlerts) ?> driver<?= count($expiryAlerts)>1?'s':'' ?> require attention
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($expiryAlerts as $d):
        $exp  = substr($d['license_expiry'], 0, 10);
        $days = (int)round((strtotime($exp) - time()) / 86400);
        $isExpired = $days < 0;
      ?>
      <span style="font-size:12px;padding:3px 10px;border-radius:99px;background:<?= $isExpired?'#FEE2E2':'#FEF3C7'?>;color:<?= $isExpired?'#dc2626':'#92400e'?>;border:1px solid <?= $isExpired?'#fca5a5':'#fbbf24'?>">
        <?= htmlspecialchars($d['full_name']??'') ?> —
        <?= $isExpired ? 'EXPIRED ' . abs($days) . ' day' . (abs($days) > 1 ? 's' : '') . ' ago' : "expires in {$days} day" . ($days > 1 ? 's' : '') ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Page header -->
<div class="page-header">
  <div>
    <h1>Driver Management</h1>
    <p>Manage driver accounts, documents, and approval status.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-primary-glass" onclick="Modal.open('addDriverModal')">
      <i class="bi bi-plus-lg"></i> Add Driver
    </button>
    <button class="btn-glass" onclick="exportDrivers()">
      <i class="bi bi-download"></i> Export
    </button>
  </div>
</div>

<!-- Stats strip -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <?php
  $strips = [
      ['all',       'Total Drivers',   $counts['total'],     'bi-people',          '#F37A20'],
      ['active',    'Active',          $counts['active'],    'bi-check-circle',    '#16a34a'],
      ['online',    'Online Now',      $counts['online'],    'bi-broadcast',       '#16a34a'],
      ['pending',   'Pending Review',  $counts['pending'],   'bi-hourglass-split', '#d97706'],
      ['suspended', 'Suspended',       $counts['suspended'], 'bi-slash-circle',    '#dc2626'],
  ];
  foreach ($strips as [$slug, $label, $count, $icon, $color]):
      $isActive = $activeFilter === $slug;
  ?>
  <a href="?page=drivers&status=<?= $slug ?>&search=<?= urlencode($searchQuery) ?>"
     style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:<?= $isActive ? 'rgba(243,122,32,0.10)' : '#fff' ?>;border:1px solid <?= $isActive ? '#F37A20' : 'var(--border)' ?>;border-radius:var(--radius-sm);text-decoration:none;transition:var(--t);min-width:140px;box-shadow:var(--shadow-sm)">
    <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:20px"></i>
    <div>
      <div style="font-size:18px;font-weight:700;color:var(--text-primary);line-height:1"><?= number_format($count) ?></div>
      <div style="font-size:11px;color:var(--text-muted)"><?= $label ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="drivers">
    <div class="filter-bar" style="padding:16px 20px">

      <div class="glass-input-icon" style="flex:1;min-width:220px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" id="driverSearchInput" name="search" class="glass-input"
               placeholder="Search name, email, plate..."
               value="<?= htmlspecialchars($searchQuery) ?>"
               autocomplete="off">
      </div>

      <select id="driverStatusFilter" name="status" class="glass-select" style="width:160px" onchange="triggerDriverSearch()">
        <option value="all"       <?= $activeFilter==='all'       ?'selected':'' ?>>All Drivers</option>
        <option value="active"    <?= $activeFilter==='active'    ?'selected':'' ?>>Active</option>
        <option value="online"    <?= $activeFilter==='online'    ?'selected':'' ?>>Online Now</option>
        <option value="pending"   <?= $activeFilter==='pending'   ?'selected':'' ?>>Pending</option>
        <option value="suspended" <?= $activeFilter==='suspended' ?'selected':'' ?>>Suspended</option>
        <option value="inactive"  <?= $activeFilter==='inactive'  ?'selected':'' ?>>Inactive</option>
      </select>

      <button type="submit" class="btn-primary-glass">
        <i class="bi bi-search"></i> Search
      </button>

      <?php if ($searchQuery || $activeFilter !== 'all'): ?>
      <a href="?page=drivers" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>

    </div>
  </form>
</div>

<!-- Drivers table -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-person-badge" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Drivers</div>
      <div class="card-subtitle"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <?php if (empty($drivers)): ?>
    <div class="empty-state">
      <i class="bi bi-person-badge"></i>
      <h4>No drivers found</h4>
      <p><?= $searchQuery ? 'Try a different search term.' : 'No drivers match the selected filter.' ?></p>
    </div>
    <?php else: ?>
    <table class="glass-table" id="driversTable">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Contact</th>
          <th>Vehicle</th>
          <th>Type</th>
          <th>Rides</th>
          <th>Earnings</th>
          <th>Docs</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($drivers as $d): ?>
        <?php
          $id       = $d['id'];
          $name     = trim($d['full_name'] ?? '');
          $parts    = array_filter(explode(' ', $name));
          $initials = strtoupper(implode('', array_map(fn($p) => $p[0], array_slice($parts, 0, 2))));
          $initials = $initials ?: '?';
          $isOnline = (bool)($d['is_online'] ?? false);
          $idShort  = substr($id, 0, 8);
        ?>
        <tr>
          <!-- Driver -->
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm" style="position:relative">
                <?php if (!empty($d['profile_pic_url'])): ?>
                  <img src="<?= htmlspecialchars($d['profile_pic_url']) ?>" alt="">
                <?php else: ?>
                  <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
                <?php if ($isOnline && ($d['status'] ?? '') === 'active'): ?>
                <span style="position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;background:#16a34a;border-radius:50%;border:2px solid #fff"></span>
                <?php endif; ?>
              </div>
              <div class="user-cell-info">
                <div class="name"><?= htmlspecialchars($name ?: '—') ?></div>
                <div class="meta"><?= htmlspecialchars($idShort) ?>…</div>
              </div>
            </div>
          </td>

          <!-- Contact -->
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($d['email'] ?? '—') ?></div>
            <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($d['phone'] ?? '—') ?></div>
          </td>

          <!-- Vehicle -->
          <td>
            <div style="font-size:13px;font-weight:500">
              <?= htmlspecialchars(trim(($d['vehicle_make'] ?? '') . ' ' . ($d['vehicle_model'] ?? ''))) ?: '—' ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-muted)">
              <?= htmlspecialchars($d['plate_no'] ?? '—') ?>
              <?php if (!empty($d['no_seats'])): ?> · <?= (int)$d['no_seats'] ?> seats<?php endif; ?>
            </div>
          </td>

          <!-- Type (JSONB) -->
          <td><?= vehicleTypeBadge($d['type'] ?? null) ?></td>

          <!-- Rides / Earnings -->
          <td style="font-weight:500"><?= number_format((int)($d['total_rides'] ?? 0)) ?></td>
          <td style="color:var(--accent);font-weight:600">
            €<?= number_format((float)($d['total_earnings'] ?? 0), 2) ?>
          </td>

          <!-- Docs -->
          <td><?= docsStatus($d) ?></td>

          <!-- Status -->
          <td><?= driverStatusBadge($d['status'] ?? 'inactive', $isOnline) ?></td>

          <!-- Joined -->
          <td class="text-muted fs-12">
            <?= !empty($d['created_at']) ? date('d M Y', strtotime($d['created_at'])) : '—' ?>
          </td>

          <!-- Actions -->
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="View Details"
                onclick="viewDriver(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn-icon" title="Assign Ride Types"
                onclick="openRideTypesModal(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-tag"></i>
              </button>
              <button class="btn-icon" title="Upload Documents"
                onclick="openUploadDocsModal(<?= htmlspecialchars(json_encode($id)) ?>)">
                <i class="bi bi-cloud-arrow-up"></i>
              </button>
              <button class="btn-icon danger" title="Delete Account"
                onclick="confirmDeleteDriver(<?= htmlspecialchars(json_encode($id)) ?>, <?= htmlspecialchars(json_encode($name ?: 'this driver')) ?>)">
                <i class="bi bi-trash3"></i>
              </button>

              <?php if (($d['status'] ?? '') === 'pending'): ?>
              <button class="btn-icon success" title="Approve"
                onclick="updateStatus(<?= htmlspecialchars(json_encode($id)) ?>, 'active', this)">
                <i class="bi bi-check-lg"></i>
              </button>
              <?php elseif (in_array($d['status'] ?? '', ['active','approved'], true)): ?>
              <button class="btn-icon danger" title="Suspend"
                onclick="updateStatus(<?= htmlspecialchars(json_encode($id)) ?>, 'suspended', this)">
                <i class="bi bi-slash-circle"></i>
              </button>
              <?php elseif (($d['status'] ?? '') === 'suspended'): ?>
              <button class="btn-icon success" title="Reactivate"
                onclick="updateStatus(<?= htmlspecialchars(json_encode($id)) ?>, 'active', this)">
                <i class="bi bi-arrow-counterclockwise"></i>
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

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination-bar">
    <span>Showing <?= ($page-1)*$perPage+1 ?>–<?= min($page*$perPage,$total) ?> of <?= number_format($total) ?></span>
    <div class="pagination-controls">
      <?php if ($page > 1): ?>
      <a href="?page=drivers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page-1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
      <a href="?page=drivers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $i ?>"
         class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=drivers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page+1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- View Driver Modal -->
<div class="modal-overlay" id="viewDriverModal">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <div class="user-avatar-sm" style="width:42px;height:42px;font-size:16px;flex-shrink:0" id="mdAvatar"></div>
      <div class="flex-1" style="min-width:0">
        <div class="modal-title" id="mdName"></div>
        <div style="font-size:12px;color:var(--text-muted)" id="mdId"></div>
      </div>
      <div id="mdStatus"></div>
      <button class="modal-close" onclick="Modal.close('viewDriverModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" id="mdBody"></div>
    <div class="modal-footer" id="mdFooter">
      <button class="btn-glass" onclick="Modal.close('viewDriverModal')"><i class="bi bi-x"></i> Close</button>
    </div>
  </div>
</div>

<!-- Add Driver Modal -->
<div class="modal-overlay" id="addDriverModal">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header">
      <i class="bi bi-person-plus-fill" style="color:var(--accent);font-size:20px"></i>
      <span class="modal-title">Add New Driver</span>
      <button class="modal-close" onclick="Modal.close('addDriverModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">

      <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px">
        Creates a Supabase Auth account and a driver profile. The driver can log in immediately with the password you set.
      </p>

      <!-- Section: Account -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--accent);margin-bottom:10px">Account Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        <div style="grid-column:1/-1">
          <label class="form-label">Full Name <span style="color:#dc2626">*</span></label>
          <input type="text" id="adName" class="glass-input" placeholder="John Murphy" autocomplete="off">
        </div>
        <div>
          <label class="form-label">Email <span style="color:#dc2626">*</span></label>
          <input type="email" id="adEmail" class="glass-input" placeholder="john@example.com" autocomplete="off">
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input type="tel" id="adPhone" class="glass-input" placeholder="+353 87 000 0000">
        </div>
        <div>
          <label class="form-label">Temporary Password <span style="color:#dc2626">*</span></label>
          <div style="position:relative">
            <input type="password" id="adPassword" class="glass-input" placeholder="Min. 8 characters" style="padding-right:42px" autocomplete="new-password">
            <button type="button" onclick="togglePwd()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:16px;padding:2px">
              <i class="bi bi-eye" id="adPwdEye"></i>
            </button>
          </div>
        </div>
        <div>
          <label class="form-label">Initial Status</label>
          <select id="adStatus" class="glass-select" style="width:100%">
            <option value="pending">Pending Review</option>
            <option value="approved">Approved</option>
          </select>
        </div>
      </div>

      <!-- Section: Vehicle -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--accent);margin-bottom:10px">Vehicle Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label class="form-label">Vehicle Make</label>
          <input type="text" id="adVehicleMake" class="glass-input" placeholder="Toyota">
        </div>
        <div>
          <label class="form-label">Vehicle Model</label>
          <input type="text" id="adVehicleModel" class="glass-input" placeholder="Corolla">
        </div>
        <div>
          <label class="form-label">Plate Number</label>
          <input type="text" id="adPlateNo" class="glass-input" placeholder="192-D-12345" style="text-transform:uppercase">
        </div>
        <div>
          <label class="form-label">Vehicle / Board Number</label>
          <input type="text" id="adVehicleNo" class="glass-input" placeholder="e.g. N3433" style="text-transform:uppercase">
        </div>
        <div>
          <label class="form-label">No. of Seats</label>
          <select id="adSeats" class="glass-select" style="width:100%">
            <option value="4">4 seats (Economy)</option>
            <option value="5">5 seats (Economy XL)</option>
            <option value="6">6 seats</option>
            <option value="7">7 seats</option>
            <option value="8">8 seats</option>
          </select>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('addDriverModal')"><i class="bi bi-x"></i> Cancel</button>
      <button class="btn-primary-glass" id="adSaveBtn" onclick="submitAddDriver()">
        <i class="bi bi-check-lg"></i> Create Driver
      </button>
    </div>
  </div>
</div>

<!-- Ride Types Modal -->
<div class="modal-overlay" id="rideTypesModal">
  <div class="modal-box" style="max-width:540px">
    <div class="modal-header">
      <i class="bi bi-tag-fill" style="color:var(--accent);font-size:20px"></i>
      <div>
        <div class="modal-title">Assign Ride Types</div>
        <div style="font-size:12px;color:var(--text-muted)" id="rtDriverName"></div>
      </div>
      <button class="modal-close" onclick="Modal.close('rideTypesModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px">
        Select the ride categories this driver is eligible to accept.
      </p>
      <div id="rtChecksContainer" style="display:grid;grid-template-columns:1fr 1fr;gap:10px"></div>
      <div id="rtEmpty" style="display:none;text-align:center;padding:30px;color:var(--text-subtle);font-size:13px">
        <i class="bi bi-tag" style="font-size:28px;display:block;margin-bottom:8px"></i>
        No active ride types configured yet.
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('rideTypesModal')"><i class="bi bi-x"></i> Cancel</button>
      <button class="btn-primary-glass" id="rtSaveBtn" onclick="saveRideTypes()">
        <i class="bi bi-check-lg"></i> Save
      </button>
    </div>
  </div>
</div>

<!-- Delete Driver Confirmation Modal -->
<div class="modal-overlay" id="deleteDriverModal">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header">
      <div style="width:40px;height:40px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi bi-trash3-fill" style="color:#dc2626;font-size:18px"></i>
      </div>
      <div>
        <div class="modal-title">Delete Driver Account</div>
        <div style="font-size:12px;color:var(--text-muted)" id="delDriverName"></div>
      </div>
      <button class="modal-close" onclick="Modal.close('deleteDriverModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <div style="background:#FEF2F2;border:1px solid #fecaca;border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:600;color:#dc2626;margin-bottom:6px">This action cannot be undone</div>
        <ul style="margin:0;padding-left:18px;font-size:12.5px;color:#7f1d1d;line-height:1.8">
          <li>Driver account will be permanently removed</li>
          <li>Driver will be signed out of all devices</li>
          <li>An email will be sent notifying document incompletion</li>
        </ul>
      </div>
      <div style="background:var(--hover-bg);border-radius:var(--radius-sm);padding:12px 14px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Email notification preview</div>
        <div style="font-size:12.5px;color:var(--text-primary);font-style:italic">
          "Your PowerCabs driver account has been removed due to incomplete or invalid documentation."
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('deleteDriverModal')"><i class="bi bi-x"></i> Cancel</button>
      <button class="btn-primary-glass" id="delDriverBtn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 4px 15px rgba(220,38,38,0.3)" onclick="executeDeleteDriver()">
        <i class="bi bi-trash3"></i> Delete & Notify
      </button>
    </div>
  </div>
</div>

<!-- Upload Documents Modal -->
<div class="modal-overlay" id="uploadDocsModal">
  <div class="modal-box" style="max-width:580px">
    <div class="modal-header">
      <i class="bi bi-cloud-arrow-up-fill" style="color:var(--accent);font-size:20px"></i>
      <div>
        <div class="modal-title">Upload Documents</div>
        <div style="font-size:12px;color:var(--text-muted)" id="udDriverName"></div>
      </div>
      <button class="modal-close" onclick="Modal.close('uploadDocsModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 16px">
        JPG, PNG or PDF — max 10 MB per file. Files replace any previously uploaded document of the same type.
      </p>
      <div id="udSlots" style="display:flex;flex-direction:column;gap:10px"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('uploadDocsModal')"><i class="bi bi-x"></i> Close</button>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="docLightbox">
  <button class="lightbox-close" onclick="closeLightbox()"><i class="bi bi-x"></i></button>
  <img id="lbImg" src="" alt="">
  <div class="lightbox-label" id="lbLabel"></div>
  <div class="lightbox-actions" id="lbActions"></div>
</div>

<?php $extraScripts = <<<'SCRIPTS'
<style>
.rt-card { display:flex; align-items:flex-start; gap:10px; padding:12px; background:var(--hover-bg); border-radius:var(--radius-sm); border:1.5px solid var(--border); cursor:pointer; transition:all .15s; user-select:none }
.rt-card:has(.rt-cb:checked) { border-color:var(--accent); background:rgba(243,122,32,0.08) }
.rt-cb { flex-shrink:0; margin-top:2px; accent-color:var(--accent); width:15px; height:15px; cursor:pointer }
.rt-name { font-size:13px; font-weight:600; color:var(--text-primary) }
.rt-desc { font-size:11.5px; color:var(--text-muted); margin-top:2px }
.rt-meta { font-size:11px; color:var(--text-subtle); margin-top:4px }
.ud-slot { display:flex; align-items:center; gap:12px; padding:12px 14px; background:var(--hover-bg); border-radius:var(--radius-sm); border:1px solid var(--border) }
.ud-thumb { width:44px; height:44px; border-radius:6px; object-fit:cover; border:1px solid var(--border); flex-shrink:0; cursor:pointer }
.ud-thumb-placeholder { width:44px; height:44px; border-radius:6px; background:rgba(255,255,255,0.04); border:1px dashed var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--text-subtle) }
.ud-info { flex:1; min-width:0 }
.ud-label { font-size:13px; font-weight:600; color:var(--text-primary); margin-bottom:3px }
.ud-status { font-size:11.5px }
.ud-file-row { display:flex; gap:8px; align-items:center; margin-top:8px }
.ud-file-input { font-size:12px; color:var(--text-muted); flex:1; min-width:0; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:6px; padding:4px 8px; cursor:pointer }
.ud-file-input::file-selector-button { background:rgba(243,122,32,0.12); border:none; border-radius:4px; color:#F37A20; font-size:11.5px; padding:3px 8px; margin-right:8px; cursor:pointer }
.ud-upload-btn { font-size:12px; padding:5px 14px; white-space:nowrap }
</style>
<script>
// Driver data lookup table — avoids any inline JSON encoding issues
const _DD = DRIVER_DATA_PLACEHOLDER;
const _RIDE_TYPES = RIDE_TYPES_PLACEHOLDER;

function viewDriver(id) {
  const d = _DD[id];
  if (!d) { Toast.show('Driver data not found.', 'error'); return; }

  // Header
  const initials = d.name.split(' ').filter(Boolean).map(p => p[0]).join('').toUpperCase().slice(0, 2) || '?';
  document.getElementById('mdAvatar').textContent = initials;
  document.getElementById('mdName').textContent   = d.name || '—';
  document.getElementById('mdId').textContent     = id.slice(0, 8) + '…';

  const statusStyle = {
    active:'color:#16a34a;background:#DCFCE7', pending:'color:#d97706;background:#FEF3C7',
    suspended:'color:#dc2626;background:#FEE2E2', inactive:'color:#94a3b8;background:#F1F5F9'
  }[d.status] || 'color:#94a3b8;background:#F1F5F9';
  document.getElementById('mdStatus').innerHTML =
    `<span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:99px;${statusStyle}">${d.status || '—'}</span>`;

  // Info grid
  const fields = [
    ['bi-envelope',    'Email',           d.email   || '—'],
    ['bi-telephone',   'Phone',           d.phone   || '—'],
    ['bi-car-front',   'Vehicle',         d.vehicle || '—'],
    ['bi-credit-card', 'Plate No.',       d.plate   || '—'],
    ['bi-people',      'Seats',           d.seats   ? d.seats + ' seats' : '—'],
    ['bi-signpost',    'Total Rides',     String(d.rides)],
    ['bi-cash-coin',   'Total Earnings',  d.earnings],
    ['bi-calendar',    'Joined',          d.joined],
    ['bi-card-list',   'Licence Expiry',  d.license_expiry],
  ];

  const grid = fields.map(([icon, label, val]) => `
    <div style="padding:10px 12px;background:var(--hover-bg);border-radius:var(--radius-sm);border:1px solid var(--border)">
      <div style="font-size:10.5px;color:var(--text-subtle);margin-bottom:3px;display:flex;align-items:center;gap:5px">
        <i class="bi ${icon}" style="color:var(--accent)"></i>${label}
      </div>
      <div style="font-size:13px;font-weight:500;color:var(--text-primary)">${val}</div>
    </div>
  `).join('');

  const hasDocs = d.docs && d.docs.some(doc => doc.url);

  const docGrid = (d.docs || []).map(doc => `
    <div class="doc-thumb" onclick="${doc.url ? `openLightbox('${escJs(doc.url)}','${escJs(doc.label)}','${escJs(d.status)}','${escJs(id)}')` : "Toast.show('No document uploaded.','info')"}">
      ${doc.url
        ? `<img src="${escHtml(doc.url)}" alt="${escHtml(doc.label)}" onerror="this.parentNode.innerHTML='<div class=doc-no-img><i class=\\'bi bi-file-earmark-x\\'></i><span>Load error</span></div>'">`
        : `<div class="doc-no-img"><i class="bi bi-file-earmark-x"></i><span>Not uploaded</span></div>`}
      <div class="doc-label">${escHtml(doc.label)}</div>
    </div>
  `).join('');

  document.getElementById('mdBody').innerHTML = `
    <p class="section-title" style="margin-top:0">Driver Information</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">${grid}</div>
    <p class="section-title">Documents</p>
    <div class="doc-grid">${docGrid}</div>
  `;

  // Footer action — treat 'approved' same as 'active'
  let action = '';
  if (d.status === 'pending') {
    action = `<button class="btn-primary-glass" onclick="updateStatusFromModal('${escJs(id)}','active')"><i class="bi bi-check-lg"></i> Approve Driver</button>`;
  } else if (d.status === 'active' || d.status === 'approved') {
    action = `<button class="btn-glass" style="color:#dc2626;border-color:#dc2626" onclick="updateStatusFromModal('${escJs(id)}','suspended')"><i class="bi bi-slash-circle"></i> Suspend</button>`;
  } else if (d.status === 'suspended') {
    action = `<button class="btn-primary-glass" onclick="updateStatusFromModal('${escJs(id)}','active')"><i class="bi bi-arrow-counterclockwise"></i> Reactivate</button>`;
  }
  document.getElementById('mdFooter').innerHTML =
    `<button class="btn-glass" onclick="Modal.close('viewDriverModal')"><i class="bi bi-x"></i> Close</button>${action}`;

  Modal.open('viewDriverModal');
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) {
  return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"').replace(/\n/g,'\\n').replace(/\r/g,'\\r');
}

function openLightbox(url, label, status, driverId) {
  document.getElementById('lbImg').src            = url;
  document.getElementById('lbLabel').textContent  = label;

  let action = '';
  if (status === 'pending') {
    action = `<button class="btn-primary-glass" onclick="updateStatusFromModal('${escJs(driverId)}','active');closeLightbox()"><i class="bi bi-check-lg"></i> Approve Driver</button>`;
  } else if (status === 'active' || status === 'approved') {
    action = `<button class="btn-glass" style="color:#fff;background:#dc2626;border-color:#dc2626" onclick="updateStatusFromModal('${escJs(driverId)}','suspended');closeLightbox()"><i class="bi bi-slash-circle"></i> Suspend</button>`;
  } else if (status === 'suspended') {
    action = `<button class="btn-primary-glass" onclick="updateStatusFromModal('${escJs(driverId)}','active');closeLightbox()"><i class="bi bi-arrow-counterclockwise"></i> Reactivate</button>`;
  }
  document.getElementById('lbActions').innerHTML = action;
  document.getElementById('docLightbox').classList.add('open');
}

function closeLightbox() {
  document.getElementById('docLightbox').classList.remove('open');
  document.getElementById('lbImg').src = '';
}

document.getElementById('docLightbox').addEventListener('click', function(e) {
  if (e.target === this) closeLightbox();
});

async function updateStatusFromModal(id, status) {
  const labels = { active:'approve/activate', suspended:'suspend', inactive:'deactivate' };
  if (!confirm(`Are you sure you want to ${labels[status]||status} this driver?`)) return;
  await _doUpdateStatus(id, status);
}

async function updateStatus(id, status, btn) {
  const labels = { active:'activate', suspended:'suspend', inactive:'deactivate' };
  if (!confirm(`Are you sure you want to ${labels[status]||status} this driver?`)) return;
  if (btn) btn.disabled = true;
  await _doUpdateStatus(id, status, btn);
}

async function _doUpdateStatus(id, status, btn) {
  const fd = new FormData();
  fd.append('action', 'update_status');
  fd.append('id', id);
  fd.append('status', status);
  try {
    const res  = await fetch(window.location.href, { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      Toast.show('Driver status updated.', 'success');
      setTimeout(() => location.reload(), 800);
    } else {
      Toast.show(data.message || 'Update failed.', 'error');
      if (btn) btn.disabled = false;
    }
  } catch {
    Toast.show('Network error.', 'error');
    if (btn) btn.disabled = false;
  }
}

function exportDrivers() {
  Toast.show('Export feature coming soon.', 'info');
}

function togglePwd() {
  const inp = document.getElementById('adPassword');
  const eye = document.getElementById('adPwdEye');
  if (inp.type === 'password') {
    inp.type = 'text';
    eye.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    eye.className = 'bi bi-eye';
  }
}

async function submitAddDriver() {
  const name     = document.getElementById('adName').value.trim();
  const email    = document.getElementById('adEmail').value.trim();
  const password = document.getElementById('adPassword').value;
  const phone    = document.getElementById('adPhone').value.trim();
  const make     = document.getElementById('adVehicleMake').value.trim();
  const model    = document.getElementById('adVehicleModel').value.trim();
  const plate    = document.getElementById('adPlateNo').value.trim();
  const vehNo    = document.getElementById('adVehicleNo').value.trim();
  const seats    = document.getElementById('adSeats').value;
  const status   = document.getElementById('adStatus').value;

  if (!name)            { Toast.show('Full name is required.', 'error');         return; }
  if (!email)           { Toast.show('Email is required.', 'error');              return; }
  if (password.length < 8) { Toast.show('Password must be at least 8 characters.', 'error'); return; }

  const btn = document.getElementById('adSaveBtn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating…';

  const fd = new FormData();
  fd.append('action',         'add_driver');
  fd.append('full_name',      name);
  fd.append('email',          email);
  fd.append('password',       password);
  fd.append('phone',          phone);
  fd.append('vehicle_make',   make);
  fd.append('vehicle_model',  model);
  fd.append('plate_no',       plate);
  fd.append('vehicle_number', vehNo);
  fd.append('no_seats',       seats);
  fd.append('status',         status);

  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.show(data.message || 'Driver created.', 'success');
      Modal.close('addDriverModal');
      // clear form
      ['adName','adEmail','adPassword','adPhone','adVehicleMake','adVehicleModel','adPlateNo','adVehicleNo'].forEach(id => {
        document.getElementById(id).value = '';
      });
      setTimeout(() => location.reload(), 1000);
    } else {
      Toast.show(data.message || 'Failed to create driver.', 'error');
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  } catch {
    Toast.show('Network error — please try again.', 'error');
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}

// ── Keyup live search ────────────────────────────────────────────────
(function() {
  const input  = document.getElementById('driverSearchInput');
  const filter = document.getElementById('driverStatusFilter');
  const tbody  = document.querySelector('#driversTable tbody');
  const countEl = document.querySelector('.card-subtitle');
  let timer;

  function buildRow(d) {
    const id       = d.id || '';
    const name     = d.full_name || '—';
    const parts    = name.split(' ').filter(Boolean);
    const initials = parts.map(p => p[0]).join('').toUpperCase().slice(0,2) || '?';
    const isOnline = !!d.is_online;
    const status   = d.status || 'inactive';
    const statusBadge = (status === 'active' || status === 'approved') && isOnline
      ? `<span class='badge-pill badge-online'><span class='dot'></span>Online</span>`
      : { active:'<span class="badge-pill badge-active"><span class="dot"></span>Active</span>',
          approved:'<span class="badge-pill badge-active"><span class="dot"></span>Active</span>',
          pending:'<span class="badge-pill badge-pending"><span class="dot"></span>Pending</span>',
          suspended:'<span class="badge-pill badge-suspended"><span class="dot"></span>Suspended</span>',
          inactive:'<span class="badge-pill badge-inactive"><span class="dot"></span>Inactive</span>',
        }[status] || `<span class="badge-pill badge-inactive"><span class="dot"></span>${status}</span>`;

    const picHtml = d.profile_pic_url
      ? `<img src="${escHtml(d.profile_pic_url)}" alt="">`
      : escHtml(initials);
    const onlineDot = isOnline && status === 'active'
      ? `<span style="position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;background:#16a34a;border-radius:50%;border:2px solid #fff"></span>` : '';

    const vehicle  = escHtml(((d.vehicle_make||'') + ' ' + (d.vehicle_model||'')).trim() || '—');
    const plate    = escHtml(d.plate_no || '—');
    const seats    = d.no_seats ? ` · ${d.no_seats} seats` : '';
    const rides    = Number(d.total_rides||0).toLocaleString();
    const earnings = '€' + parseFloat(d.total_earnings||0).toFixed(2);
    const joined   = d.created_at ? new Date(d.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—';

    const hasLic = d.license_url, hasReg = d.vehicle_reg_url, hasIns = d.insurance_url;
    const docsOk = hasLic && hasReg && hasIns;
    const docBadge = docsOk
      ? `<span style="color:#16a34a;font-size:12px"><i class="bi bi-patch-check-fill"></i> Complete</span>`
      : `<span style="color:#d97706;font-size:12px"><i class="bi bi-exclamation-triangle"></i> Incomplete</span>`;

    const nameJson = JSON.stringify(name);
    const idJson   = JSON.stringify(id);
    let actionBtns = `
      <button class="btn-icon" title="View Details" onclick="viewDriver(${idJson})"><i class="bi bi-eye"></i></button>
      <button class="btn-icon" title="Assign Ride Types" onclick="openRideTypesModal(${idJson})"><i class="bi bi-tag"></i></button>
      <button class="btn-icon" title="Upload Documents" onclick="openUploadDocsModal(${idJson})"><i class="bi bi-cloud-arrow-up"></i></button>
      <button class="btn-icon danger" title="Delete Account" onclick="confirmDeleteDriver(${idJson},${nameJson})"><i class="bi bi-trash3"></i></button>`;
    if (status === 'pending') {
      actionBtns += `<button class="btn-icon success" title="Approve" onclick="updateStatus(${idJson},'active',this)"><i class="bi bi-check-lg"></i></button>`;
    } else if (status === 'active' || status === 'approved') {
      actionBtns += `<button class="btn-icon danger" title="Suspend" onclick="updateStatus(${idJson},'suspended',this)"><i class="bi bi-slash-circle"></i></button>`;
    } else if (status === 'suspended') {
      actionBtns += `<button class="btn-icon success" title="Reactivate" onclick="updateStatus(${idJson},'active',this)"><i class="bi bi-arrow-counterclockwise"></i></button>`;
    }

    return `<tr>
      <td><div class="user-cell">
        <div class="user-avatar-sm" style="position:relative">${picHtml}${onlineDot}</div>
        <div class="user-cell-info"><div class="name">${escHtml(name)}</div><div class="meta">${escHtml(id.slice(0,8))}…</div></div>
      </div></td>
      <td><div style="font-size:13px">${escHtml(d.email||'—')}</div><div style="font-size:11.5px;color:var(--text-muted)">${escHtml(d.phone||'—')}</div></td>
      <td><div style="font-size:13px;font-weight:500">${vehicle}</div><div style="font-size:11.5px;color:var(--text-muted)">${plate}${seats}</div></td>
      <td>${vehicleTypeBadgeJs(d.type)}</td>
      <td style="font-weight:500">${rides}</td>
      <td style="color:var(--accent);font-weight:600">${earnings}</td>
      <td>${docBadge}</td>
      <td>${statusBadge}</td>
      <td class="text-muted fs-12">${joined}</td>
      <td><div style="display:flex;gap:4px">${actionBtns}</div></td>
    </tr>`;
  }

  function vehicleTypeBadgeJs(typeInput) {
    if (!typeInput) return '<span class="text-muted fs-12">—</span>';
    let types = typeof typeInput === 'string' ? JSON.parse(typeInput) : typeInput;
    if (!Array.isArray(types)) types = [types];
    const html = types.map(t => {
      const label = (typeof t === 'object' ? t.label : null)
        || (typeof t === 'string' ? t.replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase()) : '?');
      return `<span class="badge-pill" style="background:#EDE9FE;color:#7c3aed;margin-right:4px;font-size:10.5px">${escHtml(label)}</span>`;
    }).join('');
    return html || '<span class="text-muted fs-12">—</span>';
  }

  function doSearch() {
    const q      = input.value.trim();
    const status = filter.value;
    const url    = `?page=drivers&action=search_ajax&q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}`;
    fetch(url)
      .then(r => r.json())
      .then(drivers => {
        if (!tbody) return;
        if (!drivers.length) {
          tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:32px;color:var(--text-muted)"><i class="bi bi-person-badge" style="font-size:28px;display:block;margin-bottom:8px"></i>No drivers found</td></tr>`;
        } else {
          // update _DD with fresh data
          drivers.forEach(d => {
            const rawType = d.type;
            let typeArr = typeof rawType === 'string' ? (JSON.parse(rawType)||[]) : (rawType||[]);
            const typeNames = typeArr.map(t => typeof t === 'object' ? (t.type||'') : t).filter(Boolean);
            _DD[d.id] = {
              id:d.id, name:d.full_name||'', email:d.email||'', phone:d.phone||'',
              status:d.status||'', vehicle:((d.vehicle_make||'')+' '+(d.vehicle_model||'')).trim(),
              plate:d.plate_no||'', seats:String(d.no_seats||''), rides:Number(d.total_rides||0),
              earnings:'€'+parseFloat(d.total_earnings||0).toFixed(2),
              joined: d.created_at ? new Date(d.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—',
              license_expiry: d.license_expiry ? new Date(d.license_expiry).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—',
              types: typeNames,
              docs:[
                {type:'license',    label:'Driving Licence',     url:d.license_url||''},
                {type:'vehicle_reg',label:'Vehicle Registration', url:d.vehicle_reg_url||''},
                {type:'insurance',  label:'Insurance',           url:d.insurance_url||''},
                {type:'nct',        label:'NCT Certificate',     url:d.nct_cert||''},
                {type:'rt',         label:'Road Tax',            url:d.rt_cert||''},
                {type:'suitability',label:'Suitability Cert',    url:d.suitability_cert||''},
              ],
            };
          });
          tbody.innerHTML = drivers.map(buildRow).join('');
        }
        if (countEl) countEl.textContent = drivers.length + ' result' + (drivers.length!==1?'s':'') + (drivers.length===20?' (showing top 20)':'');
      })
      .catch(() => Toast.show('Search error.', 'error'));
  }

  if (input) {
    input.addEventListener('keyup', () => { clearTimeout(timer); timer = setTimeout(doSearch, 380); });
  }
  if (filter) {
    filter.removeAttribute('onchange');
    filter.addEventListener('change', doSearch);
  }
})();

// ── Delete Driver ─────────────────────────────────────────────────────
let _delDriverId = null;

function confirmDeleteDriver(id, name) {
  _delDriverId = id;
  document.getElementById('delDriverName').textContent = name;
  Modal.open('deleteDriverModal');
}

async function executeDeleteDriver() {
  if (!_delDriverId) return;
  const btn = document.getElementById('delDriverBtn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Deleting…';

  const fd = new FormData();
  fd.append('action', 'delete_driver');
  fd.append('id', _delDriverId);

  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.show(data.message, 'success');
      Modal.close('deleteDriverModal');
      setTimeout(() => location.reload(), 900);
    } else {
      Toast.show(data.message || 'Delete failed.', 'error');
    }
  } catch {
    Toast.show('Network error.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}

// ── Upload Documents Modal ───────────────────────────────────────────
let _udDriverId = null;

function openUploadDocsModal(driverId) {
  _udDriverId = driverId;
  const d = _DD[driverId];
  if (!d) { Toast.show('Driver data not found.', 'error'); return; }

  document.getElementById('udDriverName').textContent = d.name;

  const slots = document.getElementById('udSlots');
  slots.innerHTML = (d.docs || []).map(doc => {
    const hasDoc  = !!doc.url;
    const isPdf   = doc.url && doc.url.toLowerCase().endsWith('.pdf');
    const thumbHtml = hasDoc
      ? (isPdf
          ? `<a href="${escHtml(doc.url)}" target="_blank" class="ud-thumb" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:#dc2626;font-size:20px"><i class="bi bi-file-earmark-pdf-fill"></i></a>`
          : `<img class="ud-thumb" src="${escHtml(doc.url)}" alt="${escHtml(doc.label)}" onclick="openLightbox('${escJs(doc.url)}','${escJs(doc.label)}','','')">`)
      : `<div class="ud-thumb-placeholder"><i class="bi bi-file-earmark" style="font-size:18px"></i></div>`;

    const statusHtml = hasDoc
      ? `<span class="ud-status" style="color:#16a34a"><i class="bi bi-patch-check-fill"></i> Uploaded</span>`
      : `<span class="ud-status" style="color:#d97706"><i class="bi bi-exclamation-triangle"></i> Not uploaded</span>`;

    return `<div class="ud-slot" id="udslot-${escHtml(doc.type)}">
      ${thumbHtml}
      <div class="ud-info">
        <div class="ud-label">${escHtml(doc.label)}</div>
        <div id="udstatus-${escHtml(doc.type)}">${statusHtml}</div>
        <div class="ud-file-row">
          <input type="file" class="ud-file-input" id="udfile-${escHtml(doc.type)}"
            accept=".jpg,.jpeg,.png,.pdf,.webp" onchange="udFileSelected('${escJs(doc.type)}', this)">
          <button class="btn-primary-glass ud-upload-btn" id="udbtn-${escHtml(doc.type)}"
            onclick="uploadDoc('${escJs(doc.type)}')" disabled>
            <i class="bi bi-cloud-arrow-up"></i> Upload
          </button>
        </div>
      </div>
    </div>`;
  }).join('');

  Modal.open('uploadDocsModal');
}

function udFileSelected(docType, input) {
  const btn = document.getElementById('udbtn-' + docType);
  btn.disabled = !input.files.length;
}

async function uploadDoc(docType) {
  const fileInput = document.getElementById('udfile-' + docType);
  const btn       = document.getElementById('udbtn-' + docType);
  const statusEl  = document.getElementById('udstatus-' + docType);
  if (!fileInput.files.length) return;

  const origHtml    = btn.innerHTML;
  btn.disabled      = true;
  btn.innerHTML     = '<i class="bi bi-arrow-repeat"></i> Uploading…';
  statusEl.innerHTML = '<span style="color:var(--text-muted);font-size:11.5px"><i class="bi bi-arrow-repeat"></i> Uploading…</span>';

  const fd = new FormData();
  fd.append('action',   'upload_doc');
  fd.append('id',       _udDriverId);
  fd.append('doc_type', docType);
  fd.append('doc_file', fileInput.files[0]);

  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.show('Document uploaded successfully.', 'success');
      // Update thumbnail and status inline without full reload
      const slot    = document.getElementById('udslot-' + docType);
      const isPdf   = data.url && data.url.toLowerCase().endsWith('.pdf');
      const oldThumb = slot.querySelector('.ud-thumb, .ud-thumb-placeholder');
      const newThumb = document.createElement(isPdf ? 'a' : 'img');
      if (isPdf) {
        newThumb.href   = data.url;
        newThumb.target = '_blank';
        newThumb.className = 'ud-thumb';
        newThumb.style.cssText = 'display:flex;align-items:center;justify-content:center;text-decoration:none;color:#dc2626;font-size:20px';
        newThumb.innerHTML = '<i class="bi bi-file-earmark-pdf-fill"></i>';
      } else {
        newThumb.src       = data.url;
        newThumb.alt       = docType;
        newThumb.className = 'ud-thumb';
        newThumb.onclick   = () => openLightbox(data.url, docType, '', '');
      }
      oldThumb.replaceWith(newThumb);
      statusEl.innerHTML = '<span style="color:#16a34a;font-size:11.5px"><i class="bi bi-patch-check-fill"></i> Uploaded</span>';
      fileInput.value = '';
      // Update _DD so the view driver modal also reflects the new URL
      const d = _DD[_udDriverId];
      if (d) { const doc = d.docs.find(x => x.type === docType); if (doc) doc.url = data.url; }
    } else {
      Toast.show(data.message || 'Upload failed.', 'error');
      statusEl.innerHTML = '<span style="color:#dc2626;font-size:11.5px"><i class="bi bi-x-circle"></i> ' + escHtml(data.message || 'Upload failed.') + '</span>';
    }
  } catch {
    Toast.show('Network error during upload.', 'error');
    statusEl.innerHTML = '<span style="color:#dc2626;font-size:11.5px"><i class="bi bi-x-circle"></i> Network error.</span>';
  } finally {
    btn.innerHTML = origHtml;
    btn.disabled  = !fileInput.files.length;
  }
}

// ── Ride Types Modal ─────────────────────────────────────────────────
let _rtDriverId = null;

function openRideTypesModal(driverId) {
  _rtDriverId = driverId;
  const d = _DD[driverId];
  if (!d) { Toast.show('Driver data not found.', 'error'); return; }

  document.getElementById('rtDriverName').textContent = d.name;

  const container = document.getElementById('rtChecksContainer');
  const emptyMsg  = document.getElementById('rtEmpty');

  if (!_RIDE_TYPES || _RIDE_TYPES.length === 0) {
    container.innerHTML = '';
    emptyMsg.style.display = 'block';
  } else {
    emptyMsg.style.display = 'none';
    const currentTypes = new Set(d.types || []);
    container.innerHTML = _RIDE_TYPES.map(rt => {
      const checked = currentTypes.has(rt.name) ? 'checked' : '';
      const label   = rt.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      const mult    = rt.multiplier ? `${parseFloat(rt.multiplier).toFixed(1)}× fare · ` : '';
      return `<label class="rt-card">
        <input type="checkbox" class="rt-cb" value="${escHtml(rt.name)}" ${checked}>
        <div>
          <div class="rt-name">${escHtml(label)}</div>
          ${rt.description ? `<div class="rt-desc">${escHtml(rt.description)}</div>` : ''}
          <div class="rt-meta"><i class="bi bi-people"></i> ${rt.seats || 4} seats &nbsp;·&nbsp; ${mult}${escHtml(rt.name)}</div>
        </div>
      </label>`;
    }).join('');
  }

  Modal.open('rideTypesModal');
}

async function saveRideTypes() {
  const checks = document.querySelectorAll('#rtChecksContainer .rt-cb:checked');
  const names  = Array.from(checks).map(c => c.value);

  const saveBtn  = document.getElementById('rtSaveBtn');
  const origHtml = saveBtn.innerHTML;
  saveBtn.disabled  = true;
  saveBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Saving…';

  const fd = new FormData();
  fd.append('action',     'assign_ride_types');
  fd.append('id',         _rtDriverId);
  fd.append('type_names', JSON.stringify(names));

  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.show(data.message, 'success');
      Modal.close('rideTypesModal');
      setTimeout(() => location.reload(), 700);
    } else {
      Toast.show(data.message || 'Update failed.', 'error');
    }
  } catch {
    Toast.show('Network error.', 'error');
  } finally {
    saveBtn.disabled  = false;
    saveBtn.innerHTML = origHtml;
  }
}
</script>
SCRIPTS;

// Inject PHP data into the script placeholders
$extraScripts = str_replace(
    'DRIVER_DATA_PLACEHOLDER',
    json_encode($driverDataMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE),
    $extraScripts
);
$extraScripts = str_replace(
    'RIDE_TYPES_PLACEHOLDER',
    json_encode($rideTypes ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE),
    $extraScripts
);
?>
