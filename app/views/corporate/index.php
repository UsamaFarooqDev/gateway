<?php
$tab = preg_replace('/[^a-z_]/', '', $_GET['tab'] ?? 'accounts');
if (!in_array($tab, ['accounts', 'employees', 'rides', 'invoices', 'revenue_history'])) $tab = 'accounts';
$filterCid = htmlspecialchars($_GET['cid'] ?? '', ENT_QUOTES);
?>

<div class="page-header">
  <div>
    <h1>Corporate Accounts</h1>
    <p>Manage business clients, ride policies, and corporate billing.</p>
  </div>
  <?php if ($tab === 'accounts'): ?>
  <button class="btn-primary-glass" onclick="openCorporateModal()">
    <i class="bi bi-plus-lg"></i> Add Corporate
  </button>
  <?php elseif ($tab === 'invoices'): ?>
  <button class="btn-glass" id="dlCorpInvBtn" onclick="downloadCorpInvoicePdf()" style="display:none">
    <i class="bi bi-download"></i> Download PDF
  </button>
  <?php elseif ($tab === 'revenue_history'): ?>
  <div style="font-size:12px;color:var(--text-muted)">Select a company &amp; year, then click Load History</div>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#7c3aed22;color:#7c3aed"><i class="bi bi-building"></i></div>
    <div class="stat-value"><?= htmlspecialchars($stats['total_clients']) ?></div>
    <div class="stat-label">Total Companies</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#16a34a22;color:#16a34a"><i class="bi bi-building-check"></i></div>
    <div class="stat-value"><?= htmlspecialchars($stats['active_clients']) ?></div>
    <div class="stat-label">Active Companies</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#F37A2022;color:#F37A20"><i class="bi bi-people-fill"></i></div>
    <div class="stat-value"><?= htmlspecialchars($stats['total_employees']) ?></div>
    <div class="stat-label">Total Employees</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:#0ea5e922;color:#0ea5e9"><i class="bi bi-graph-up-arrow"></i></div>
    <div class="stat-value"><?= htmlspecialchars($stats['revenue_accounts']) ?></div>
    <div class="stat-label">Revenue Model Accounts</div>
  </div>
</div>

<!-- Tab Nav -->
<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ([
    'accounts'        => ['bi-building',         'Companies'],
    'employees'       => ['bi-people-fill',       'Employees'],
    'rides'           => ['bi-car-front-fill',    'Ride History'],
    'invoices'        => ['bi-receipt-cutoff',    'Invoices'],
    'revenue_history' => ['bi-bar-chart-line',    'Revenue History'],
  ] as $slug => [$icon, $label]):
    $a = $tab === $slug;
  ?>
  <a href="?page=corporate&tab=<?= $slug ?>"
     style="padding:9px 18px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?= $a?'var(--accent)':'var(--border)' ?>;background:<?= $a?'var(--accent-soft)':'var(--bg-card)' ?>;color:<?= $a?'var(--accent)':'var(--text-muted)' ?>;display:inline-flex;align-items:center;gap:7px;transition:var(--t)">
    <i class="bi <?= $icon ?>"></i> <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'accounts'): ?>
