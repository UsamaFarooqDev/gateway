<?php
$he  = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$eur = fn($v)  => '€' . number_format((float)($v ?? 0), 2);
?>
<style>
/* ── Toggle switch ── */
.pp-sw { position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;flex-shrink:0 }
.pp-sw input { opacity:0;width:0;height:0;position:absolute }
.pp-sw .pp-sl { position:absolute;inset:0;background:#CBD5E0;border-radius:99px;transition:.25s }
.pp-sw .pp-sl::before { content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2) }
.pp-sw input:checked + .pp-sl { background:var(--accent) }
.pp-sw input:checked + .pp-sl::before { transform:translateX(20px) }

/* ── Promo cards ── */
.promo-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px }
.promo-card { background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-card);padding:20px;display:flex;flex-direction:column;transition:var(--t) }
.promo-card:hover { box-shadow:var(--shadow-lg) }

/* ── Small pill badge ── */
.pp-chip { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:600 }

/* ── Modal section header ── */
.ms-divider { font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);padding:14px 0 8px;border-bottom:1px solid rgba(243,122,32,.2);margin-bottom:14px;display:flex;align-items:center;justify-content:space-between }

/* ── Danger button ── */
.btn-danger { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#FEE2E2;color:#dc2626;border:1px solid rgba(220,38,38,.25);border-radius:var(--radius-sm);font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:var(--t) }
.btn-danger:hover { background:#FCA5A5;border-color:rgba(220,38,38,.5) }
</style>

<!-- Page header -->
<div class="page-header">
  <div>
    <h1>Promotions &amp; Pricing</h1>
    <p>Manage fare configurations and promotional campaigns.</p>
  </div>
  <?php if ($tab === 'pricing'): ?>
  <button class="btn-primary-glass" onclick="Modal.open('pricingModal');resetPricingForm()">
    <i class="bi bi-plus-lg"></i> Add Config
  </button>
  <?php else: ?>
  <button class="btn-primary-glass" onclick="openPromoModal()">
    <i class="bi bi-plus-lg"></i> Add Promotion
  </button>
  <?php endif; ?>
</div>

<!-- Stats strip -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <?php
  $sItems = [
    ['value' => $stats['total_pricing'],  'label' => 'Pricing Rules',  'icon' => 'bi-currency-euro',    'color' => 'var(--text-primary)'],
    ['value' => $stats['active_pricing'], 'label' => 'Active Rules',   'icon' => 'bi-check-circle-fill','color' => 'var(--accent)'],
    ['value' => $stats['total_promos'],   'label' => 'Promotions',     'icon' => 'bi-megaphone-fill',   'color' => 'var(--text-primary)'],
    ['value' => $stats['active_promos'],  'label' => 'Live Now',       'icon' => 'bi-broadcast',        'color' => 'var(--accent)'],
  ];
  foreach ($sItems as $s):
  ?>
  <div class="glass-card stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 20px">
    <div class="stat-icon" style="margin:0;flex-shrink:0"><i class="bi <?= $s['icon'] ?>"></i></div>
    <div>
      <div style="font-size:22px;font-weight:700;color:<?= $s['color'] ?>;line-height:1"><?= $s['value'] ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= $s['label'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tab nav -->
<div style="display:flex;gap:4px;margin-bottom:20px">
  <?php foreach (['pricing' => ['bi-currency-euro','Pricing Rules'], 'promotions' => ['bi-tag-fill','Promotions']] as $slug => [$icon, $label]):
    $a = $tab === $slug;
  ?>
  <a href="?page=promotions&tab=<?= $slug ?>"
     style="padding:9px 18px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?= $a?'var(--accent)':'var(--border)' ?>;background:<?= $a?'var(--accent-soft)':'var(--bg-card)' ?>;color:<?= $a?'var(--accent)':'var(--text-muted)' ?>;display:inline-flex;align-items:center;gap:7px;transition:var(--t)">
    <i class="bi <?= $icon ?>"></i> <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'pricing'): ?>
<!-- ══════════════════ PRICING TAB ══════════════════ -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-currency-euro" style="color:var(--accent)"></i>
    <div class="card-title">Fare Configuration Rules</div>
    <span style="margin-left:auto;font-size:12px;color:var(--text-muted)"><?= count($pricing) ?> rule<?= count($pricing)!==1?'s':'' ?></span>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr>
          <th>Ride Type</th><th>Period</th><th>Base</th><th>/ km</th><th>/ min</th>
          <th>Min Fare</th><th>Surge</th><th>Discount</th><th>Commission</th><th>Active</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($pricing)): ?>
        <tr><td colspan="11">
          <div class="empty-state">
            <i class="bi bi-currency-euro"></i>
            <h4>No pricing rules yet</h4>
            <p>Add a fare configuration to define how rides are priced.</p>
            <button class="btn-primary-glass mt-3" onclick="Modal.open('pricingModal');resetPricingForm()">
              <i class="bi bi-plus-lg"></i> Add Config
            </button>
          </div>
        </td></tr>
      <?php else: foreach ($pricing as $p):
        $period = $p['time_period'] ?? 'both';
        [$pIcon, $pColor] = match($period) {
          'night' => ['moon-stars', '#7c3aed'],
          'day'   => ['sun', '#d97706'],
          default => ['clock', 'var(--text-muted)'],
        };
      ?>
        <tr>
          <td><strong style="text-transform:uppercase;letter-spacing:.05em;font-size:13px"><?= $he($p['ride_type']) ?></strong></td>
          <td>
            <span class="pp-chip" style="background:<?= $pColor ?>18;color:<?= $pColor ?>">
              <i class="bi bi-<?= $pIcon ?>-fill"></i> <?= $he(ucfirst($period)) ?>
            </span>
          </td>
          <td><?= $eur($p['base_fare']) ?></td>
          <td><?= $eur($p['per_km_rate']) ?></td>
          <td>€<?= number_format((float)($p['per_min_rate'] ?? 0), 3) ?></td>
          <td><?= $eur($p['minimum_fare']) ?></td>
          <td>
            <?php if (!empty($p['surge_enabled'])): ?>
              <span class="pp-chip" style="background:#FEE2E2;color:#dc2626">
                <i class="bi bi-lightning-charge-fill"></i> <?= $he($p['surge_multiplier']) ?>×
              </span>
            <?php else: ?>
              <span style="color:var(--text-subtle);font-size:12px">Off</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['discount_enabled'])): ?>
              <span class="pp-chip" style="background:#DCFCE7;color:#16a34a">
                <i class="bi bi-percent"></i>
                <?= ($p['discount_type'] ?? 'percentage') === 'percentage'
                    ? $he($p['discount_value']) . '%'
                    : $eur($p['discount_value']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-subtle);font-size:12px">Off</span>
            <?php endif; ?>
          </td>
          <td><?= $he($p['driver_commission_pct'] ?? 0) ?>%</td>
          <td>
            <label class="pp-sw">
              <input type="checkbox" <?= !empty($p['is_active']) ? 'checked' : '' ?>
                onchange="togglePricing('<?= $he($p['id']) ?>',this.checked)">
              <span class="pp-sl"></span>
            </label>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn-icon" title="Edit" onclick="editPricing('<?= $he($p['id']) ?>')">
                <i class="bi bi-pencil-fill"></i>
              </button>
              <button class="btn-icon danger" title="Delete"
                onclick="deletePricingConfirm('<?= $he($p['id']) ?>','<?= $he($p['ride_type'].' — '.ucfirst($period)) ?>')">
                <i class="bi bi-trash3-fill"></i>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pricing Modal -->
