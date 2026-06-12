<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'overview');
$tabs = ['overview'=>'Revenue Overview','payouts'=>'Driver Payouts','refunds'=>'Refunds & Adjustments','transactions'=>'Payment Transactions','commission'=>'Commission Settings','invoices'=>'Corporate Invoices'];
if (!isset($tabs[$tab])) $tab = 'overview';
?>

<div class="page-header">
  <div>
    <h1>Finance &amp; Payments</h1>
    <p>Revenue breakdown, driver payouts, refunds, and commission management.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass" onclick="Toast.show('Export coming soon.','info')"><i class="bi bi-filetype-csv"></i> CSV</button>
    <button class="btn-glass" onclick="Toast.show('PDF export coming soon.','info')"><i class="bi bi-filetype-pdf"></i> PDF</button>
  </div>
</div>

<!-- KPI cards -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
  <?php foreach ([
    ['bi-cash-coin',      'Total Revenue',       '€—',   '#F37A20'],
    ['bi-graph-up',       'Revenue This Month',  '€—',   '#16a34a'],
    ['bi-person-check',   'Driver Payouts (MTD)','€—',   '#7c3aed'],
    ['bi-arrow-return-left','Refunds (MTD)',      '€—',   '#dc2626'],
    ['bi-percent',        'Avg Commission',       '—%',   '#d97706'],
    ['bi-credit-card',    'Transactions Today',  '—',    '#0ea5e9'],
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
  <a href="?page=finance&tab=<?=$slug?>"
     style="padding:8px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-bar-chart" style="color:var(--accent)"></i><div class="card-title">Revenue by Day (Last 30 Days)</div></div>
    <div style="padding:20px"><canvas id="revenueChart" height="200"></canvas></div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-pie-chart" style="color:var(--accent)"></i><div class="card-title">Revenue Breakdown</div></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <?php foreach (['Commission (Platform)'=>'70%','Driver Earnings'=>'25%','Refunds'=>'5%'] as $label=>$pct): ?>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
          <span><?=$label?></span><span style="font-weight:600"><?=$pct?></span>
        </div>
        <div style="height:6px;background:#F0F2F5;border-radius:99px"><div style="height:100%;width:<?=$pct?>;background:var(--accent);border-radius:99px"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'payouts'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-wallet2" style="color:var(--accent)"></i><div class="card-title">Driver Payouts</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <select class="glass-select" style="width:130px"><option>This Month</option><option>Last Month</option></select>
      <button class="btn-primary-glass" onclick="Toast.show('Batch payout coming soon.','info')"><i class="bi bi-send"></i> Process Payouts</button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Driver</th><th>Period</th><th>Total Rides</th><th>Gross Earnings</th><th>Commission</th><th>Net Payout</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="8"><div class="empty-state"><i class="bi bi-wallet2"></i><h4>No payout records</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'refunds'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-arrow-return-left" style="color:#dc2626"></i><div class="card-title">Refunds &amp; Adjustments</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Issue refund coming soon.','info')"><i class="bi bi-plus"></i> Issue Refund</button>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Ride ID</th><th>Passenger</th><th>Amount</th><th>Reason</th><th>Requested By</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="8"><div class="empty-state"><i class="bi bi-arrow-return-left"></i><h4>No refunds</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'transactions'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-credit-card" style="color:var(--accent)"></i><div class="card-title">Payment Gateway Transactions</div></div>
  <div class="filter-bar" style="padding:14px 18px;border-bottom:1px solid var(--border)">
    <div class="glass-input-icon" style="flex:1;min-width:200px">
      <i class="bi bi-search input-icon"></i><input class="glass-input" placeholder="Search transaction ID...">
    </div>
    <select class="glass-select" style="width:130px"><option>All Status</option><option>Success</option><option>Failed</option><option>Pending</option></select>
    <input type="date" class="glass-input" style="width:145px">
    <input type="date" class="glass-input" style="width:145px">
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Transaction ID</th><th>Type</th><th>Amount</th><th>Gateway</th><th>Reference</th><th>Date</th><th>Status</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-credit-card"></i><h4>No transactions</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'commission'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-percent" style="color:var(--accent)"></i><div class="card-title">Commission &amp; Fee Structure</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Save settings coming soon.','info')"><i class="bi bi-check-lg"></i> Save Changes</button>
  </div>
  <div style="padding:24px;max-width:520px">
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php foreach (['Platform Commission (%)'=>'15','Base Fare (€)'=>'3.00','Per KM Rate (€)'=>'1.20','Per Minute Rate (€)'=>'0.20','Minimum Fare (€)'=>'5.00','Booking Fee (€)'=>'0.50'] as $label=>$val): ?>
      <div>
        <label class="form-label"><?=$label?></label>
        <input type="text" class="glass-input" value="<?=$val?>">
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-receipt" style="color:var(--accent)"></i><div class="card-title">Corporate Invoices</div></div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Invoice #</th><th>Company</th><th>Period</th><th>Rides</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i><h4>No corporate invoices</h4></div></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
if (document.getElementById('revenueChart')) {
  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: Array.from({length:30},(_,i)=>`Day ${i+1}`),
      datasets:[{label:'Revenue (€)',data:Array.from({length:30},()=>0),backgroundColor:'rgba(243,122,32,0.6)',borderRadius:4}]
    },
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#F0F2F5'},ticks:{color:'#94a3b8'}},x:{grid:{display:false},ticks:{display:false}}}}
  });
}
</script>