<!-- ═══════════════════════════════ ACCOUNTS TAB ═══════════════════════════════ -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-building" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Companies</div>
    <div style="margin-left:auto;font-size:13px;color:var(--text-muted)">
      <?= count($corporates) ?> company<?= count($corporates) !== 1 ? 'ies' : '' ?>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr>
          <th>CID</th>
          <th>Company</th>
          <th>Contact</th>
          <th>Billing Model</th>
          <th>Payment Cycle</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($corporates)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <i class="bi bi-building"></i>
            <h4>No corporate accounts yet</h4>
            <p>Add your first corporate client to get started.</p>
          </div>
        </td></tr>
        <?php else: foreach ($corporates as $c):
          $isActive = ($c['status'] ?? 'active') === 'active';
          $isRev    = ($c['billing_model'] ?? 'regular') === 'revenue';
        ?>
        <tr>
          <td><span style="font-family:monospace;font-size:12px;color:var(--accent)"><?= htmlspecialchars($c['cid']) ?></span></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($c['company_name'] ?? '—') ?></div>
            <?php if (!empty($c['tax_number'])): ?>
            <div style="font-size:11px;color:var(--text-subtle)">VAT: <?= htmlspecialchars($c['tax_number']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div><?= htmlspecialchars($c['name'] ?? '—') ?></div>
            <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($c['email'] ?? '—') ?></div>
            <?php if (!empty($c['phone'])): ?>
            <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($c['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isRev): ?>
            <span style="background:rgba(2,132,199,0.12);color:#0284c7;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;border:1px solid rgba(2,132,199,0.2)">
              <i class="bi bi-graph-up"></i> Revenue-Based
            </span>
            <?php else: ?>
            <span style="background:var(--hover-bg);color:var(--text-muted);padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;border:1px solid var(--border)">
              Regular
            </span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-muted);font-size:13px"><?= htmlspecialchars(ucfirst($c['payment_cycle'] ?? '—')) ?></td>
          <td>
            <button onclick="toggleCorporate('<?= htmlspecialchars($c['cid']) ?>','<?= $isActive ? 'inactive' : 'active' ?>')"
                    style="border:1px solid <?= $isActive ? 'rgba(22,163,74,0.3)' : 'rgba(220,38,38,0.3)' ?>;cursor:pointer;background:<?= $isActive ? 'rgba(22,163,74,0.1)' : 'rgba(220,38,38,0.1)' ?>;
                           color:<?= $isActive ? '#16a34a' : '#dc2626' ?>;padding:3px 12px;border-radius:12px;font-size:11.5px;font-weight:600;transition:var(--t)">
              <?= $isActive ? 'Active' : 'Inactive' ?>
            </button>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn-glass" style="padding:5px 10px;font-size:12px" onclick="editCorporate('<?= htmlspecialchars($c['cid']) ?>')">
                <i class="bi bi-pencil"></i> Edit
              </button>
              <button class="btn-glass" style="padding:5px 10px;font-size:12px;color:#dc2626;border-color:rgba(220,38,38,0.3)" onclick="deleteCorporateConfirm('<?= htmlspecialchars($c['cid']) ?>','<?= htmlspecialchars(addslashes($c['company_name'] ?? $c['cid'])) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'employees'): ?>
<!-- ═══════════════════════════════ EMPLOYEES TAB ═══════════════════════════════ -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-people-fill" style="color:var(--accent)"></i>
    <div class="card-title">Employees</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="page" value="corporate">
        <input type="hidden" name="tab"  value="employees">
        <select name="cid" class="glass-select" style="width:200px" onchange="this.form.submit()">
          <option value="">All Companies</option>
          <?php foreach ($allCorps as $c): ?>
          <option value="<?= htmlspecialchars($c['cid']) ?>" <?= $filterCid === $c['cid'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['company_name'] ?? $c['cid']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Company</th>
          <th>Department</th>
          <th>Phone</th>
          <th>Passenger ID</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($employees)): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <i class="bi bi-people"></i>
            <h4><?= $filterCid ? 'No employees found for this company' : 'Select a company to view employees' ?></h4>
            <p>Employees are added from the corporate portal.</p>
          </div>
        </td></tr>
        <?php else: foreach ($employees as $e): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($e['name'] ?? '—') ?></div>
            <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($e['email'] ?? '—') ?></div>
          </td>
          <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($e['company'] ?? $e['cid'] ?? '—') ?></td>
          <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($e['department'] ?? '—') ?></td>
          <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($e['phone'] ?? '—') ?></td>
          <td><span style="font-family:monospace;font-size:11px;color:var(--text-subtle)"><?= htmlspecialchars(substr($e['id'], 0, 12)) ?>…</span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'rides'): ?>
<!-- ═══════════════════════════════ RIDES TAB ═══════════════════════════════ -->
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
  <input type="hidden" name="page" value="corporate">
  <input type="hidden" name="tab"  value="rides">
  <div>
    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Company *</div>
    <select name="cid" class="glass-select" style="width:220px" required>
      <option value="">— Select Company —</option>
      <?php foreach ($allCorps as $c): ?>
      <option value="<?= htmlspecialchars($c['cid']) ?>" <?= $filterCid === $c['cid'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['company_name'] ?? $c['cid']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">From</div>
    <input type="date" name="date_from" class="glass-input" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>" style="width:150px">
  </div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">To</div>
    <input type="date" name="date_to" class="glass-input" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" style="width:150px">
  </div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Status</div>
    <select name="status" class="glass-select" style="width:140px">
      <option value="">All</option>
      <?php foreach (['completed','cancelled','searching','assigned','enroute'] as $s): ?>
      <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Search</button>
</form>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-car-front-fill" style="color:var(--accent)"></i>
    <div class="card-title">Corporate Ride History</div>
    <div style="margin-left:auto;font-size:13px;color:var(--text-muted)"><?= count($rides) ?> ride<?= count($rides) !== 1 ? 's' : '' ?></div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Employee</th>
          <th>Route</th>
          <th>Payment</th>
          <th>Ride Fare</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rides) && !$filterCid): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-car-front"></i><h4>Select a company to view rides</h4></div></td></tr>
        <?php elseif (empty($rides)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-car-front"></i><h4>No rides found</h4><p>Try adjusting your date range or filters.</p></div></td></tr>
        <?php else:
          $statusColors = [
            'completed' => ['#16a34a','rgba(22,163,74,0.12)'],
            'cancelled' => ['#dc2626','rgba(220,38,38,0.12)'],
            'enroute'   => ['#0284c7','rgba(2,132,199,0.12)'],
            'assigned'  => ['#7c3aed','rgba(124,58,237,0.12)'],
            'searching' => ['#d97706','rgba(217,119,6,0.12)'],
            'scheduled' => ['#ea580c','rgba(234,88,12,0.12)'],
          ];
          foreach ($rides as $i => $r):
            [$sc, $sbg] = $statusColors[$r['status'] ?? ''] ?? ['var(--text-subtle)','var(--hover-bg)'];
            $fare = (float)($r['fare'] ?? $r['total_charged'] ?? $r['final_fare'] ?? $r['fare_eur'] ?? 0);
        ?>
        <tr>
          <td style="color:var(--text-subtle);font-size:12px"><?= $i + 1 ?></td>
          <td style="font-size:12px;white-space:nowrap">
            <?php try { $dt = new DateTime($r['created_at'] ?? ''); echo htmlspecialchars($dt->format('d M Y')); } catch (Throwable $e) { echo '—'; } ?>
          </td>
          <td>
            <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($r['employee_name'] ?? '—') ?></div>
            <div style="font-size:11px;color:var(--text-subtle)"><?= htmlspecialchars($r['employee_email'] ?? '') ?></div>
          </td>
          <td style="font-size:11.5px">
            <div><i class="bi bi-geo-alt-fill" style="color:#16a34a;font-size:10px"></i> <?= htmlspecialchars($r['pickup_addr'] ?? '—') ?></div>
            <div style="color:var(--text-muted);margin-top:2px"><i class="bi bi-geo-alt" style="font-size:10px"></i> <?= htmlspecialchars($r['dest_addr'] ?? '—') ?></div>
          </td>
          <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= htmlspecialchars(ucfirst($r['payment_method'] ?? 'Cash')) ?></td>
          <td style="font-weight:600;color:var(--accent);white-space:nowrap">€<?= number_format($fare, 2) ?></td>
          <td>
            <span style="background:<?= $sbg ?>;color:<?= $sc ?>;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;text-transform:capitalize;white-space:nowrap">
              <?= htmlspecialchars($r['status'] ?? '—') ?>
            </span>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        <?php if (!empty($rides)):
          $rideTotal = array_sum(array_map(fn($r) => (float)($r['fare'] ?? $r['total_charged'] ?? $r['final_fare'] ?? $r['fare_eur'] ?? 0), $rides));
        ?>
        <tr style="font-weight:700;border-top:2px solid var(--border)">
          <td colspan="5" style="padding:10px 8px;font-size:12px;color:var(--text-muted)">
            Total — <?= count($rides) ?> ride<?= count($rides) !== 1 ? 's' : '' ?>
          </td>
          <td style="font-size:15px;font-weight:700;color:var(--accent);white-space:nowrap">€<?= number_format($rideTotal, 2) ?></td>
          <td></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<!-- ═══════════════════════════════ INVOICES TAB ═══════════════════════════════ -->
<div class="glass-card" style="margin-bottom:20px">
  <div class="card-header-bar">
    <i class="bi bi-receipt-cutoff" style="color:var(--accent)"></i>
    <div class="card-title">Generate Corporate Invoice</div>
  </div>
  <div style="padding:20px 24px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:5px">Company *</div>
      <select id="invCid" class="glass-select" style="width:240px" onchange="updateBillingModelBadge()">
        <option value="">— Select Company —</option>
        <?php foreach ($allCorps as $c): ?>
        <option value="<?= htmlspecialchars($c['cid']) ?>"
                data-model="<?= htmlspecialchars($c['billing_model'] ?? 'regular') ?>"
                data-name="<?= htmlspecialchars($c['company_name'] ?? $c['cid']) ?>">
          <?= htmlspecialchars($c['company_name'] ?? $c['cid']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:5px">From *</div>
      <input type="date" id="invFrom" class="glass-input" style="width:155px">
    </div>
    <div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:5px">To *</div>
      <input type="date" id="invTo" class="glass-input" style="width:155px">
    </div>
    <button type="button" class="btn-primary-glass" onclick="computeCorpInvoice(this)">
      <i class="bi bi-calculator"></i> Compute Invoice
    </button>
    <div id="billingModelBadge" style="display:none;padding:6px 14px;border-radius:12px;font-size:12px;font-weight:600"></div>
  </div>
</div>

<!-- Admin Profit Summary (not printed, visible only to admin) -->
<div id="adminProfitSummary" style="display:none;margin-bottom:16px" class="no-print">
  <div class="glass-card" style="border:1px solid rgba(245,158,11,0.3);background:rgba(245,158,11,0.06)">
    <div class="card-header-bar">
      <i class="bi bi-shield-lock-fill" style="color:#f59e0b"></i>
      <div class="card-title" style="color:#f59e0b">PowerCabs Internal — Not Included in Invoice</div>
    </div>
    <div style="padding:16px 24px;display:flex;gap:20px;flex-wrap:wrap;align-items:center">
      <div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">Total Billed to Corporate</div>
        <div id="apsTotalFare" style="font-size:20px;font-weight:700;color:#fff">—</div>
      </div>
      <div style="font-size:20px;color:var(--text-muted)">−</div>
      <div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px">Credits Earned (Loyalty Liability)</div>
        <div id="apsCredits" style="font-size:20px;font-weight:700;color:#16a34a">—</div>
      </div>
      <div style="font-size:20px;color:var(--text-muted)">=</div>
      <div style="padding:12px 20px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.4);border-radius:10px">
        <div style="font-size:11px;color:#f59e0b;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px">PC Effective Revenue</div>
        <div id="apsPcProfit" style="font-size:28px;font-weight:800;color:#f59e0b">—</div>
      </div>
      <div style="font-size:13px;color:var(--text-muted);max-width:260px;line-height:1.5">
        Corporate charged full fare <strong id="apsTotalCharge" style="color:var(--accent)">—</strong>. Credits are issued as monthly loyalty balance.
      </div>
    </div>
  </div>
</div>

<!-- Invoice Preview -->
<div id="corpInvoicePreview" style="display:none">
  <div class="glass-card" style="padding:0;overflow:hidden">
    <div class="card-header-bar">
      <i class="bi bi-file-earmark-text" style="color:var(--accent)"></i>
      <div class="card-title">Invoice Preview</div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn-glass" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <button class="btn-primary-glass" onclick="downloadCorpInvoicePdf()"><i class="bi bi-download"></i> Download PDF</button>
      </div>
    </div>
    <div id="corpInvoiceContent" style="background:#fff;padding:8px"></div>
  </div>
</div>

<?php elseif ($tab === 'revenue_history'): ?>
<!-- ═══════════════════════════════ REVENUE HISTORY TAB ═══════════════════════════════ -->
<div class="glass-card" style="margin-bottom:20px">
  <div class="card-header-bar">
    <i class="bi bi-bar-chart-line" style="color:var(--accent)"></i>
    <div class="card-title">Monthly Revenue History</div>
  </div>
  <div style="padding:20px 24px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:5px">Company *</div>
      <select id="rhCid" class="glass-select" style="width:260px">
        <option value="">— Select Company —</option>
        <?php foreach ($allCorps as $c): ?>
        <option value="<?= htmlspecialchars($c['cid']) ?>" data-model="<?= htmlspecialchars($c['billing_model'] ?? 'regular') ?>">
          <?= htmlspecialchars($c['company_name'] ?? $c['cid']) ?>
          <?php if (($c['billing_model'] ?? 'regular') === 'revenue'): ?> ★<?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:5px">Year</div>
      <select id="rhYear" class="glass-select" style="width:110px">
        <?php for ($y = (int)date('Y'); $y >= 2023; $y--): ?>
        <option value="<?= $y ?>" <?= $y === (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button class="btn-primary-glass" onclick="loadRevenueHistory()">
      <i class="bi bi-bar-chart-line"></i> Load History
    </button>
  </div>
</div>

<div id="revHistoryResults" style="display:none"></div>
<?php endif; ?>


<!-- ═══════════════════════════════ ADD/EDIT MODAL ═══════════════════════════════ -->
<div class="modal-overlay" id="corporateModal">
  <div class="modal-box" style="max-width:780px;width:100%;max-height:90vh;overflow-y:auto;margin:auto">
    <div class="modal-header">
      <h5 id="corpModalTitle" class="modal-title">Add Corporate Account</h5>
      <button class="modal-close" onclick="Modal.close('corporateModal')">×</button>
    </div>
    <div style="padding:20px 24px">
      <input type="hidden" id="corpEditCid">

      <!-- Company Details -->
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)">Company Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="form-label">Company Name *</label>
          <input type="text" id="corp_company_name" class="glass-input" placeholder="Acme Ltd">
        </div>
        <div>
          <label class="form-label">CID (leave blank to auto-generate)</label>
          <input type="text" id="corp_cid" class="glass-input" placeholder="CID-XXXXXXXX" style="font-family:monospace">
        </div>
        <div>
          <label class="form-label">Tax / VAT Number</label>
          <input type="text" id="corp_tax_number" class="glass-input" placeholder="IE1234567T">
        </div>
        <div>
          <label class="form-label">Status</label>
          <select id="corp_status" class="glass-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div>
          <label class="form-label">Billing IBAN</label>
          <input type="text" id="corp_billing_iban" class="glass-input" placeholder="IE12 AIBK 1234 5678 9012 34">
        </div>
        <div>
          <label class="form-label">Payment Cycle</label>
          <select id="corp_payment_cycle" class="glass-select">
            <option value="">— Select —</option>
            <option value="weekly">Weekly</option>
            <option value="biweekly">Bi-weekly</option>
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
          </select>
        </div>
      </div>

      <!-- Contact Person -->
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)">Primary Contact</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="form-label">Contact Name *</label>
          <input type="text" id="corp_name" class="glass-input" placeholder="John Smith">
        </div>
        <div>
          <label class="form-label">Contact Email *</label>
          <input type="email" id="corp_email" class="glass-input" placeholder="john@company.com">
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input type="tel" id="corp_phone" class="glass-input" placeholder="+353 1 234 5678">
        </div>
        <div>
          <label class="form-label">Invoice Email</label>
          <input type="email" id="corp_invoice_email" class="glass-input" placeholder="accounts@company.com">
        </div>
        <div>
          <label class="form-label">Appointed Person</label>
          <input type="text" id="corp_appointed_person" class="glass-input" placeholder="Jane Doe">
        </div>
        <div>
          <label class="form-label">Designation</label>
          <input type="text" id="corp_designation" class="glass-input" placeholder="Finance Manager">
        </div>
      </div>

      <!-- Addresses -->
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)">Addresses</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="form-label">Office Address</label>
          <input type="text" id="corp_office_address" class="glass-input" placeholder="123 Business Park, Dublin">
        </div>
        <div>
          <label class="form-label">Billing Address</label>
          <input type="text" id="corp_address" class="glass-input" placeholder="Same as office or different">
        </div>
      </div>

      <!-- Billing Model -->
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)">Billing Model</div>
      <div style="display:flex;gap:10px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:10px;border:1px solid var(--border);background:var(--hover-bg);flex:1;transition:all 0.2s" id="modelRegularCard">
          <input type="radio" name="corp_billing_model" value="regular" id="modelRegular" onchange="updateBillingModelUI()" style="accent-color:var(--accent)">
          <div>
            <div style="font-weight:600;font-size:13px">Regular</div>
            <div style="font-size:11px;color:var(--text-muted)">Charge meter fare only</div>
          </div>
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:10px;border:1px solid var(--border);background:var(--hover-bg);flex:1;transition:all 0.2s" id="modelRevenueCard">
          <input type="radio" name="corp_billing_model" value="revenue" id="modelRevenue" onchange="updateBillingModelUI()" style="accent-color:var(--accent)">
          <div>
            <div style="font-weight:600;font-size:13px">Revenue-Based</div>
            <div style="font-size:11px;color:var(--text-muted)">Meter fare + fixed fee with tier discounts</div>
          </div>
        </label>
      </div>

      <!-- Revenue Config (shown when revenue model is selected) -->
      <div id="revenueSection" style="display:none;background:rgba(2,132,199,0.06);border:1px solid rgba(2,132,199,0.2);border-radius:12px;padding:18px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
          <i class="bi bi-graph-up-arrow" style="color:#0284c7"></i>
          <div style="font-weight:600;font-size:13px;color:#0284c7">Revenue Model Configuration</div>
        </div>
        <div style="margin-bottom:16px">
          <label class="form-label">Fixed Fee per Ride (€)</label>
          <input type="number" id="corp_revenue_fixed_fee" class="glass-input" placeholder="3.00" min="0" step="0.01" style="width:180px">
          <div style="font-size:11px;color:var(--text-subtle);margin-top:4px">Added on top of the meter fare for every ride</div>
        </div>
        <div>
          <div style="font-size:12px;font-weight:600;margin-bottom:10px">Ride Range Discounts</div>
          <div style="font-size:11px;color:var(--text-subtle);margin-bottom:10px">
            Define discounts per ride range within a billing period. Discount is deducted from the fixed fee per ride.
          </div>
          <div style="display:grid;grid-template-columns:90px 110px 120px 40px;gap:8px;align-items:center;font-size:11px;color:var(--text-muted);margin-bottom:6px">
            <span>From Ride #</span><span>To Ride # (blank=∞)</span><span>Discount (€)</span><span></span>
          </div>
          <div id="tiersContainer" style="display:flex;flex-direction:column;gap:8px"></div>
          <button type="button" class="btn-glass" onclick="addTier()" style="margin-top:10px;font-size:12px;padding:6px 14px">
            <i class="bi bi-plus"></i> Add Tier
          </button>
        </div>
      </div>

      <!-- Notes -->
      <div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)">Notes</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
        <div>
          <label class="form-label">Notes (Company Visible)</label>
          <textarea id="corp_special_notes_company" class="glass-input" rows="3" placeholder="Notes visible to the corporate client…" style="resize:vertical"></textarea>
        </div>
        <div>
          <label class="form-label">Notes (Internal / PowerCabs Only)</label>
          <textarea id="corp_special_notes_powercabs" class="glass-input" rows="3" placeholder="Internal notes not shared with the client…" style="resize:vertical"></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn-glass" onclick="Modal.close('corporateModal')">Cancel</button>
        <button class="btn-primary-glass" onclick="saveCorporate(this)"><i class="bi bi-check-lg"></i> Save Account</button>
      </div>
    </div>
  </div>