<div class="modal-overlay" id="pricingModal">
  <div class="modal-box modal-lg" style="max-width:780px;max-height:90vh;overflow-y:auto">
    <div class="modal-header">
      <div class="modal-title" id="pricingModalTitle">Add Pricing Config</div>
      <button class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div style="padding:24px">
      <input type="hidden" id="pc_id">

      <div class="ms-divider">Basic Info</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:18px">
        <div style="grid-column:1/3">
          <label class="form-label">Ride Type</label>
          <select class="glass-select" id="pc_ride_type">
            <option value="all">All Types (Default)</option>
            <?php foreach ($rideTypes as $rt): ?>
            <option value="<?= $he(strtolower(str_replace(' ','_',$rt['name']))) ?>"><?= $he($rt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Time Period</label>
          <select class="glass-select" id="pc_time_period" onchange="toggleDayHours(this.value)">
            <option value="both">Both (24 h)</option>
            <option value="day">Day only</option>
            <option value="night">Night only</option>
          </select>
        </div>
        <div id="dayHoursWrap" style="display:none;grid-column:1/-1">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><label class="form-label">Day Start Hour (0–23)</label><input type="number" class="glass-input" id="pc_day_start_hour" min="0" max="23" value="8"></div>
            <div><label class="form-label">Day End Hour (0–23)</label><input type="number" class="glass-input" id="pc_day_end_hour" min="0" max="23" value="20"></div>
          </div>
        </div>
      </div>

      <div class="ms-divider">Core Rates</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px">
        <div><label class="form-label">Base Fare (€)</label><input type="number" class="glass-input" id="pc_base_fare" step="0.01" min="0" value="2.50" oninput="updateFarePreview()"></div>
        <div><label class="form-label">Booking Fee (€)</label><input type="number" class="glass-input" id="pc_booking_fee" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Per KM (€)</label><input type="number" class="glass-input" id="pc_per_km_rate" step="0.01" min="0" value="1.50"></div>
        <div><label class="form-label">Per Minute (€)</label><input type="number" class="glass-input" id="pc_per_min_rate" step="0.001" min="0" value="0.200"></div>
        <div><label class="form-label">Type Multiplier</label><input type="number" class="glass-input" id="pc_type_multiplier" step="0.01" min="0.1" value="1.00"></div>
        <div><label class="form-label">Minimum Fare (€)</label><input type="number" class="glass-input" id="pc_minimum_fare" step="0.01" min="0" value="5.00" oninput="updateFarePreview()"></div>
      </div>

      <div class="ms-divider">Fare Estimate Range</div>
      <div style="background:var(--accent-soft);border:1px solid rgba(243,122,32,.2);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:12px;font-size:12px;color:var(--text-muted);line-height:1.5">
        <i class="bi bi-info-circle" style="color:var(--accent)"></i>
        The app multiplies the calculated fare by these values to show passengers an estimated price range.
        e.g. Low <strong>0.85</strong> × High <strong>1.20</strong> on a €10 fare → shows <strong>€8.50 – €12.00</strong>.
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        <div>
          <label class="form-label">Low Estimate Multiplier</label>
          <input type="number" class="glass-input" id="pc_range_low_pct" step="0.001" min="0.1" max="2" value="0.850"
            oninput="updateFarePreview()">
          <div style="font-size:11px;color:var(--text-subtle);margin-top:3px">e.g. 0.85 = show 85% of fare as lower bound</div>
        </div>
        <div>
          <label class="form-label">High Estimate Multiplier</label>
          <input type="number" class="glass-input" id="pc_range_high_pct" step="0.001" min="0.1" max="5" value="1.200"
            oninput="updateFarePreview()">
          <div style="font-size:11px;color:var(--text-subtle);margin-top:3px">e.g. 1.20 = show 120% of fare as upper bound</div>
        </div>
        <div style="grid-column:1/-1">
          <div id="farePreview" style="font-size:12.5px;color:var(--text-muted);padding:8px 12px;background:var(--hover-bg);border-radius:var(--radius-xs)">
            Enter base fare above to preview the displayed range.
          </div>
        </div>
      </div>

      <div class="ms-divider">
        <span>Surge Pricing</span>
        <label class="pp-sw" style="margin:0">
          <input type="checkbox" id="pc_surge_enabled"
            onchange="document.getElementById('surgeFields').style.display=this.checked?'block':'none'">
          <span class="pp-sl"></span>
        </label>
      </div>
      <div id="surgeFields" style="display:none;margin-bottom:18px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px">
          <div><label class="form-label">Surge Multiplier</label><input type="number" class="glass-input" id="pc_surge_multiplier" step="0.1" min="1" value="1.5"></div>
          <div><label class="form-label">Surge Label</label><input type="text" class="glass-input" id="pc_surge_label" placeholder="e.g. High Demand"></div>
        </div>
      </div>

      <div class="ms-divider">
        <span>Discount</span>
        <label class="pp-sw" style="margin:0">
          <input type="checkbox" id="pc_discount_enabled"
            onchange="document.getElementById('discountFields').style.display=this.checked?'block':'none'">
          <span class="pp-sl"></span>
        </label>
      </div>
      <div id="discountFields" style="display:none;margin-bottom:18px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:10px">
          <div>
            <label class="form-label">Type</label>
            <select class="glass-select" id="pc_discount_type">
              <option value="percentage">Percentage (%)</option>
              <option value="fixed">Fixed Amount (€)</option>
            </select>
          </div>
          <div><label class="form-label">Value</label><input type="number" class="glass-input" id="pc_discount_value" step="0.01" min="0" value="0"></div>
          <div><label class="form-label">Min Fare (€)</label><input type="number" class="glass-input" id="pc_discount_min_fare" step="0.01" min="0" value="0"></div>
          <div><label class="form-label">Max Uses</label><input type="number" class="glass-input" id="pc_discount_max_uses" min="0" placeholder="Unlimited"></div>
          <div><label class="form-label">Valid From</label><input type="date" class="glass-input" id="pc_discount_valid_from"></div>
          <div><label class="form-label">Valid Until</label><input type="date" class="glass-input" id="pc_discount_valid_until"></div>
        </div>
        <div><label class="form-label">Discount Label</label><input type="text" class="glass-input" id="pc_discount_label" placeholder="e.g. Weekend Deal"></div>
      </div>

      <div class="ms-divider">Card Surcharge</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px">
        <div><label class="form-label">Percentage (%)</label><input type="number" class="glass-input" id="pc_card_surcharge_pct" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Fixed (€)</label><input type="number" class="glass-input" id="pc_card_surcharge_fixed" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Label</label><input type="text" class="glass-input" id="pc_card_surcharge_label" placeholder="Card Processing Fee"></div>
      </div>

      <div class="ms-divider">Scheduled Rides</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px">
        <div><label class="form-label">Notify Driver (mins)</label><input type="number" class="glass-input" id="pc_schedule_notify_mins" min="0" value="30"></div>
        <div><label class="form-label">Fallback Window (mins)</label><input type="number" class="glass-input" id="pc_schedule_fallback_mins" min="0" value="15"></div>
        <div><label class="form-label">Cancel After (mins)</label><input type="number" class="glass-input" id="pc_schedule_cancel_mins" min="0" value="5"></div>
      </div>

      <div class="ms-divider">Driver Settings</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">
        <div><label class="form-label">Commission (%)</label><input type="number" class="glass-input" id="pc_driver_commission_pct" step="0.01" min="0" max="100" value="20"></div>
        <div><label class="form-label">Min Balance (€)</label><input type="number" class="glass-input" id="pc_driver_min_balance" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Warning Balance (€)</label><input type="number" class="glass-input" id="pc_driver_warning_balance" step="0.01" min="0" value="0"></div>
      </div>

      <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--hover-bg);border-radius:var(--radius-sm);margin-bottom:22px">
        <label class="pp-sw"><input type="checkbox" id="pc_is_active" checked><span class="pp-sl"></span></label>
        <div>
          <div style="font-weight:500;font-size:13.5px;color:var(--text-primary)">Active Rule</div>
          <div style="font-size:12px;color:var(--text-muted)">Enable this config for fare calculation.</div>
        </div>
      </div>

      <div class="modal-footer" style="padding:0;border:none;background:transparent">
        <button type="button" class="btn-glass" onclick="Modal.close('pricingModal')">Cancel</button>
        <button type="button" class="btn-primary-glass" id="savePricingBtn" onclick="savePricing()">
          <i class="bi bi-check-lg"></i> Save Config
        </button>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════ PROMOTIONS TAB ══════════════════ -->

