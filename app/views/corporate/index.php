<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'clients');
$tabs = ['clients' => 'Client Profiles', 'employees' => 'Employees / Users', 'policies' => 'Ride Policies', 'invoices' => 'Invoicing & Billing', 'reports' => 'Usage Reports'];
if (!isset($tabs[$tab])) $tab = 'clients';
?>

<div class="page-header">
  <div>
    <h1>Corporate Accounts</h1>
    <p>Manage business clients, ride policies, and corporate billing.</p>
  </div>
  <button class="btn-primary-glass" onclick="Toast.show('Add client form coming soon.','info')"><i class="bi bi-plus-lg"></i> Add Client</button>
</div>

<div class="stats-grid">
  <?php foreach ([
    ['bi-building',       'Total Clients',     '—', '#7c3aed'],
    ['bi-people',         'Total Employees',   '—', '#F37A20'],
    ['bi-receipt',        'Invoices This Month','—','#16a34a'],
    ['bi-cash',           'Monthly Revenue',   '—', '#d97706'],
  ] as [$i,$l,$v,$c]): ?>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:<?=$c?>22;color:<?=$c?>"><i class="bi <?=$i?>"></i></div>
    <div class="stat-value"><?=$v?></div>
    <div class="stat-label"><?=$l?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=corporate&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'clients'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-building" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Clients</div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Company</th><th>Contact</th><th>Employees</th><th>Monthly Rides</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-building"></i><h4>No corporate clients yet</h4><p>Add your first client to get started.</p></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'employees'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-people" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Employees</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <select class="glass-select" style="width:180px"><option>All Companies</option></select>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Employee</th><th>Company</th><th>Email</th><th>Rides This Month</th><th>Spend This Month</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><h4>No employees found</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'policies'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-shield-check" style="color:var(--accent)"></i>
    <div class="card-title">Ride Policies</div>
  </div>
  <div style="padding:24px">
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px">Configure spend limits, allowed zones, and ride rules per corporate account.</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <?php foreach (['Spend Limit per Ride','Monthly Spend Cap','Allowed Pickup Zones','Allowed Drop-off Zones','Permitted Hours','Vehicle Types Allowed'] as $policy): ?>
      <div style="padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
        <div style="font-size:12px;color:var(--text-subtle);margin-bottom:4px">Policy</div>
        <div style="font-weight:600"><?=$policy?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Not configured</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-receipt" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Invoices</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <button class="btn-glass" onclick="Toast.show('Generate invoice coming soon.','info')"><i class="bi bi-plus"></i> Generate Invoice</button>
      <button class="btn-glass" onclick="Toast.show('Export coming soon.','info')"><i class="bi bi-download"></i> Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Invoice #</th><th>Company</th><th>Period</th><th>Total Rides</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i><h4>No invoices yet</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-bar-chart" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Usage Reports</div>
    <div style="margin-left:auto"><button class="btn-glass" onclick="Toast.show('Export coming soon.','info')"><i class="bi bi-download"></i> Export CSV</button></div>
  </div>
  <div class="empty-state" style="padding:64px"><i class="bi bi-bar-chart"></i><h4>No report data yet</h4><p>Reports will generate as corporate rides accumulate.</p></div>
</div>
<?php endif; ?>
