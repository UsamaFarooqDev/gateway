<?php
$tabs = ['overview'=>'Revenue Overview','ride_invoices'=>'Invoice Management','payouts'=>'Driver Payouts','refunds'=>'Refunds & Adjustments','transactions'=>'Payment Transactions','commission'=>'Commission Settings','invoices'=>'Corporate Invoices'];

$he  = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$eur = fn($v) => '€' . number_format((float)($v ?? 0), 2);

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$driverId = $_GET['driver_id'] ?? '';
$search   = trim($_GET['search'] ?? '');
$fareMin  = $_GET['fare_min'] ?? '';
$fareMax  = $_GET['fare_max'] ?? '';
$sort     = $_GET['sort'] ?? 'date_desc';
$method   = $_GET['method'] ?? 'all';

$riQs = 'tab=ride_invoices'
    . '&date_from=' . urlencode($dateFrom)
    . '&date_to='   . urlencode($dateTo)
    . '&driver_id=' . urlencode($driverId)
    . '&search='    . urlencode($search)
    . '&fare_min='  . urlencode($fareMin)
    . '&fare_max='  . urlencode($fareMax)
    . '&sort='      . urlencode($sort);

$txQs = 'tab=transactions'
    . '&date_from=' . urlencode($dateFrom)
    . '&date_to='   . urlencode($dateTo)
    . '&method='    . urlencode($method)
    . '&search='    . urlencode($search);
?>

<div class="page-header">
  <div>
    <h1>Finance &amp; Payments</h1>
    <p>Revenue breakdown, driver wallets, refunds, and commission management.</p>
  </div>
</div>

<!-- KPI cards -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
  <?php foreach ([
    ['bi-cash-coin',         'Total Revenue',         $eur($kpis['total_revenue']),                              '#F37A20'],
    ['bi-graph-up',          'Revenue This Month',    $eur($kpis['revenue_mtd']),                                '#16a34a'],
    ['bi-person-check',      'Driver Earnings (MTD)', $eur($kpis['driver_earnings_mtd']),                        '#7c3aed'],
    ['bi-arrow-return-left', 'Refunds (MTD)',         $eur($kpis['adjustments_mtd']),                            '#dc2626'],
    ['bi-percent',           'Avg Commission',        number_format($kpis['avg_commission_pct'], 1) . '%',       '#d97706'],
    ['bi-credit-card',       'Transactions Today',    number_format($kpis['transactions_today']),                '#0ea5e9'],
  ] as [$i, $l, $v, $c]): ?>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:<?= $c ?>22;color:<?= $c ?>"><i class="bi <?= $i ?>"></i></div>
    <div class="stat-value"><?= $v ?></div>
    <div class="stat-label"><?= $l ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=finance&tab=<?= $slug ?>"
     style="padding:8px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?= $tab === $slug ? 'var(--accent)' : 'var(--border)' ?>;background:<?= $tab === $slug ? 'var(--accent-soft)' : '#fff' ?>;color:<?= $tab === $slug ? 'var(--accent)' : 'var(--text-muted)' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ══════════════════ REVENUE OVERVIEW ══════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-bar-chart" style="color:var(--accent)"></i><div class="card-title">Revenue by Day (Last 30 Days)</div></div>
    <div style="padding:20px"><canvas id="revenueChart" height="200"></canvas></div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-pie-chart" style="color:var(--accent)"></i><div class="card-title">Revenue Breakdown (This Month)</div></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <?php foreach ([
        ['Commission (Platform)', $kpis['breakdown']['commission_pct']],
        ['Driver Earnings',       $kpis['breakdown']['driver_pct']],
        ['Refunds & Adjustments', $kpis['breakdown']['adjustments_pct']],
      ] as [$label, $pct]): ?>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
          <span><?= $label ?></span><span style="font-weight:600"><?= number_format($pct, 1) ?>%</span>
        </div>
        <div style="height:6px;background:#F0F2F5;border-radius:99px"><div style="height:100%;width:<?= min(100, $pct) ?>%;background:var(--accent);border-radius:99px"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'ride_invoices'): ?>