<?php if (empty($promotions)): ?>
<div class="glass-card">
  <div class="empty-state" style="padding:60px 32px">
    <i class="bi bi-megaphone"></i>
    <h4>No promotions yet</h4>
    <p>Create your first promotion to engage passengers and drivers.</p>
    <button class="btn-primary-glass mt-3" onclick="openPromoModal()"><i class="bi bi-plus-lg"></i> Add Promotion</button>
  </div>
</div>
<?php else: ?>
<div class="promo-grid">
  <?php
  $now = time();
  foreach ($promotions as $pr):
    $color    = $he($pr['color'] ?? '#F37A20');
    $startTs  = !empty($pr['starts_at']) ? strtotime($pr['starts_at']) : 0;
    $endTs    = !empty($pr['ends_at'])   ? strtotime($pr['ends_at'])   : 0;
    $isActive = !empty($pr['is_active']);

    if ($endTs && $endTs < $now) {
      [$sLabel,$sBg,$sColor] = ['Expired',  '#F1F5F9','#64748B'];
    } elseif ($startTs && $startTs > $now) {
      [$sLabel,$sBg,$sColor] = ['Upcoming', '#EFF6FF','#2563eb'];
    } elseif ($isActive) {
      [$sLabel,$sBg,$sColor] = ['Live',     '#DCFCE7','#16a34a'];
    } else {
      [$sLabel,$sBg,$sColor] = ['Paused',   '#FEF3C7','#d97706'];
    }

    $audienceMap = ['all' => 'All Users', 'passenger' => 'Passengers', 'driver' => 'Drivers'];
    $audience    = $audienceMap[$pr['target_audience'] ?? 'all'] ?? 'All Users';
  ?>
  <div class="promo-card">
    <!-- Header row -->
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
      <div style="width:46px;height:46px;border-radius:10px;background:<?= $color ?>18;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;color:<?= $color ?>">
        <?= !empty($pr['icon']) ? $he($pr['icon']) : '<i class="bi bi-megaphone-fill"></i>' ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:14.5px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px">
          <?= $he($pr['title']) ?>
        </div>
        <span class="pp-chip" style="background:var(--hover-bg);color:var(--text-muted)">
          <i class="bi bi-people-fill"></i> <?= $he($audience) ?>
        </span>
      </div>
      <span class="pp-chip" style="background:<?= $sBg ?>;color:<?= $sColor ?>;white-space:nowrap"><?= $sLabel ?></span>
    </div>

    <!-- Description -->
    <?php if (!empty($pr['description'])): ?>
    <p style="font-size:13px;color:var(--text-muted);line-height:1.55;margin-bottom:12px;flex:1"><?= $he($pr['description']) ?></p>
    <?php else: ?>
    <div style="flex:1"></div>
    <?php endif; ?>

    <!-- Hyperlink / Action URL -->
    <?php if (!empty($pr['action_url'])): ?>
    <div style="margin-bottom:10px;padding:8px 10px;background:var(--accent-soft);border-radius:var(--radius-xs);border:1px solid rgba(243,122,32,.2)">
      <a href="<?= $he($pr['action_url']) ?>" target="_blank" rel="noopener noreferrer"
         style="font-size:12px;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:5px;overflow:hidden">
        <i class="bi bi-link-45deg" style="flex-shrink:0"></i>
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"><?= $he($pr['action_url']) ?></span>
        <i class="bi bi-arrow-up-right-square" style="flex-shrink:0;opacity:.7"></i>
      </a>
    </div>
    <?php endif; ?>

    <!-- Dates -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <span class="pp-chip" style="background:var(--hover-bg);color:var(--text-muted)">
        <i class="bi bi-calendar3"></i>
        <?= $startTs ? date('d M Y', $startTs) : '—' ?> → <?= $endTs ? date('d M Y', $endTs) : '—' ?>
      </span>
      <span style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;border:1.5px solid rgba(0,0,0,.08)" title="<?= $color ?>"></span>
    </div>

    <!-- Footer -->
    <div style="display:flex;align-items:center;gap:8px;padding-top:10px;border-top:1px solid var(--border)">
      <label class="pp-sw" title="Toggle active">
        <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="togglePromo('<?= $he($pr['id']) ?>',this.checked)">
        <span class="pp-sl"></span>
      </label>
      <span style="flex:1"></span>
      <button class="btn-icon" title="Edit" onclick="editPromo('<?= $he($pr['id']) ?>')">
        <i class="bi bi-pencil-fill"></i>
      </button>
      <button class="btn-icon danger" title="Delete"
        onclick="deletePromoConfirm('<?= $he($pr['id']) ?>','<?= $he($pr['title']) ?>')">
        <i class="bi bi-trash3-fill"></i>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Promo Modal -->