</div>

<!-- Revenue Slip Modal -->
<div class="modal-overlay" id="revSlipModal">
  <div class="modal-box" style="max-width:960px;width:100%;max-height:92vh;overflow-y:auto;margin:auto">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-receipt"></i> Monthly Invoice Slip</h5>
      <button class="modal-close" onclick="Modal.close('revSlipModal')">×</button>
    </div>
    <div style="padding:10px 20px 10px;display:flex;gap:8px;border-bottom:1px solid var(--border)">
      <button class="btn-glass" onclick="printRevSlip()"><i class="bi bi-printer"></i> Print</button>
      <button class="btn-primary-glass" onclick="downloadRevSlipPdf()"><i class="bi bi-download"></i> Download PDF</button>
    </div>
    <div id="revSlipContent" style="background:#fff;padding:8px;min-height:200px"></div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="corpDeleteModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h5 class="modal-title" style="color:#f87171"><i class="bi bi-exclamation-triangle"></i> Delete Account</h5>
      <button class="modal-close" onclick="Modal.close('corpDeleteModal')">×</button>
    </div>
    <div style="padding:20px 24px">
      <p style="font-size:13.5px;margin-bottom:6px">Are you sure you want to delete <strong id="corpDeleteName"></strong>?</p>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:20px">This will also delete all linked employees. This action cannot be undone.</p>
      <input type="hidden" id="corpDeleteCid">
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn-glass" onclick="Modal.close('corpDeleteModal')">Cancel</button>
        <button class="btn-glass" style="background:rgba(220,38,38,0.1);color:#dc2626;border-color:rgba(220,38,38,0.3)" onclick="deleteCorporate()">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ─── Corporate CRUD ────────────────────────────────────────────────────────────

