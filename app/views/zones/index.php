<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'map');
$tabs = ['map'=>'Service Area Map','zones'=>'Zone Management','restricted'=>'Restricted Zones','airport'=>'Airport / Fixed-Fare Zones'];
if (!isset($tabs[$tab])) $tab = 'map';
?>

<div class="page-header">
  <div>
    <h1>Zones &amp; Coverage</h1>
    <p>Define service areas, restricted zones, and special fare zones.</p>
  </div>
  <button class="btn-primary-glass" onclick="Toast.show('Create zone coming soon.','info')"><i class="bi bi-plus-lg"></i> Create Zone</button>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=zones&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'map'): ?>
<div class="glass-card" style="overflow:hidden">
  <div class="card-header-bar">
    <i class="bi bi-map" style="color:var(--accent)"></i>
    <div class="card-title">Interactive Service Area Map</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <button class="btn-glass" onclick="Toast.show('Draw zone mode coming soon.','info')"><i class="bi bi-pencil"></i> Draw Zone</button>
      <button class="btn-glass" onclick="Toast.show('Import coming soon.','info')"><i class="bi bi-upload"></i> Import GeoJSON</button>
    </div>
  </div>
  <div style="background:#E8ECF0;height:520px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px">
    <i class="bi bi-map" style="font-size:56px;color:#A0AEC0"></i>
    <div style="font-weight:600;color:var(--text-muted)">Google Maps Zone Editor</div>
    <div style="font-size:12px;color:var(--text-subtle)">Draw service area boundaries, restricted zones, and airport zones using the polygon tool.</div>
    <button class="btn-primary-glass mt-2" onclick="Toast.show('Maps API key required in Settings.','info')">
      <i class="bi bi-geo-alt"></i> Enable Map Editor
    </button>
  </div>
</div>

<?php elseif ($tab === 'zones'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-hexagon-fill" style="color:var(--accent)"></i>
    <div class="card-title">All Zones</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Create zone coming soon.','info')"><i class="bi bi-plus"></i> Create Zone</button>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Zone Name</th><th>Type</th><th>Area (km²)</th><th>Fare Override</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="6"><div class="empty-state"><i class="bi bi-hexagon"></i><h4>No zones configured</h4><p>Create zones to define your service area and special fare regions.</p></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'restricted'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-slash-circle-fill" style="color:#dc2626"></i>
    <div class="card-title">Restricted / No-Go Zones</div>
    <button class="btn-primary-glass" style="margin-left:auto;background:linear-gradient(135deg,#dc2626,#b91c1c)" onclick="Toast.show('Add restricted zone coming soon.','info')"><i class="bi bi-plus"></i> Add Restriction</button>
  </div>
  <div style="padding:16px">
    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:var(--radius-sm);padding:12px 16px;font-size:13px;color:#dc2626;margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Rides cannot be booked to/from restricted zones. Passengers will see an error if they try.
    </div>
    <table class="glass-table">
      <thead><tr><th>Zone Name</th><th>Restriction Type</th><th>Reason</th><th>Added By</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="6"><div class="empty-state" style="padding:32px"><i class="bi bi-slash-circle"></i><h4>No restricted zones</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-airplane-fill" style="color:var(--accent)"></i>
    <div class="card-title">Airport &amp; Fixed-Fare Zones</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Add zone coming soon.','info')"><i class="bi bi-plus"></i> Add Zone</button>
  </div>
  <div style="padding:16px">
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Fixed-fare zones override normal metered pricing with a flat rate for specific routes (e.g. Dublin Airport → City Centre).</p>
    <table class="glass-table">
      <thead><tr><th>Zone Name</th><th>From Area</th><th>To Area</th><th>Fixed Fare (€)</th><th>Vehicle Type</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state" style="padding:32px"><i class="bi bi-airplane"></i><h4>No fixed-fare zones configured</h4><p>Add Dublin Airport or other special fare zones.</p></div></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>