<div class="modal-overlay" id="promoModal">
  <div class="modal-box" style="max-width:560px;max-height:92vh;overflow-y:auto">
    <div class="modal-header">
      <div class="modal-title" id="promoModalTitle">Add Promotion</div>
      <button class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div style="padding:22px">
      <input type="hidden" id="pr_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

        <div style="grid-column:1/-1">
          <label class="form-label">Title <span style="color:#dc2626">*</span></label>
          <input type="text" class="glass-input" id="pr_title" placeholder="e.g. Summer Special">
        </div>

        <div style="grid-column:1/-1">
          <label class="form-label">Description</label>
          <textarea class="glass-input" id="pr_description" rows="3" style="resize:vertical" placeholder="Briefly describe this promotion…"></textarea>
        </div>

        <div>
          <label class="form-label">Accent Color</label>
          <div style="display:flex;align-items:center;gap:8px">
            <input type="color" id="pr_color" value="#F37A20"
              style="width:42px;height:38px;border:1px solid var(--border-input);border-radius:var(--radius-sm);cursor:pointer;padding:2px;flex-shrink:0;background:#fff">
            <input type="text" class="glass-input" id="pr_color_hex" value="#F37A20" maxlength="7"
              placeholder="#F37A20" oninput="syncHexToColor()">
          </div>
        </div>

        <div>
          <label class="form-label">Icon (emoji or class)</label>
          <input type="text" class="glass-input" id="pr_icon" placeholder="🎉">
        </div>

        <div>
          <label class="form-label">Starts At</label>
          <input type="datetime-local" class="glass-input" id="pr_starts_at">
        </div>

        <div>
          <label class="form-label">Ends At</label>
          <input type="datetime-local" class="glass-input" id="pr_ends_at">
        </div>

        <div>
          <label class="form-label">Target Audience</label>
          <select class="glass-select" id="pr_target_audience">
            <option value="all">All Users</option>
            <option value="passenger">Passengers Only</option>
            <option value="driver">Drivers Only</option>
          </select>
        </div>

        <div style="display:flex;align-items:center;gap:10px;padding-top:20px">
          <label class="pp-sw"><input type="checkbox" id="pr_is_active"><span class="pp-sl"></span></label>
          <span class="form-label" style="margin:0">Active</span>
        </div>

        <div style="grid-column:1/-1">
          <label class="form-label"><i class="bi bi-link-45deg"></i> Action URL <span style="color:var(--text-subtle);font-weight:400;text-transform:none;letter-spacing:0">(hyperlink)</span></label>
          <input type="url" class="glass-input" id="pr_action_url" placeholder="https://powercabs.ie/summer-promo">
          <div style="font-size:11.5px;color:var(--text-subtle);margin-top:4px">Shown as a clickable link on the promotion card.</div>
        </div>

      </div>

      <div class="modal-footer" style="padding:18px 0 0;border:none;background:transparent;margin-top:4px">
        <button type="button" class="btn-glass" onclick="Modal.close('promoModal')">Cancel</button>
        <button type="button" class="btn-primary-glass" onclick="savePromo()">
          <i class="bi bi-check-lg"></i> Save Promotion
        </button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- Shared delete confirm modal -->