function openCorporateModal(prefill) {
  const isEdit = !!prefill;

  // Set title and CID handling
  document.getElementById('corpModalTitle').textContent = isEdit ? 'Edit Corporate Account' : 'Add Corporate Account';
  document.getElementById('corpEditCid').value          = isEdit ? (prefill.cid || '') : '';
  document.getElementById('corp_cid').readOnly          = isEdit;

  // Reset all plain text/select fields
  ['company_name','tax_number','status','billing_iban','payment_cycle',
   'name','email','phone','invoice_email','appointed_person','designation',
   'office_address','address','revenue_fixed_fee',
   'special_notes_company','special_notes_powercabs'].forEach(f => {
    const el = document.getElementById('corp_' + f);
    if (!el) return;
    if (el.tagName === 'SELECT') el.value = f === 'status' ? 'active' : '';
    else el.value = '';
  });
  if (!isEdit) document.getElementById('corp_cid').value = '';
  document.querySelector('input[name="corp_billing_model"][value="regular"]').checked = true;
  document.getElementById('tiersContainer').innerHTML = '';

  // Fill form from prefill (edit mode) — strip literal "NULL" strings Supabase may return
  if (isEdit) {
    const cleanVal = v => (v === null || v === undefined || String(v).toUpperCase().trim() === 'NULL') ? '' : v;
    Object.keys(prefill).forEach(k => {
      const el = document.getElementById('corp_' + k);
      if (el) el.value = cleanVal(prefill[k]);
    });
    const bm = prefill.billing_model || 'regular';
    const rb = document.querySelector(`input[name="corp_billing_model"][value="${bm}"]`);
    if (rb) rb.checked = true;

    const rawTiers = prefill.revenue_tiers;
    const tiers    = Array.isArray(rawTiers) ? rawTiers
                   : (typeof rawTiers === 'string' ? (JSON.parse(rawTiers || '[]') || []) : []);
    tiers.forEach(t => addTier(t.from ?? '', t.to ?? '', t.discount ?? ''));
  }

  updateBillingModelUI();
  Modal.open('corporateModal');
}

