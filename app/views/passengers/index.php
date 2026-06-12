<?php
$activeFilter = $filters['status'] ?? 'all';
$searchQuery  = $filters['search'] ?? '';

function passengerStatusBadge(string $status): string {
    $map = [
        'active'    => ['badge-active',    'Active'],
        'suspended' => ['badge-suspended', 'Suspended'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-inactive', ucfirst($status)];
    return "<span class='badge-pill {$cls}'><span class='dot'></span>{$label}</span>";
}
?>

<!-- Page header -->
<div class="page-header">
  <div>
    <h1>Passenger Management</h1>
    <p>View and manage all registered passenger accounts.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass" onclick="exportPassengers()">
      <i class="bi bi-download"></i> Export
    </button>
  </div>
</div>

<!-- Stats strip -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <?php
  $strips = [
      ['all',       'Total Passengers', $counts['total'],     'bi-people-fill',      'var(--accent)'],
      ['active',    'Active',           $counts['active'],    'bi-check-circle',     '#22c55e'],
      ['suspended', 'Suspended',        $counts['suspended'], 'bi-slash-circle',     '#ef4444'],
      [null,        'New Today',        $counts['new_today'], 'bi-person-plus-fill', '#818cf8'],
  ];
  foreach ($strips as [$slug, $label, $count, $icon, $color]):
      $isActive = $slug !== null && $activeFilter === $slug;
      $href     = $slug !== null ? "?page=passengers&status={$slug}&search=" . urlencode($searchQuery) : '#';
  ?>
  <a href="<?= $href ?>"
     style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:<?= $isActive?'rgba(243,122,32,0.08)':'#fff' ?>;border:1px solid <?= $isActive?'var(--accent)':'var(--border)' ?>;border-radius:var(--radius-sm);text-decoration:none;transition:var(--t);min-width:140px;box-shadow:var(--shadow-sm)">
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
    <input type="hidden" name="page" value="passengers">
    <div class="filter-bar" style="padding:16px 20px">

      <div class="glass-input-icon" style="flex:1;min-width:220px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input"
               placeholder="Search name, email, phone..."
               value="<?= htmlspecialchars($searchQuery) ?>">
      </div>

      <select name="status" class="glass-select" style="width:160px" onchange="this.form.submit()">
        <option value="all"       <?= $activeFilter==='all'       ?'selected':'' ?>>All Passengers</option>
        <option value="active"    <?= $activeFilter==='active'    ?'selected':'' ?>>Active</option>
        <option value="suspended" <?= $activeFilter==='suspended' ?'selected':'' ?>>Suspended</option>
      </select>

      <button type="submit" class="btn-primary-glass">
        <i class="bi bi-search"></i> Search
      </button>

      <?php if ($searchQuery || $activeFilter !== 'all'): ?>
      <a href="?page=passengers" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>

    </div>
  </form>
</div>

<!-- Passengers table -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-people-fill" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Passengers</div>
      <div class="card-subtitle"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <?php if (empty($passengers)): ?>
    <div class="empty-state">
      <i class="bi bi-people"></i>
      <h4>No passengers found</h4>
      <p><?= $searchQuery ? 'Try a different search term.' : 'No passengers match the selected filter.' ?></p>
    </div>
    <?php else: ?>
    <table class="glass-table" id="passengersTable">
      <thead>
        <tr>
          <th>Passenger</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Total Rides</th>
          <th>Total Spent</th>
          <th>Rating</th>
          <th>Status</th>
          <th>Registered</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($passengers as $p): ?>
        <?php
          $name     = trim($p['name'] ?? '');
          $initials = implode('', array_map(fn($part) => strtoupper($part[0]), array_filter(array_slice(explode(' ', $name), 0, 2))));
          $initials = $initials ?: '?';

          $avatarColors = [
            'linear-gradient(135deg,#F37A20,#e06010)',
            'linear-gradient(135deg,#6366f1,#4f46e5)',
            'linear-gradient(135deg,#22c55e,#16a34a)',
            'linear-gradient(135deg,#f59e0b,#d97706)',
            'linear-gradient(135deg,#ec4899,#db2777)',
            'linear-gradient(135deg,#14b8a6,#0d9488)',
          ];
          // Use a numeric hash of UUID for color
          $colorIdx  = hexdec(substr($p['id'], 0, 4)) % count($avatarColors);
          $avatarBg  = $avatarColors[$colorIdx];

          $emailVerified = (bool)($p['is_email_verified'] ?? false);
          $avgRating     = (float)($p['avg_rating'] ?? 0);
        ?>
        <tr>

          <!-- Passenger -->
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm" style="background:<?= $avatarBg ?>">
                <?php if (!empty($p['photo_url'])): ?>
                  <img src="<?= htmlspecialchars($p['photo_url']) ?>" alt="">
                <?php else: ?>
                  <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
              </div>
              <div class="user-cell-info">
                <div class="name"><?= htmlspecialchars($name ?: '—') ?></div>
                <div class="meta"><?= htmlspecialchars(substr($p['id'], 0, 8)) ?>…</div>
              </div>
            </div>
          </td>

          <!-- Contact (phone) -->
          <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($p['phone'] ?? '—') ?></td>

          <!-- Email with verified badge -->
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($p['email'] ?? '—') ?></div>
            <?php if ($emailVerified): ?>
            <div style="font-size:10.5px;color:#22c55e;margin-top:1px"><i class="bi bi-patch-check-fill"></i> Verified</div>
            <?php else: ?>
            <div style="font-size:10.5px;color:#f59e0b;margin-top:1px"><i class="bi bi-exclamation-triangle"></i> Unverified</div>
            <?php endif; ?>
          </td>

          <!-- Rides -->
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <i class="bi bi-car-front-fill" style="color:var(--accent);font-size:13px"></i>
              <span style="font-weight:500"><?= number_format((int)($p['total_rides'] ?? 0)) ?></span>
            </div>
          </td>

          <!-- Spent -->
          <td style="color:var(--accent);font-weight:600">
            €<?= number_format((float)($p['total_spent'] ?? 0), 2) ?>
          </td>

          <!-- Rating (driver's avg rating of this passenger) -->
          <td>
            <?php if ($avgRating > 0): ?>
            <div class="rating-cell">
              <i class="bi bi-star-fill" style="font-size:12px"></i>
              <?= number_format($avgRating, 1) ?>
            </div>
            <?php else: ?>
            <span class="text-muted fs-12">—</span>
            <?php endif; ?>
          </td>

          <!-- Status -->
          <td><?= passengerStatusBadge($p['status'] ?? 'active') ?></td>

          <!-- Registered -->
          <td class="text-muted fs-12">
            <?= !empty($p['created_at']) ? date('d M Y', strtotime($p['created_at'])) : '—' ?>
          </td>

          <!-- Actions -->
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="View Details"
                onclick="viewPassenger('<?= htmlspecialchars($p['id']) ?>', <?= htmlspecialchars(json_encode([
                    'name'     => $name,
                    'email'    => $p['email']  ?? '',
                    'phone'    => $p['phone']  ?? '',
                    'rides'    => (int)($p['total_rides'] ?? 0),
                    'spent'    => '€'.number_format((float)($p['total_spent']??0),2),
                    'rating'   => $avgRating > 0 ? number_format($avgRating,1) : 'N/A',
                    'verified' => (bool)($p['is_email_verified']??false),
                    'status'   => $p['status']     ?? 'active',
                    'joined'   => !empty($p['created_at']) ? date('d M Y',strtotime($p['created_at'])) : '—',
                ]), ENT_QUOTES) ?>)">
                <i class="bi bi-eye"></i>
              </button>

              <?php if (($p['status'] ?? '') === 'active'): ?>
              <button class="btn-icon danger" title="Suspend"
                onclick="updatePassengerStatus('<?= htmlspecialchars($p['id']) ?>', 'suspended', this)">
                <i class="bi bi-slash-circle"></i>
              </button>
              <?php else: ?>
              <button class="btn-icon success" title="Reactivate"
                onclick="updatePassengerStatus('<?= htmlspecialchars($p['id']) ?>', 'active', this)">
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
    <span>
      Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= number_format($total) ?>
    </span>
    <div class="pagination-controls">
      <?php if ($page > 1): ?>
      <a href="?page=passengers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page-1 ?>" class="page-btn">
        <i class="bi bi-chevron-left"></i>
      </a>
      <?php endif; ?>
      <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
      <a href="?page=passengers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $i ?>"
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=passengers&status=<?= urlencode($activeFilter) ?>&search=<?= urlencode($searchQuery) ?>&p=<?= $page+1 ?>" class="page-btn">
        <i class="bi bi-chevron-right"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- View Passenger Modal -->