<div class="modal-overlay" id="ppDeleteModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header" style="border-bottom:1px solid #FCA5A5;background:#FEF2F2">
      <div class="modal-title" style="color:#dc2626"><i class="bi bi-exclamation-triangle-fill"></i> Confirm Delete</div>
      <button class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <p id="ppDeleteMsg" style="color:var(--text-primary);line-height:1.6;margin-bottom:20px"></p>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn-glass" onclick="Modal.close('ppDeleteModal')">Cancel</button>
        <button class="btn-danger" id="ppDeleteConfirmBtn"><i class="bi bi-trash3-fill"></i> Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
const PP_URL = '?page=promotions';

function ppPost(data) {
  return fetch(PP_URL, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams(data),
  }).then(r => r.json());
}

// ── Delete confirm ───────────────────────────────────────────────
function ppShowDelete(msg, handler) {
  document.getElementById('ppDeleteMsg').textContent = msg;
  document.getElementById('ppDeleteConfirmBtn').onclick = handler;
  Modal.open('ppDeleteModal');
}

// ── Color sync (promo modal) ─────────────────────────────────────
function syncHexToColor() {
  const v = document.getElementById('pr_color_hex').value.trim();
  if (/^#[0-9a-fA-F]{6}$/.test(v)) document.getElementById('pr_color').value = v;
}
document.getElementById('pr_color')?.addEventListener('input', e => {
  document.getElementById('pr_color_hex').value = e.target.value;
});

// ══════════════════ PRICING ══════════════════════════════════════

function updateFarePreview() {
  const base = parseFloat(document.getElementById('pc_base_fare')?.value) || 0;
  const min  = document.getElementById('pc_minimum_fare') ? parseFloat(document.getElementById('pc_minimum_fare').value) || 0 : 0;
  const low  = parseFloat(document.getElementById('pc_range_low_pct')?.value) || 0.85;
  const high = parseFloat(document.getElementById('pc_range_high_pct')?.value) || 1.20;
  const el   = document.getElementById('farePreview');
  if (!el) return;
  const exFare = Math.max(base, min) || 10;
  const loVal  = (exFare * low).toFixed(2);
  const hiVal  = (exFare * high).toFixed(2);
  el.innerHTML = `<i class="bi bi-phone" style="color:var(--accent)"></i> App shows: `
    + `<strong>€${loVal}</strong> – <strong>€${hiVal}</strong> `
    + `<span style="color:var(--text-subtle)">(based on €${exFare.toFixed(2)} fare × ${low} / ${high})</span>`;
}

function toggleDayHours(period) {
  const wrap = document.getElementById('dayHoursWrap');
  if (wrap) wrap.style.display = (period === 'day' || period === 'night') ? 'block' : 'none';
}

function resetPricingForm() {
  document.getElementById('pricingModalTitle').textContent = 'Add Pricing Config';
  document.getElementById('pc_id').value = '';
  document.getElementById('pc_ride_type').value      = 'all';
  document.getElementById('pc_time_period').value    = 'both';
  toggleDayHours('both');
  document.getElementById('pc_day_start_hour').value = '8';
  document.getElementById('pc_day_end_hour').value   = '20';
  document.getElementById('pc_base_fare').value      = '2.50';
  document.getElementById('pc_booking_fee').value    = '0';
  document.getElementById('pc_per_km_rate').value    = '1.50';
  document.getElementById('pc_per_min_rate').value   = '0.200';
  document.getElementById('pc_type_multiplier').value= '1.00';
  document.getElementById('pc_minimum_fare').value   = '5.00';
  document.getElementById('pc_surge_enabled').checked   = false;
  document.getElementById('surgeFields').style.display  = 'none';
  document.getElementById('pc_surge_multiplier').value  = '1.5';
  document.getElementById('pc_range_low_pct').value     = '0.850';
  document.getElementById('pc_range_high_pct').value    = '1.200';
  updateFarePreview();
  document.getElementById('pc_surge_label').value       = '';
  document.getElementById('pc_discount_enabled').checked  = false;
  document.getElementById('discountFields').style.display = 'none';
  document.getElementById('pc_discount_type').value      = 'percentage';
  document.getElementById('pc_discount_value').value     = '0';
  document.getElementById('pc_discount_min_fare').value  = '0';
  document.getElementById('pc_discount_max_uses').value  = '';
  document.getElementById('pc_discount_valid_from').value = '';
  document.getElementById('pc_discount_valid_until').value= '';
  document.getElementById('pc_discount_label').value     = '';
  document.getElementById('pc_card_surcharge_pct').value   = '0';
  document.getElementById('pc_card_surcharge_fixed').value  = '0';
  document.getElementById('pc_card_surcharge_label').value  = '';
  document.getElementById('pc_schedule_notify_mins').value   = '30';
  document.getElementById('pc_schedule_fallback_mins').value = '15';
  document.getElementById('pc_schedule_cancel_mins').value   = '5';
  document.getElementById('pc_driver_commission_pct').value  = '20';
  document.getElementById('pc_driver_min_balance').value     = '0';
  document.getElementById('pc_driver_warning_balance').value = '0';
  document.getElementById('pc_is_active').checked = true;
}

function fillPricingForm(d) {
  const period = d.time_period ? (d.time_period.charAt(0).toUpperCase() + d.time_period.slice(1)) : '';
  document.getElementById('pricingModalTitle').textContent =
    'Edit: ' + (d.ride_type ?? 'Unknown') + (period ? ' — ' + period : '');
  const f = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.value = (val !== null && val !== undefined) ? String(val) : '';
  };
  const c = (id, val) => { const el = document.getElementById(id); if (el) el.checked = !!(val === true || val == 1); };

  f('pc_id',                     d.id ?? '');
  const rtVal = (d.ride_type ?? 'all').toLowerCase().replace(/ /g,'_');
  const rtSel = document.getElementById('pc_ride_type');
  rtSel.value = rtVal;
  if (!rtSel.value) {
    for (const opt of rtSel.options) {
      if (opt.value.toLowerCase() === rtVal) { rtSel.value = opt.value; break; }
    }
  }
  f('pc_time_period',            d.time_period ?? 'both');
  toggleDayHours(d.time_period ?? 'both');
  f('pc_day_start_hour',         d.day_start_hour ?? '8');
  f('pc_day_end_hour',           d.day_end_hour ?? '20');
  f('pc_base_fare',              d.base_fare ?? '2.50');
  f('pc_booking_fee',            d.booking_fee ?? '0');
  f('pc_per_km_rate',            d.per_km_rate ?? '1.50');
  f('pc_per_min_rate',           d.per_min_rate ?? '0.200');
  f('pc_type_multiplier',        d.type_multiplier ?? '1.00');
  f('pc_minimum_fare',           d.minimum_fare ?? '5.00');

  const surgeOn = d.surge_enabled === true || d.surge_enabled == 1;
  c('pc_surge_enabled', surgeOn);
  document.getElementById('surgeFields').style.display = surgeOn ? 'block' : 'none';
  f('pc_surge_multiplier',  d.surge_multiplier ?? '1.5');
  f('pc_range_low_pct',     d.range_low_pct ?? '0.850');
  f('pc_range_high_pct',    d.range_high_pct ?? '1.200');
  updateFarePreview();
  f('pc_surge_label',       d.surge_label ?? '');

  const discOn = d.discount_enabled === true || d.discount_enabled == 1;
  c('pc_discount_enabled', discOn);
  document.getElementById('discountFields').style.display = discOn ? 'block' : 'none';
  f('pc_discount_type',        d.discount_type ?? 'percentage');
  f('pc_discount_value',       d.discount_value ?? '0');
  f('pc_discount_min_fare',    d.discount_min_fare ?? '0');
  f('pc_discount_max_uses',    d.discount_max_uses ?? '');
  f('pc_discount_valid_from',  d.discount_valid_from ? d.discount_valid_from.slice(0,10) : '');
  f('pc_discount_valid_until', d.discount_valid_until ? d.discount_valid_until.slice(0,10) : '');
  f('pc_discount_label',       d.discount_label ?? '');
  f('pc_card_surcharge_pct',   d.card_surcharge_pct ?? '0');
  f('pc_card_surcharge_fixed', d.card_surcharge_fixed ?? '0');
  f('pc_card_surcharge_label', d.card_surcharge_label ?? '');
  f('pc_schedule_notify_mins',   d.schedule_notify_mins ?? '30');
  f('pc_schedule_fallback_mins', d.schedule_fallback_mins ?? '15');
  f('pc_schedule_cancel_mins',   d.schedule_cancel_mins ?? '5');
  f('pc_driver_commission_pct',  d.driver_commission_pct ?? '20');
  f('pc_driver_min_balance',     d.driver_min_balance ?? '0');
  f('pc_driver_warning_balance', d.driver_warning_balance ?? '0');
  c('pc_is_active', d.is_active === true || d.is_active == 1);
}