<!-- ══════════════════ INVOICE MANAGEMENT ══════════════════ -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="finance">
    <input type="hidden" name="tab" value="ride_invoices">
    <div class="filter-bar" style="padding:14px 18px;flex-wrap:wrap;gap:10px">
      <input type="date" name="date_from" class="glass-input" style="width:145px" title="From date" value="<?= $he($dateFrom) ?>">
      <input type="date" name="date_to" class="glass-input" style="width:145px" title="To date" value="<?= $he($dateTo) ?>">
      <select name="driver_id" class="glass-select" style="width:170px">
        <option value="">All Drivers</option>
        <?php foreach ($drivers as $d): ?>
        <option value="<?= $he($d['id']) ?>" <?= $driverId === $d['id'] ? 'selected' : '' ?>><?= $he($d['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="glass-input-icon" style="min-width:170px;flex:1">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input" placeholder="Pickup / destination..." value="<?= $he($search) ?>">
      </div>
      <input type="number" name="fare_min" class="glass-input" style="width:95px" placeholder="Min €" step="0.01" value="<?= $he($fareMin) ?>">
      <input type="number" name="fare_max" class="glass-input" style="width:95px" placeholder="Max €" step="0.01" value="<?= $he($fareMax) ?>">
      <select name="sort" class="glass-select" style="width:155px">
        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
        <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>>Oldest First</option>
        <option value="fare_desc" <?= $sort === 'fare_desc' ? 'selected' : '' ?>>Highest Fare</option>
        <option value="fare_asc"  <?= $sort === 'fare_asc'  ? 'selected' : '' ?>>Lowest Fare</option>
      </select>
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Filter</button>
      <?php if ($dateFrom || $dateTo || $driverId || $search || $fareMin !== '' || $fareMax !== ''): ?>
      <a href="?page=finance&tab=ride_invoices" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
      <a href="?page=finance&<?= $riQs ?>&export=csv" class="btn-glass"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
      <button type="button" class="btn-primary-glass" id="riStatementBtn" onclick="downloadFilteredInvoicesPdf('riStatementBtn')"><i class="bi bi-file-earmark-pdf"></i> Download All (PDF)</button>
    </div>
  </form>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="glass-card stat-card">
    <div class="stat-icon"><i class="bi bi-receipt"></i></div>
    <div class="stat-value"><?= number_format($stats['count']) ?></div>
    <div class="stat-label">Invoices (Filtered)</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#DCFCE722;color:#16a34a"><i class="bi bi-cash-coin"></i></div>
    <div class="stat-value" style="color:#16a34a"><?= $eur($stats['total_revenue']) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#F37A2022;color:#F37A20"><i class="bi bi-percent"></i></div>
    <div class="stat-value" style="color:#F37A20"><?= $eur($stats['total_commission']) ?></div>
    <div class="stat-label">PowerCabs Commission (10%)</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#EDE9FE22;color:#7c3aed"><i class="bi bi-wallet2"></i></div>
    <div class="stat-value" style="color:#7c3aed"><?= $eur($stats['total_payout']) ?></div>
    <div class="stat-label">Driver Earnings</div>
  </div>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-receipt" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Invoices</div>
      <div class="card-subtitle"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <?php if (empty($invoices)): ?>
    <div class="empty-state">
      <i class="bi bi-receipt"></i>
      <h4>No invoices found</h4>
      <p>No completed rides match the selected filters.</p>
    </div>
    <?php else: ?>
    <table class="glass-table">
      <thead>
        <tr>
          <th>Ride ID</th>
          <th>Date</th>
          <th>Passenger</th>
          <th>Driver</th>
          <th>Fare</th>
          <th>Commission</th>
          <th>Driver Earnings</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $r):
          $fare       = (float)($r['fare'] ?? 0);
          $commission = round($fare * 0.10, 2);
        ?>
        <tr>
          <td class="text-muted fs-12"><?= $he(substr($r['id'], 0, 8)) ?>…</td>
          <td class="text-muted fs-12"><?= !empty($r['created_at']) ? date('d M Y H:i', strtotime($r['created_at'])) : '—' ?></td>
          <td><?= $he($r['passenger_name'] ?? '—') ?></td>
          <td><?= $he($r['driver_name'] ?? 'Unassigned') ?></td>
          <td style="font-weight:600;color:var(--accent)"><?= $eur($fare) ?></td>
          <td style="color:#dc2626"><?= $eur($commission) ?></td>
          <td style="color:#16a34a"><?= $eur($fare - $commission) ?></td>
          <td>
            <button class="btn-icon" title="Invoice" style="color:#7c3aed"
              onclick="showInvoice(<?= htmlspecialchars(json_encode($r['id'])) ?>)">
              <i class="bi bi-receipt"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination-bar">
    <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= number_format($total) ?></span>
    <div class="pagination-controls">
      <?php if ($page > 1): ?>
      <a href="?page=finance&<?= $riQs ?>&p=<?= $page - 1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="?page=finance&<?= $riQs ?>&p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=finance&<?= $riQs ?>&p=<?= $page + 1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require 'includes/invoice_modal.php'; ?>
<?php
$invoiceDataMap = [];
foreach ($invoices as $r) {
    $fareEur   = (float)($r['fare_eur'] ?? 0);
    $finalFare = (float)($r['final_fare'] ?? $fareEur);
    $total     = (float)($r['total_charged'] ?? $finalFare);
    $invoiceDataMap[$r['id']] = [
        'id'              => $r['id'],
        'fare'            => number_format($total ?: $finalFare, 2),
        'fare_eur'        => number_format($fareEur, 2),
        'final_fare'      => number_format($finalFare, 2),
        'total_charged'   => number_format($total ?: $finalFare, 2),
        'payment_method'  => $r['payment_method'] ?? '',
        'vehicle_type'    => $r['vehicle_type'] ?? null,
        'created_at'      => $r['created_at'] ?? '',
        'pickup_addr'     => $r['pickup_addr'] ?? '',
        'dest_addr'       => $r['dest_addr'] ?? '',
        'driver_id'       => $r['driver_id'] ?? '',
        'driver_license'  => $r['driver_license'] ?? '',
        'driver_name'     => $r['driver_name'] ?? '',
        'driver_phone'    => $r['driver_phone'] ?? '',
        'driver_email'    => $r['driver_email'] ?? '',
        'passenger_name'  => $r['passenger_name'] ?? '',
        'passenger_phone' => $r['passenger_phone'] ?? '',
        'passenger_email' => $r['passenger_email'] ?? '',
        'distance_km'     => $r['distance_km'] ?? null,
        'duration_min'    => $r['duration_min'] ?? null,
    ];
}
?>
<?php $invoiceDataJson = json_encode($invoiceDataMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>

<?php elseif ($tab === 'payouts'): ?>
<!-- ══════════════════ DRIVER WALLET LEDGER ══════════════════ -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="finance">
    <input type="hidden" name="tab" value="payouts">
    <div class="filter-bar" style="padding:14px 18px">
      <div class="glass-input-icon" style="flex:1;min-width:200px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input" placeholder="Search driver name..." value="<?= $he($search) ?>">
      </div>
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Search</button>
      <?php if ($search): ?>
      <a href="?page=finance&tab=payouts" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-wallet2" style="color:var(--accent)"></i>
    <div>
      <div class="card-title">Driver Wallet Ledger</div>
      <div class="card-subtitle"><?= count($driverWallets) ?> driver<?= count($driverWallets) !== 1 ? 's' : '' ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($driverWallets)): ?>
    <div class="empty-state"><i class="bi bi-wallet2"></i><h4>No drivers found</h4></div>
    <?php else: ?>
    <table class="glass-table">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Contact</th>
          <th>Wallet Balance</th>
          <th>Total Topped Up</th>
          <th>Total Commission Deducted</th>
          <th>Last Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($driverWallets as $w): ?>
        <tr>
          <td style="font-weight:500"><?= $he($w['full_name']) ?></td>
          <td class="text-muted fs-12"><?= $he($w['email']) ?><br><?= $he($w['phone']) ?></td>
          <td style="font-weight:600;color:var(--accent)"><?= $eur($w['balance']) ?></td>
          <td style="color:#16a34a"><?= $eur($w['total_topped']) ?></td>
          <td style="color:#dc2626"><?= $eur($w['total_deducted']) ?></td>
          <td class="text-muted fs-12"><?= !empty($w['updated_at']) ? date('d M Y H:i', strtotime($w['updated_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'refunds'): ?>
<!-- ══════════════════ REFUNDS & ADJUSTMENTS ══════════════════ -->
<div class="glass-card mb-4">
  <div class="card-header-bar"><i class="bi bi-arrow-return-left" style="color:#dc2626"></i><div class="card-title">Issue Wallet Credit / Adjustment</div></div>
  <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end">
    <div>
      <label class="form-label">Target Type</label>
      <select id="creditType" class="glass-select" style="width:100%" onchange="toggleCreditTarget()">
        <option value="driver">Driver</option>
        <option value="passenger">Passenger</option>
      </select>
    </div>
    <div id="creditDriverWrap">
      <label class="form-label">Driver</label>
      <select id="creditDriverId" class="glass-select" style="width:100%">
        <option value="">Select driver…</option>
        <?php foreach ($drivers as $d): ?>
        <option value="<?= $he($d['id']) ?>"><?= $he($d['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="creditPassengerWrap" style="display:none">
      <label class="form-label">Passenger</label>
      <select id="creditPassengerId" class="glass-select" style="width:100%">
        <option value="">Select passenger…</option>
        <?php foreach ($passengers as $p): ?>
        <option value="<?= $he($p['id']) ?>"><?= $he($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">Amount (€)</label>
      <input type="number" id="creditAmount" class="glass-input" style="width:100%" step="0.01" min="0.01" placeholder="0.00">
    </div>
    <div style="grid-column:1/-1">
      <label class="form-label">Reason</label>
      <input type="text" id="creditReason" class="glass-input" style="width:100%" placeholder="e.g. Goodwill refund for cancelled ride, fare dispute correction...">
    </div>
    <div>
      <button type="button" class="btn-primary-glass" id="issueCreditBtn" onclick="issueCredit()"><i class="bi bi-check-lg"></i> Issue Credit</button>
    </div>
  </div>
</div>

<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-clock-history" style="color:var(--accent)"></i><div class="card-title">Adjustment History</div></div>
  <div class="table-wrap">
    <?php if (empty($adjustments)): ?>
    <div class="empty-state"><i class="bi bi-arrow-return-left"></i><h4>No adjustments yet</h4><p>Credits issued above will appear here.</p></div>
    <?php else: ?>
    <table class="glass-table">
      <thead><tr><th>Date</th><th>Type</th><th>Name</th><th>Amount</th><th>Reason</th></tr></thead>
      <tbody>
        <?php foreach ($adjustments as $a): ?>
        <tr>
          <td class="text-muted fs-12"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></td>
          <td><span style="background:<?= $a['type'] === 'driver' ? '#EDE9FE' : '#DBEAFE' ?>;color:<?= $a['type'] === 'driver' ? '#7c3aed' : '#1d4ed8' ?>;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600"><?= $a['type'] === 'driver' ? 'Driver' : 'Passenger' ?></span></td>
          <td style="font-weight:500"><?= $he($a['target_name']) ?></td>
          <td style="font-weight:600;color:#16a34a"><?= $eur($a['amount']) ?></td>
          <td class="text-muted fs-12"><?= $he($a['reason']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'transactions'): ?>
<!-- ══════════════════ PAYMENT TRANSACTIONS ══════════════════ -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="finance">
    <input type="hidden" name="tab" value="transactions">
    <div class="filter-bar" style="padding:14px 18px;flex-wrap:wrap;gap:10px">
      <input type="date" name="date_from" class="glass-input" style="width:145px" value="<?= $he($dateFrom) ?>">
      <input type="date" name="date_to" class="glass-input" style="width:145px" value="<?= $he($dateTo) ?>">
      <select name="method" class="glass-select" style="width:140px">
        <option value="all"  <?= $method === 'all'  ? 'selected' : '' ?>>All Methods</option>
        <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="card" <?= $method === 'card' ? 'selected' : '' ?>>Card</option>
      </select>
      <div class="glass-input-icon" style="flex:1;min-width:200px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input" placeholder="Pickup / destination..." value="<?= $he($search) ?>">
      </div>
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Filter</button>
      <?php if ($dateFrom || $dateTo || $method !== 'all' || $search): ?>
      <a href="?page=finance&tab=transactions" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-credit-card" style="color:var(--accent)"></i>
    <div>
      <div class="card-title">Payment Transactions</div>
      <div class="card-subtitle"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($transactions)): ?>
    <div class="empty-state"><i class="bi bi-credit-card"></i><h4>No transactions found</h4></div>
    <?php else: ?>
    <table class="glass-table">
      <thead><tr><th>Date</th><th>Passenger</th><th>Method</th><th>Amount</th><th>Gateway Ref</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($transactions as $t):
          $amount = (float)($t['total_charged'] ?? $t['final_fare'] ?? $t['fare_eur'] ?? 0);
          $isCard = stripos($t['payment_method'] ?? '', 'card') !== false || stripos($t['payment_method'] ?? '', 'wallet') !== false;
        ?>
        <tr>
          <td class="text-muted fs-12"><?= !empty($t['created_at']) ? date('d M Y H:i', strtotime($t['created_at'])) : '—' ?></td>
          <td><?= $he($t['passenger_name'] ?? '—') ?></td>
          <td><span style="background:<?= $isCard ? '#DBEAFE' : '#DCFCE7' ?>;color:<?= $isCard ? '#1d4ed8' : '#16a34a' ?>;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600"><?= $he($t['payment_method'] ?? '—') ?></span></td>
          <td style="font-weight:600;color:var(--accent)"><?= $eur($amount) ?></td>
          <td class="text-muted fs-12"><?= $he($t['stripe_payment_intent_id'] ?? '—') ?></td>
          <td class="text-muted fs-12"><?= $t['stripe_charge_status'] ? $he($t['stripe_charge_status']) : ($isCard ? 'Charged' : 'Collected by Driver') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination-bar">
    <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= number_format($total) ?></span>
    <div class="pagination-controls">
      <?php if ($page > 1): ?>
      <a href="?page=finance&<?= $txQs ?>&p=<?= $page - 1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="?page=finance&<?= $txQs ?>&p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=finance&<?= $txQs ?>&p=<?= $page + 1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'commission'): ?>
<!-- ══════════════════ COMMISSION SETTINGS (read-only) ══════════════════ -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-percent" style="color:var(--accent)"></i>
    <div class="card-title">Commission Rates by Ride Type</div>
    <a href="?page=promotions&tab=pricing" class="btn-primary-glass" style="margin-left:auto"><i class="bi bi-pencil-square"></i> Manage in Promotions &amp; Pricing</a>
  </div>
  <div class="table-wrap">
    <?php if (empty($commissionRows)): ?>
    <div class="empty-state"><i class="bi bi-percent"></i><h4>No pricing configs found</h4></div>
    <?php else: ?>
    <table class="glass-table">
      <thead><tr><th>Ride Type</th><th>Time Period</th><th>Commission</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($commissionRows as $c): ?>
        <tr>
          <td style="font-weight:500"><?= $he(ucwords(str_replace('_', ' ', $c['ride_type']))) ?></td>
          <td class="text-muted fs-12"><?= $he(ucfirst($c['time_period'])) ?></td>
          <td style="font-weight:600;color:var(--accent)"><?= number_format((float)$c['driver_commission_pct'], 1) ?>%</td>
          <td><span style="background:<?= $c['is_active'] ? '#DCFCE7' : '#F1F5F9' ?>;color:<?= $c['is_active'] ? '#16a34a' : '#64748B' ?>;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════ CORPORATE INVOICES ══════════════════ -->
<div class="glass-card mb-4">
  <form method="GET" action="">
    <input type="hidden" name="page" value="finance">
    <input type="hidden" name="tab" value="invoices">
    <div class="filter-bar" style="padding:14px 18px;flex-wrap:wrap;gap:10px">
      <input type="date" name="date_from" class="glass-input" style="width:145px" value="<?= $he($dateFrom) ?>">
      <input type="date" name="date_to" class="glass-input" style="width:145px" value="<?= $he($dateTo) ?>">
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Apply</button>
      <?php if ($dateFrom || $dateTo): ?>
      <a href="?page=finance&tab=invoices" class="btn-glass"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-receipt" style="color:var(--accent)"></i>
    <div>
      <div class="card-title">Corporate Accounts</div>
      <div class="card-subtitle"><?= count($corporate) ?> account<?= count($corporate) !== 1 ? 's' : '' ?> with completed rides</div>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($corporate)): ?>
    <div class="empty-state"><i class="bi bi-receipt"></i><h4>No corporate rides found</h4><p>No completed corporate-account rides in this period.</p></div>
    <?php else: ?>
    <table class="glass-table">
      <thead><tr><th>Company</th><th>CID</th><th>Completed Rides</th><th>Total Fare</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($corporate as $c): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= $he($c['company']) ?></div>
            <?php if ($c['invoice_email'] ?? $c['email']): ?>
            <div class="text-muted fs-12"><?= $he($c['invoice_email'] ?: $c['email']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted fs-12"><?= $he($c['cid']) ?></td>
          <td><?= number_format($c['rides']) ?></td>
          <td style="font-weight:600;color:var(--accent)"><?= $eur($c['total_fare']) ?></td>
          <td>
            <button class="btn-icon" title="Download Invoice" style="color:#7c3aed"
              onclick="downloadCorporateInvoicePdf(<?= htmlspecialchars(json_encode($c['cid'])) ?>, <?= htmlspecialchars(json_encode($c['company'])) ?>)">
              <i class="bi bi-receipt"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php
$dailyLabels = array_map(fn($d) => date('d M', strtotime($d)), array_keys($dailyRevenue));
$dailyValues = array_values($dailyRevenue);

$extraScripts = '<script>'
    . 'function toggleCreditTarget(){'
    . 'const t=document.getElementById("creditType");'
    . 'if(!t)return;'
    . 'const type=t.value;'
    . 'document.getElementById("creditDriverWrap").style.display=type==="driver"?"":"none";'
    . 'document.getElementById("creditPassengerWrap").style.display=type==="passenger"?"":"none";'
    . '}'
    . 'async function issueCredit(){'
    . 'const type=document.getElementById("creditType").value;'
    . 'const id=type==="driver"?document.getElementById("creditDriverId").value:document.getElementById("creditPassengerId").value;'
    . 'const amount=parseFloat(document.getElementById("creditAmount").value);'
    . 'const reason=document.getElementById("creditReason").value.trim();'
    . 'if(!id){Toast.show("Please select a "+type+".","error");return;}'
    . 'if(!amount||amount<=0){Toast.show("Please enter a valid amount.","error");return;}'
    . 'if(!reason){Toast.show("Please enter a reason.","error");return;}'
    . 'if(!confirm("Credit €"+amount.toFixed(2)+" to this "+type+"\'s wallet?\\nReason: "+reason))return;'
    . 'const btn=document.getElementById("issueCreditBtn");btn.disabled=true;'
    . 'const fd=new FormData();'
    . 'fd.append("action","issue_credit");fd.append("credit_type",type);fd.append("target_id",id);fd.append("amount",amount);fd.append("reason",reason);'
    . 'try{const res=await fetch(window.location.href,{method:"POST",body:fd});const data=await res.json();'
    . 'if(data.success){Toast.show(data.message,"success");setTimeout(()=>location.reload(),900);}'
    . 'else{Toast.show(data.message||"Failed to issue credit.","error");btn.disabled=false;}'
    . '}catch(e){Toast.show("Network error.","error");btn.disabled=false;}'
    . '}'
    . '</script>';

if ($tab === 'ride_invoices' && isset($invoiceDataJson)) {
    $driverMode = $driverId !== '' ? 'true' : 'false';
    $extraScripts .= '<script>setInvoiceData(' . $invoiceDataJson . ',' . $driverMode . ');loadPdfLibs(function(){});</script>';
}
if ($tab === 'invoices') {
    $extraScripts .= '<script>loadPdfLibs(function(){});</script>';
}
if ($tab === 'overview') {
    $extraScripts .= '<script>'
        . 'new Chart(document.getElementById("revenueChart"), {'
        . 'type: "bar",'
        . 'data: { labels: ' . json_encode($dailyLabels) . ', datasets: [{'
        . 'label: "Revenue (€)", data: ' . json_encode($dailyValues) . ','
        . 'backgroundColor: "rgba(243,122,32,0.6)", borderRadius: 4 }] },'
        . 'options: { responsive: true, plugins: { legend: { display: false } },'
        . 'scales: { y: { beginAtZero: true, grid: { color: "#F0F2F5" }, ticks: { color: "#94a3b8" } },'
        . 'x: { grid: { display: false }, ticks: { display: false } } } }'
        . '});'
        . '</script>';
}
?>