function editCorporate(cid) {
  const fd = new FormData();
  fd.append('action', 'get_corporate');
  fd.append('cid', cid);
  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.success) { Toast.show(d.message || 'Failed to load.', 'error'); return; }
      openCorporateModal(d.data);
    });
}

function saveCorporate(saveBtn) {
  const editCid = document.getElementById('corpEditCid').value;
  const isEdit  = !!editCid;

  const company_name = document.getElementById('corp_company_name').value.trim();
  const name         = document.getElementById('corp_name').value.trim();
  const email        = document.getElementById('corp_email').value.trim();
  if (!company_name || !name || !email) {
    Toast.show('Company name, contact name, and email are required.', 'error'); return;
  }

  const billingModel = document.querySelector('input[name="corp_billing_model"]:checked')?.value || 'regular';
  const fixedFee     = document.getElementById('corp_revenue_fixed_fee').value;
  const tiers        = getTiers();

  const fd = new FormData();
  fd.append('action', isEdit ? 'update_corporate' : 'create_corporate');
  // For edits, CID comes from editCid; for creates, from the corp_cid field (may be blank → auto-generated)
  fd.append('cid', isEdit ? editCid : document.getElementById('corp_cid').value.trim());

  [['company_name',company_name],['name',name],['email',email],
   ['tax_number',      document.getElementById('corp_tax_number').value.trim()],
   ['status',          document.getElementById('corp_status').value],
   ['billing_iban',    document.getElementById('corp_billing_iban').value.trim()],
   ['payment_cycle',   document.getElementById('corp_payment_cycle').value],
   ['phone',           document.getElementById('corp_phone').value.trim()],
   ['invoice_email',   document.getElementById('corp_invoice_email').value.trim()],
   ['appointed_person',document.getElementById('corp_appointed_person').value.trim()],
   ['designation',     document.getElementById('corp_designation').value.trim()],
   ['office_address',  document.getElementById('corp_office_address').value.trim()],
   ['address',         document.getElementById('corp_address').value.trim()],
   ['billing_model',   billingModel],
   ['revenue_fixed_fee', fixedFee],
   ['revenue_tiers',   JSON.stringify(tiers)],
   ['special_notes_company',    document.getElementById('corp_special_notes_company').value.trim()],
   ['special_notes_powercabs',  document.getElementById('corp_special_notes_powercabs').value.trim()],
  ].forEach(([k, v]) => fd.append(k, v));

  const origLabel = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.text().then(text => {
      try {
        return JSON.parse(text);
      } catch (_) {
        throw new Error(text.substring(0, 300));
      }
    }))
    .then(d => {
      saveBtn.disabled = false;
      saveBtn.innerHTML = origLabel;
      Toast.show(d.message || (d.success ? 'Saved.' : 'Save failed.'), d.success ? 'success' : 'error');
      if (d.success) { Modal.close('corporateModal'); setTimeout(() => location.reload(), 800); }
    })
    .catch(err => {
      saveBtn.disabled = false;
      saveBtn.innerHTML = origLabel;
      Toast.show('Save error: ' + (err.message || 'Unexpected server response'), 'error');
    });
}

function toggleCorporate(cid, newStatus) {
  const fd = new FormData();
  fd.append('action', 'toggle_corporate');
  fd.append('cid',    cid);
  fd.append('status', newStatus);
  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      Toast.show(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 600);
    });
}

function deleteCorporateConfirm(cid, name) {
  document.getElementById('corpDeleteCid').value  = cid;
  document.getElementById('corpDeleteName').textContent = name;
  Modal.open('corpDeleteModal');
}

function deleteCorporate() {
  const cid = document.getElementById('corpDeleteCid').value;
  const fd  = new FormData();
  fd.append('action', 'delete_corporate');
  fd.append('cid', cid);
  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      Toast.show(d.message, d.success ? 'success' : 'error');
      if (d.success) { Modal.close('corpDeleteModal'); setTimeout(() => location.reload(), 800); }
    });
}

// ─── Billing Model UI ──────────────────────────────────────────────────────────

function updateBillingModelUI() {
  const model = document.querySelector('input[name="corp_billing_model"]:checked')?.value;
  document.getElementById('revenueSection').style.display = model === 'revenue' ? 'block' : 'none';
  document.getElementById('modelRegularCard').style.borderColor  = model === 'regular' ? 'var(--accent)'  : 'var(--border)';
  document.getElementById('modelRevenueCard').style.borderColor  = model === 'revenue' ? '#0284c7'         : 'var(--border)';
  document.getElementById('modelRevenueCard').style.background   = model === 'revenue' ? 'rgba(2,132,199,0.07)' : 'var(--hover-bg)';
  document.getElementById('modelRegularCard').style.background   = model === 'regular' ? 'var(--accent-soft)' : 'var(--hover-bg)';
}

function addTier(from = '', to = '', discount = '') {
  const container = document.getElementById('tiersContainer');
  const row = document.createElement('div');
  row.className = 'tier-row';
  row.style.cssText = 'display:grid;grid-template-columns:90px 110px 120px 40px;gap:8px;align-items:center';
  row.innerHTML = `
    <input type="number" class="glass-input tier-from" placeholder="1" min="1" step="1" value="${from}" style="padding:6px 8px;font-size:12px">
    <input type="number" class="glass-input tier-to"   placeholder="∞"  min="1" step="1" value="${to}"   style="padding:6px 8px;font-size:12px">
    <input type="number" class="glass-input tier-disc" placeholder="0.00" min="0" step="0.01" value="${discount}" style="padding:6px 8px;font-size:12px">
    <button type="button" style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.3);color:#dc2626;border-radius:6px;cursor:pointer;width:32px;height:32px;font-size:16px;line-height:1" onclick="this.parentElement.remove()">×</button>`;
  container.appendChild(row);
}