async function editPricing(id) {
  const res = await ppPost({ action: 'get_pricing', id });
  if (res.success) { fillPricingForm(res.data); Modal.open('pricingModal'); }
  else Toast.show('Failed to load config.', 'error');
}

async function savePricing() {
  const id  = document.getElementById('pc_id').value;
  const btn = document.getElementById('savePricingBtn');
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  const res = await ppPost({
    action:                  id ? 'update_pricing' : 'create_pricing', id,
    ride_type:               document.getElementById('pc_ride_type').value,
    time_period:             document.getElementById('pc_time_period').value,
    day_start_hour:          document.getElementById('pc_day_start_hour').value,
    day_end_hour:            document.getElementById('pc_day_end_hour').value,
    base_fare:               document.getElementById('pc_base_fare').value,
    booking_fee:             document.getElementById('pc_booking_fee').value,
    per_km_rate:             document.getElementById('pc_per_km_rate').value,
    per_min_rate:            document.getElementById('pc_per_min_rate').value,
    type_multiplier:         document.getElementById('pc_type_multiplier').value,
    minimum_fare:            document.getElementById('pc_minimum_fare').value,
    surge_enabled:           document.getElementById('pc_surge_enabled').checked ? '1' : '0',
    surge_multiplier:        document.getElementById('pc_surge_multiplier').value,
    range_low_pct:           document.getElementById('pc_range_low_pct').value,
    range_high_pct:          document.getElementById('pc_range_high_pct').value,
    surge_label:             document.getElementById('pc_surge_label').value,
    discount_enabled:        document.getElementById('pc_discount_enabled').checked ? '1' : '0',
    discount_type:           document.getElementById('pc_discount_type').value,
    discount_value:          document.getElementById('pc_discount_value').value,
    discount_min_fare:       document.getElementById('pc_discount_min_fare').value,
    discount_max_uses:       document.getElementById('pc_discount_max_uses').value,
    discount_valid_from:     document.getElementById('pc_discount_valid_from').value,
    discount_valid_until:    document.getElementById('pc_discount_valid_until').value,
    discount_label:          document.getElementById('pc_discount_label').value,
    card_surcharge_pct:      document.getElementById('pc_card_surcharge_pct').value,
    card_surcharge_fixed:    document.getElementById('pc_card_surcharge_fixed').value,
    card_surcharge_label:    document.getElementById('pc_card_surcharge_label').value,
    schedule_notify_mins:    document.getElementById('pc_schedule_notify_mins').value,
    schedule_fallback_mins:  document.getElementById('pc_schedule_fallback_mins').value,
    schedule_cancel_mins:    document.getElementById('pc_schedule_cancel_mins').value,
    driver_commission_pct:   document.getElementById('pc_driver_commission_pct').value,
    driver_min_balance:      document.getElementById('pc_driver_min_balance').value,
    driver_warning_balance:  document.getElementById('pc_driver_warning_balance').value,
    is_active:               document.getElementById('pc_is_active').checked ? '1' : '0',
  });

  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Save Config';
  Toast.show(res.message, res.success ? 'success' : 'error');
  if (res.success) { Modal.close('pricingModal'); setTimeout(() => location.reload(), 600); }
}