<div class="modal-overlay" id="viewPassengerModal">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header">
      <div class="user-avatar-sm" style="width:40px;height:40px;font-size:15px" id="modalPassAvatar"></div>
      <div class="flex-1">
        <div class="modal-title" id="modalPassName"></div>
        <div class="fs-12 text-muted" id="modalPassId"></div>
      </div>
      <button class="modal-close" onclick="Modal.close('viewPassengerModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" id="modalPassDetails"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('viewPassengerModal')"><i class="bi bi-x"></i> Close</button>
    </div>
  </div>
</div>

<?php $extraScripts = <<<'HTML'
<script>
function viewPassenger(id, data) {
  document.getElementById('modalPassAvatar').textContent =
    (data.name || '?').split(' ').map(p=>p[0]).join('').toUpperCase().slice(0,2);
  document.getElementById('modalPassName').textContent = data.name || '—';
  document.getElementById('modalPassId').textContent   = id.slice(0,8) + '…';

  const fields = [
    ['bi-envelope',    'Email',         data.email],
    ['bi-telephone',   'Phone',         data.phone],
    ['bi-car-front',   'Total Rides',   String(data.rides)],
    ['bi-cash-coin',   'Total Spent',   data.spent],
    ['bi-star-fill',   'Rating',        data.rating !== 'N/A' ? data.rating + ' ★' : 'N/A'],
    ['bi-patch-check', 'Email',         data.verified ? '✓ Verified' : '✗ Not Verified'],
    ['bi-toggle-on',   'Status',        data.status],
    ['bi-calendar',    'Registered',    data.joined],
  ];

  document.getElementById('modalPassDetails').innerHTML = fields.map(([icon, label, val]) => `
    <div style="padding:10px 12px;background:var(--hover-bg);border-radius:var(--radius-sm);border:1px solid var(--border)">
      <div style="font-size:11px;color:var(--text-subtle);margin-bottom:4px;display:flex;align-items:center;gap:5px">
        <i class="bi ${icon}" style="color:var(--accent)"></i>${label}
      </div>
      <div style="font-size:13.5px;font-weight:500">${val || '—'}</div>
    </div>
  `).join('');

  Modal.open('viewPassengerModal');
}

async function updatePassengerStatus(id, status, btn) {
  const labels = { active:'reactivate', suspended:'suspend' };
  if (!confirm(`Are you sure you want to ${labels[status] || status} this passenger?`)) return;

  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', 'update_status');
  fd.append('id', id);
  fd.append('status', status);

  const res  = await fetch(window.location.href, { method:'POST', body:fd });
  const data = await res.json();

  if (data.success) {
    Toast.show('Passenger status updated.', 'success');
    setTimeout(() => location.reload(), 800);
  } else {
    Toast.show(data.message || 'Failed to update.', 'error');
    btn.disabled = false;
  }
}

function exportPassengers() {
  Toast.show('Export feature coming soon.', 'info');
}
</script>
HTML;
?>