function getTiers() {
  return Array.from(document.querySelectorAll('#tiersContainer .tier-row')).map(row => ({
    from:     parseInt(row.querySelector('.tier-from').value) || 1,
    to:       row.querySelector('.tier-to').value !== '' ? parseInt(row.querySelector('.tier-to').value) : null,
    discount: parseFloat(row.querySelector('.tier-disc').value) || 0,
  }));
}

// ─── Invoice ───────────────────────────────────────────────────────────────────

let _corpInvoiceData = null;

function updateBillingModelBadge() {
  const sel   = document.getElementById('invCid');
  const opt   = sel.options[sel.selectedIndex];
  const badge = document.getElementById('billingModelBadge');
  if (!opt || !opt.value) { badge.style.display = 'none'; return; }
  const model = opt.dataset.model || 'regular';
  badge.style.display    = 'inline-flex';
  badge.style.background = model === 'revenue' ? 'rgba(2,132,199,0.12)' : 'var(--hover-bg)';
  badge.style.color      = model === 'revenue' ? '#0284c7' : 'var(--text-muted)';
  badge.innerHTML        = model === 'revenue'
    ? '<i class="bi bi-graph-up me-1"></i> Revenue-Based Billing'
    : 'Regular Billing';
}

function computeCorpInvoice(invokeBtn) {
  const cid  = document.getElementById('invCid').value;
  const from = document.getElementById('invFrom').value;
  const to   = document.getElementById('invTo').value;
  if (!cid)         { Toast.show('Please select a company.', 'error'); return; }
  if (!from || !to) { Toast.show('Please select a date range.', 'error'); return; }

  const btn  = invokeBtn;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Computing…';
  btn.disabled  = true;

  const fd = new FormData();
  fd.append('action',    'compute_invoice');
  fd.append('cid',       cid);
  fd.append('date_from', from);
  fd.append('date_to',   to);

  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.text())
    .then(raw => {
      btn.innerHTML = orig;
      btn.disabled  = false;

      // Strip any PHP warnings/notices that may prefix the JSON
      const jsonStart = raw.indexOf('{');
      const cleaned   = jsonStart >= 0 ? raw.slice(jsonStart) : raw;

      let d;
      try { d = JSON.parse(cleaned); } catch(e) {
        console.error('Invoice parse error:', raw.slice(0, 500));
        Toast.show('Server error — check console for details.', 'error');
        return;
      }
      if (!d.success) {
        Toast.show(d.message || 'Failed to compute invoice.', 'error');
        return;
      }

      _corpInvoiceData = d;
      document.getElementById('corpInvoiceContent').innerHTML = buildCorpInvoiceHtml(d);
      const preview = document.getElementById('corpInvoicePreview');
      preview.style.display = 'block';
      document.getElementById('dlCorpInvBtn').style.display = 'inline-flex';

      // Admin profit summary (internal only, not printed)
      const eur = n => '€' + parseFloat(n || 0).toFixed(2);
      const aps = document.getElementById('adminProfitSummary');
      if (d.billing_model === 'revenue') {
        document.getElementById('apsTotalFare').textContent   = eur(d.total_ride_fare);
        document.getElementById('apsCredits').textContent     = eur(d.total_credits);
        document.getElementById('apsPcProfit').textContent    = eur(d.total_pc_profit);
        document.getElementById('apsTotalCharge').textContent = eur(d.total_charge);
        aps.style.display = 'block';
        aps.scrollIntoView({behavior:'smooth', block:'start'});
      } else {
        aps.style.display = 'none';
        preview.scrollIntoView({behavior:'smooth', block:'start'});
      }
    })
    .catch(err => {
      btn.innerHTML = orig;
      btn.disabled  = false;
      console.error('Invoice fetch error:', err);
      Toast.show('Network error computing invoice.', 'error');
    });
}