async function togglePricing(id, active) {
  const res = await ppPost({ action:'toggle_pricing', id, active: active ? '1':'0' });
  Toast.show(res.message, res.success ? 'success' : 'error');
  if (!res.success) location.reload();
}

function deletePricingConfirm(id, label) {
  ppShowDelete(`Delete pricing rule "${label}"? This cannot be undone.`, async () => {
    const res = await ppPost({ action:'delete_pricing', id });
    Toast.show(res.message, res.success ? 'success' : 'error');
    Modal.close('ppDeleteModal');
    if (res.success) setTimeout(() => location.reload(), 600);
  });
}

// ══════════════════ PROMOTIONS ════════════════════════════════════

function openPromoModal(data = null) {
  document.getElementById('promoModalTitle').textContent = data ? 'Edit Promotion' : 'Add Promotion';
  const f = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };

  f('pr_id',              data?.id ?? '');
  f('pr_title',           data?.title ?? '');
  f('pr_description',     data?.description ?? '');
  const col = data?.color ?? '#F37A20';
  f('pr_color',           col);
  f('pr_color_hex',       col);
  f('pr_icon',            data?.icon ?? '');
  f('pr_starts_at',       data?.starts_at ? data.starts_at.slice(0,16) : '');
  f('pr_ends_at',         data?.ends_at   ? data.ends_at.slice(0,16)   : '');
  f('pr_target_audience', data?.target_audience ?? 'all');
  const active = document.getElementById('pr_is_active');
  if (active) active.checked = data ? (data.is_active === true || data.is_active == 1) : false;
  f('pr_action_url',      data?.action_url ?? '');

  Modal.open('promoModal');
}

