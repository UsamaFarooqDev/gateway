<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'tickets');
$tabs = ['tickets'=>'Helpdesk Tickets','disputes'=>'Dispute Resolution','categories'=>'Categories & Tagging','sla'=>'SLA Tracking'];
if (!isset($tabs[$tab])) $tab = 'tickets';

$priorityColors = ['urgent'=>'#dc2626','high'=>'#d97706','medium'=>'#7c3aed','low'=>'#16a34a'];
?>

<div class="page-header">
  <div>
    <h1>Support &amp; Disputes</h1>
    <p>Manage helpdesk tickets, dispute resolution, and SLA compliance.</p>
  </div>
  <button class="btn-primary-glass" onclick="Toast.show('Create ticket coming soon.','info')"><i class="bi bi-plus-lg"></i> New Ticket</button>
</div>

<div class="stats-grid">
  <?php foreach ([
    ['bi-ticket-perforated','Open Tickets',     '—','#F37A20'],
    ['bi-clock',            'Avg Response Time','—','#7c3aed'],
    ['bi-exclamation-diamond','Disputes Open',  '—','#dc2626'],
    ['bi-check-circle',     'Resolved Today',  '—','#16a34a'],
  ] as [$i,$l,$v,$c]): ?>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:<?=$c?>22;color:<?=$c?>"><i class="bi <?=$i?>"></i></div>
    <div class="stat-value"><?=$v?></div>
    <div class="stat-label"><?=$l?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=support&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'tickets'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-headset" style="color:var(--accent)"></i><div class="card-title">Helpdesk Tickets</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <select class="glass-select" style="width:120px"><option>All Status</option><option>Open</option><option>In Progress</option><option>Resolved</option><option>Closed</option></select>
      <select class="glass-select" style="width:110px"><option>All Priority</option><option>Urgent</option><option>High</option><option>Medium</option><option>Low</option></select>
    </div>
  </div>
  <div class="filter-bar" style="padding:12px 18px;border-bottom:1px solid var(--border)">
    <div class="glass-input-icon" style="flex:1">
      <i class="bi bi-search input-icon"></i>
      <input class="glass-input" placeholder="Search tickets by ID, user, subject...">
    </div>
    <select class="glass-select" style="width:160px"><option>All Categories</option><option>Billing</option><option>Driver Issue</option><option>App Bug</option><option>Safety</option></select>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Ticket #</th><th>Subject</th><th>Submitted By</th><th>Category</th><th>Priority</th><th>Assigned To</th><th>Created</th><th>SLA</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="10"><div class="empty-state"><i class="bi bi-headset"></i><h4>No tickets</h4><p>Support tickets will appear here once passengers or drivers submit them.</p></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'disputes'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-exclamation-diamond-fill" style="color:#dc2626"></i><div class="card-title">Dispute Resolution</div>
  </div>
  <div class="filter-bar" style="padding:12px 18px;border-bottom:1px solid var(--border)">
    <div class="glass-input-icon" style="flex:1">
      <i class="bi bi-search input-icon"></i>
      <input class="glass-input" placeholder="Search by ride ID, passenger, driver...">
    </div>
    <select class="glass-select" style="width:150px"><option>All Status</option><option>Pending Review</option><option>Under Investigation</option><option>Resolved</option></select>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Dispute #</th><th>Ride ID</th><th>Filed By</th><th>Against</th><th>Reason</th><th>Amount at Stake</th><th>Filed</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="9"><div class="empty-state"><i class="bi bi-exclamation-diamond"></i><h4>No open disputes</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'categories'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-tags-fill" style="color:var(--accent)"></i><div class="card-title">Complaint Categories &amp; Tagging</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Add category coming soon.','info')"><i class="bi bi-plus"></i> Add Category</button>
  </div>
  <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
    <?php foreach (['Billing Issue','Driver Behaviour','App / Tech Bug','Safety Concern','Ride Cancellation','Lost Property','Wrong Fare Charged','Route Dispute'] as $cat): ?>
    <div style="padding:12px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-weight:600;font-size:13px"><?=$cat?></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px">0 open tickets</div>
      </div>
      <button class="btn-icon" onclick="Toast.show('Edit coming soon.','info')"><i class="bi bi-pencil"></i></button>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-clock-history" style="color:var(--accent)"></i><div class="card-title">SLA Tracking</div></div>
  <div style="padding:24px">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-bottom:24px">
      <?php foreach (['First Response (Urgent)'=>['Target'=>'1 hour','Avg'=>'—'],'First Response (High)'=>['Target'=>'4 hours','Avg'=>'—'],'Resolution (Standard)'=>['Target'=>'24 hours','Avg'=>'—'],'Dispute Resolution'=>['Target'=>'72 hours','Avg'=>'—']] as $name=>$vals): ?>
      <div style="padding:16px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
        <div style="font-size:12px;color:var(--text-subtle)"><?=$name?></div>
        <div style="font-size:20px;font-weight:700;color:var(--text-primary);margin-top:4px"><?=$vals['Avg']?></div>
        <div style="font-size:11px;color:var(--text-muted)">Target: <?=$vals['Target']?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <table class="glass-table">
      <thead><tr><th>Ticket #</th><th>Priority</th><th>First Response</th><th>Resolution Time</th><th>SLA Met</th><th>Breach Reason</th></tr></thead>
      <tbody><tr><td colspan="6"><div class="empty-state" style="padding:32px"><i class="bi bi-clock"></i><h4>No SLA data yet</h4></div></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>