function buildCorpInvoiceHtml(data) {
  const corp   = data.corporate;
  const isRev  = data.billing_model === 'revenue';
  const period = `${data.period_from} to ${data.period_to}`;
  const invNo  = 'CORP-' + corp.cid + '-' + (data.period_from || '').replace(/-/g, '');

  const fmtDate = (s) => s ? new Date(s).toLocaleDateString('en-IE', {day:'2-digit',month:'short',year:'numeric'}) : '—';
  const esc     = (s) => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const eur     = (n) => '€' + parseFloat(n || 0).toFixed(2);

  const cell  = (extra='') => `style="padding:6px 8px;border:1px solid #e5e7eb;vertical-align:top;${extra}"`;
  const thCell = (extra='') => `style="padding:8px;border:1px solid #e5e7eb;font-size:10px;font-weight:700;letter-spacing:0.3px;${extra}"`;
  // Invoice always shows full ride fare; credits are a separate loyalty section below
  const colCount = 5;

  const rows = data.rides.length === 0
    ? `<tr><td colspan="${colCount}" style="text-align:center;padding:28px;color:#888;font-size:12px">No completed rides in this period.</td></tr>`
    : data.rides.map(r => `
        <tr style="font-size:11.5px">
          <td ${cell('text-align:center;color:#999;width:32px')}>${r.ride_number}</td>
          <td ${cell('white-space:nowrap;width:90px')}>${fmtDate(r.date)}</td>
          <td ${cell('')}>
            <div style="font-weight:600;font-size:11.5px">${esc(r.employee_name)}</div>
            <div style="font-size:10px;color:#94a3b8">${esc(r.employee_email)}</div>
          </td>
          <td ${cell('font-size:10.5px')}>
            <div style="color:#374151"><span style="color:#16a34a;font-size:10px">▲</span> ${esc(r.pickup_addr)}</div>
            <div style="color:#6b7280;margin-top:3px"><span style="font-size:10px">▼</span> ${esc(r.dest_addr)}</div>
          </td>
          <td ${cell('text-align:right;width:90px;font-weight:700')}>${eur(r.ride_fare)}</td>
        </tr>`).join('');

  return `
  <style>@media print { .inv-no-print { display:none!important } }</style>
  <div id="corpInvoicePrint" style="font-family:'Segoe UI',Arial,sans-serif;color:#1a1a2e;padding:28px;background:#fff">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:16px;margin-bottom:18px">
      <div>
        <img src="assets/img/logo.png" alt="PowerCabs" style="height:44px;object-fit:contain">
        <div style="margin-top:7px;font-size:11px;line-height:1.8;color:#374151">
          <div style="font-weight:700;font-size:12px;color:#1a1a2e">POWERCABS IRELAND LIMITED</div>
          <div>Inchicore, Dublin, Ireland</div>
          <div>Tel: 01 2030 727 &nbsp;|&nbsp; Info@powercabs.ie &nbsp;|&nbsp; www.powercabs.ie</div>
          <div>VAT: TAX 04301619NH</div>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:22px;font-weight:800;color:#1a1a2e;letter-spacing:-0.5px">CORPORATE INVOICE</div>
        <table style="font-size:12px;border-collapse:collapse;margin-top:10px;margin-left:auto">
          <tr><td style="padding:3px 16px 3px 0;color:#64748b">Invoice No</td><td style="font-weight:700">${esc(invNo)}</td></tr>
          <tr><td style="padding:3px 16px 3px 0;color:#64748b">Period</td><td style="font-weight:700">${esc(period)}</td></tr>
          <tr><td style="padding:3px 16px 3px 0;color:#64748b">Payment Cycle</td><td style="font-weight:700">${esc(corp.payment_cycle || '—')}</td></tr>
          <tr><td style="padding:3px 16px 3px 0;color:#64748b">Date Issued</td><td style="font-weight:700">${fmtDate(new Date().toISOString())}</td></tr>
        </table>
      </div>
    </div>

    <!-- Bill To / Billing Details -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px">
        <div style="font-size:9px;font-weight:700;color:#F37A20;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px">Bill To</div>
        <div style="font-size:15px;font-weight:700">${esc(corp.company_name || '—')}</div>
        ${corp.tax_number ? `<div style="font-size:11.5px;color:#64748B;margin-top:3px">VAT/Tax: ${esc(corp.tax_number)}</div>` : ''}
        <div style="font-size:12px;color:#64748B;margin-top:5px">${esc(corp.office_address || corp.address || '—')}</div>
        <div style="font-size:12px;color:#64748B">${esc(corp.invoice_email || corp.email || '—')}</div>
        ${corp.appointed_person ? `<div style="font-size:12px;color:#64748B;margin-top:2px">Attn: ${esc(corp.appointed_person)}</div>` : ''}
      </div>
      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px">
        <div style="font-size:9px;font-weight:700;color:#F37A20;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px">Billing Details</div>
        <div style="font-size:12px;color:#374151">Total Rides: <strong>${data.total_rides}</strong></div>
        <div style="font-size:12px;color:#374151;margin-top:4px">IBAN: <strong>${esc(corp.billing_iban || '—')}</strong></div>
        <div style="font-size:12px;color:#374151;margin-top:4px">Account Ref: <strong>${esc(corp.cid)}</strong></div>
      </div>
    </div>

    <!-- Rides Table -->
    <table style="width:100%;border-collapse:collapse;font-size:11.5px;margin-bottom:6px;table-layout:fixed">
      <colgroup>
        <col style="width:32px">
        <col style="width:90px">
        <col style="width:130px">
        <col><!-- Route: flexible -->
        <col style="width:90px">
      </colgroup>
      <thead>
        <tr style="background:#F37A20;color:#fff;text-align:left">
          <th ${thCell('text-align:center')}>#</th>
          <th ${thCell('')}>Date</th>
          <th ${thCell('')}>Employee</th>
          <th ${thCell('')}>Route</th>
          <th ${thCell('text-align:right')}>Amount Due</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr style="background:#F8FAFC;font-weight:700;font-size:12px;border-top:2px solid #e5e7eb">
          <td colspan="4" ${cell('')}>Total — ${data.total_rides} ride${data.total_rides !== 1 ? 's' : ''}</td>
          <td ${cell('text-align:right;color:#F37A20;font-size:14px')}>${eur(data.total_charge)}</td>
        </tr>
      </tfoot>
    </table>

    <!-- Total Due box + credits earned info -->
    <div style="display:flex;justify-content:flex-end;align-items:flex-end;gap:16px;margin:20px 0">
      ${isRev && data.total_credits > 0 ? `
      <div style="flex:1;max-width:320px;padding:14px 16px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:8px">
        <div style="font-size:10px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Loyalty Credits Earned This Period</div>
        <div style="font-size:22px;font-weight:800;color:#16a34a">€${parseFloat(data.total_credits).toFixed(2)}</div>
        <div style="font-size:10.5px;color:#166534;margin-top:4px">Available for redemption on future bookings. Credits reset monthly.</div>
      </div>` : ''}
      <div style="padding:14px 24px;background:#FFF7ED;border:2px solid #F37A20;border-radius:8px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#7C2D12;text-transform:uppercase;letter-spacing:0.5px">Total Amount Due</div>
        <div style="font-size:34px;font-weight:800;color:#F37A20;margin-top:2px">${eur(data.total_charge)}</div>
        <div style="font-size:10px;color:#92400E;margin-top:2px">from ${esc(corp.company_name || 'Company')}</div>
      </div>
    </div>

    <!-- Footer -->
    <div style="margin-top:16px;font-size:10.5px;color:#64748b;line-height:1.8;border-top:1px solid #E2E8F0;padding-top:12px">
      <div>Queries: <a href="mailto:accounts@powercabs.ie" style="color:#F37A20">accounts@powercabs.ie</a></div>
      <div>Payment is due within the agreed billing cycle. Late payments may incur charges as per contract terms.</div>
      <div style="font-weight:700;margin-top:4px;color:#374151">Thank you for choosing PowerCabs Ireland Limited.</div>
    </div>
  </div>`;
}

function downloadCorpInvoicePdf() {
  const el = document.getElementById('corpInvoicePrint');
  if (!el) { Toast.show('No invoice to download.', 'error'); return; }
  loadPdfLibs(() => {
    html2canvas(el, {scale: 2, useCORS: true, backgroundColor: '#ffffff'}).then(canvas => {
      const { jsPDF } = window.jspdf;
      const pdf       = new jsPDF('p', 'pt', 'a4');
      const imgW      = pdf.internal.pageSize.getWidth();
      const imgH      = (canvas.height * imgW) / canvas.width;
      let y = 0;
      const pageH = pdf.internal.pageSize.getHeight();
      while (y < imgH) {
        if (y > 0) pdf.addPage();
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, -y, imgW, imgH);
        y += pageH;
      }
      const corp = _corpInvoiceData?.corporate;
      pdf.save(`CORP-INV-${corp?.cid || 'invoice'}-${_corpInvoiceData?.period_from || ''}.pdf`);
    });
  });
}

// ─── Revenue History ───────────────────────────────────────────────────────────

let _revSlipData = null;