async function editPromo(id) {
  const res = await ppPost({ action:'get_promo', id });
  if (res.success) openPromoModal(res.data);
  else Toast.show('Failed to load promotion.', 'error');
}

async function savePromo() {
  const title = document.getElementById('pr_title').value.trim();
  if (!title) { Toast.show('Title is required.', 'error'); document.getElementById('pr_title').focus(); return; }

  const id  = document.getElementById('pr_id').value;
  const res = await ppPost({
    action:          id ? 'update_promo' : 'create_promo', id, title,
    description:     document.getElementById('pr_description').value,
    color:           document.getElementById('pr_color_hex').value || document.getElementById('pr_color').value,
    icon:            document.getElementById('pr_icon').value,
    starts_at:       document.getElementById('pr_starts_at').value,
    ends_at:         document.getElementById('pr_ends_at').value,
    target_audience: document.getElementById('pr_target_audience').value,
    is_active:       document.getElementById('pr_is_active').checked ? '1' : '0',
    action_url:      document.getElementById('pr_action_url').value,
  });

  Toast.show(res.message, res.success ? 'success' : 'error');
  if (res.success) { Modal.close('promoModal'); setTimeout(() => location.reload(), 600); }
}

async function togglePromo(id, active) {
  const res = await ppPost({ action:'toggle_promo', id, active: active ? '1':'0' });
  Toast.show(res.message, res.success ? 'success' : 'error');
  if (!res.success) location.reload();
}

function deletePromoConfirm(id, title) {
  ppShowDelete(`Delete promotion "${title}"? This cannot be undone.`, async () => {
    const res = await ppPost({ action:'delete_promo', id });
    Toast.show(res.message, res.success ? 'success' : 'error');
    Modal.close('ppDeleteModal');
    if (res.success) setTimeout(() => location.reload(), 600);
  });
}
</script>