function loadRevenueHistory() {
  const cid  = document.getElementById('rhCid').value;
  const year = document.getElementById('rhYear').value;
  if (!cid) { Toast.show('Please select a company.', 'error'); return; }

  const btn  = document.querySelector('[onclick="loadRevenueHistory()"]');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Loading…';
  btn.disabled  = true;

  const fd = new FormData();
  fd.append('action',    'get_revenue_history');
  fd.append('cid',       cid);
  fd.append('date_from', year + '-01-01');
  fd.append('date_to',   year + '-12-31');

  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      btn.innerHTML = orig;
      btn.disabled  = false;
      if (!d.success) { Toast.show(d.message || 'Failed to load history.', 'error'); return; }
      renderRevHistory(d);
    })
    .catch(() => {
      btn.innerHTML = orig;
      btn.disabled  = false;
      Toast.show('Network error loading history.', 'error');
    });
}

function renderRevHistory(d) {
  const container = document.getElementById('revHistoryResults');
  const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const eur = n => '€' + parseFloat(n || 0).toFixed(2);

  if (!d.months || d.months.length === 0) {
    container.innerHTML = `<div class="glass-card"><div class="empty-state"><i class="bi bi-calendar-x"></i><h4>No rides found</h4><p>No completed rides for this company in the selected year.</p></div></div>`;
    container.style.display = 'block';
    return;
  }

  const corp  = d.corporate;
  const isRev = d.billing_model === 'revenue';

  // Columns for revenue model: Month | Rides | Amount Billed | Credits Earned | PC Net Revenue | Slip
  // Columns for regular model: Month | Rides | Amount Billed | Slip
  const extraCols      = isRev ? `<th>Credits Earned</th><th>PC Net Revenue</th>` : '';
  const extraTotalCols = isRev
    ? `<td style="color:#16a34a;font-weight:700">${eur(d.grand_credits)}</td><td style="color:#f59e0b;font-weight:700">${eur(d.grand_pc_profit)}</td>`
    : '';

  const rows = d.months.map(m => {
    const extraMCols = isRev
      ? `<td style="color:#16a34a;font-weight:600">${eur(m.total_credits)}</td><td style="color:#f59e0b">${eur(m.total_pc_profit)}</td>`
      : '';
    return `<tr>
      <td style="font-weight:600">${esc(m.month_label)}</td>
      <td>${m.total_rides}</td>
      <td style="font-weight:600">${eur(m.total_charge)}</td>
      ${extraMCols}
      <td>
        <button class="btn-glass" style="font-size:12px;padding:4px 10px"
                onclick="printMonthSlip('${esc(corp.cid)}','${esc(m.date_from)}','${esc(m.date_to)}','${esc(m.month_label)}',this)">
          <i class="bi bi-printer"></i> Slip
        </button>
      </td>
    </tr>`;
  }).join('');

  container.innerHTML = `
    <div class="glass-card">
      <div class="card-header-bar">
        <i class="bi bi-building" style="color:var(--accent)"></i>
        <div class="card-title">${esc(corp.company_name || corp.cid)}</div>
        <div style="margin-left:auto;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <span style="font-size:13px;color:var(--text-muted)">${d.total_rides} rides</span>
          ${isRev && d.grand_credits > 0 ? `
          <span style="font-size:12px;padding:5px 14px;border-radius:20px;background:rgba(22,163,74,0.15);border:1px solid rgba(22,163,74,0.3);color:#4ade80;font-weight:600">
            <i class="bi bi-gift me-1"></i>Credits: ${eur(d.grand_credits)}
          </span>` : ''}
          ${isRev ? `
          <span style="font-size:12px;padding:5px 14px;border-radius:20px;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;font-weight:600">
            <i class="bi bi-graph-up me-1"></i>PC Net: ${eur(d.grand_pc_profit)}
          </span>` : ''}
          <span style="font-size:14px;font-weight:700;color:var(--accent)">Total Billed: ${eur(d.grand_total)}</span>
        </div>
      </div>
      <div class="table-wrap">
        <table class="glass-table">
          <thead><tr><th>Month</th><th>Rides</th><th>Amount Billed</th>${extraCols}<th></th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot>
            <tr style="font-weight:700">
              <td colspan="2">Totals &nbsp;<span style="font-weight:400;color:var(--text-muted);font-size:12px">(${d.total_rides} rides)</span></td>
              <td style="color:var(--accent)">${eur(d.grand_total)}</td>
              ${extraTotalCols}
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>`;
  container.style.display = 'block';
}

function printMonthSlip(cid, dateFrom, dateTo, monthLabel, triggerBtn) {
  const orig = triggerBtn.innerHTML;
  triggerBtn.disabled = true;
  triggerBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

  const fd = new FormData();
  fd.append('action',    'compute_invoice');
  fd.append('cid',       cid);
  fd.append('date_from', dateFrom);
  fd.append('date_to',   dateTo);

  fetch('?page=corporate', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      triggerBtn.disabled = false;
      triggerBtn.innerHTML = orig;
      if (!d.success) { Toast.show(d.message || 'Failed to load slip.', 'error'); return; }
      _revSlipData = d;
      document.getElementById('revSlipContent').innerHTML = buildCorpInvoiceHtml(d);
      Modal.open('revSlipModal');
    })
    .catch(() => {
      triggerBtn.disabled = false;
      triggerBtn.innerHTML = orig;
      Toast.show('Network error.', 'error');
    });
}

function printRevSlip() {
  const content = document.getElementById('revSlipContent').innerHTML;
  const win = window.open('', '_blank');
  win.document.write('<!DOCTYPE html><html><head><title>Corporate Invoice</title><style>body{font-family:Arial,sans-serif;padding:20px;color:#1a1a1a}table{width:100%;border-collapse:collapse}@media print{.no-print{display:none}}</style></head><body>' + content + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); win.close(); }, 300);
}

function downloadRevSlipPdf() {
  const el = document.getElementById('revSlipContent')?.querySelector('#corpInvoicePrint');
  if (!el) { Toast.show('No slip to download.', 'error'); return; }
  loadPdfLibs(() => {
    html2canvas(el, {scale: 2, useCORS: true, backgroundColor: '#ffffff'}).then(canvas => {
      const { jsPDF } = window.jspdf;
      const pdf   = new jsPDF('p', 'pt', 'a4');
      const imgW  = pdf.internal.pageSize.getWidth();
      const imgH  = (canvas.height * imgW) / canvas.width;
      const pageH = pdf.internal.pageSize.getHeight();
      let y = 0;
      while (y < imgH) {
        if (y > 0) pdf.addPage();
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, -y, imgW, imgH);
        y += pageH;
      }
      const corp = _revSlipData?.corporate;
      pdf.save(`CORP-SLIP-${corp?.cid || 'slip'}-${_revSlipData?.period_from || ''}.pdf`);
    });
  });
}
</script>
