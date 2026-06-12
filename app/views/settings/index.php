<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'general');
$tabs = ['general'=>'General','rides'=>'Ride Types & Vehicles','commission'=>'Commission & Fees','surge'=>'Surge Settings','referral'=>'Referral & Promo Rules','content'=>'Terms & Privacy','templates'=>'Email/SMS Templates'];
if (!isset($tabs[$tab])) $tab = 'general';
?>

<div class="page-header">
  <div>
    <h1>Settings &amp; Configuration</h1>
    <p>App settings, commission structure, ride types, and content management.</p>
  </div>
  <button class="btn-primary-glass" onclick="Toast.show('Settings saved.','success')"><i class="bi bi-check-lg"></i> Save Changes</button>
</div>

<!-- Tab nav (vertical sidebar style) -->
<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start">

  <!-- Sidebar -->
  <div class="glass-card" style="padding:8px 0">
    <?php foreach ($tabs as $slug => $label): ?>
    <a href="?page=settings&tab=<?=$slug?>"
       style="display:block;padding:10px 16px;font-size:13px;font-weight:500;text-decoration:none;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'transparent'?>;border-left:3px solid <?=$tab===$slug?'var(--accent)':'transparent'?>;transition:var(--t)">
      <?=$label?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Content -->
  <div>
    <?php if ($tab === 'general'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-gear-fill" style="color:var(--accent)"></i><div class="card-title">General Settings</div></div>
      <div style="padding:24px;display:flex;flex-direction:column;gap:16px;max-width:520px">
        <div><label class="form-label">App Name</label><input type="text" class="glass-input" value="PowerCabs"></div>
        <div><label class="form-label">App Version (iOS)</label><input type="text" class="glass-input" value="1.0.0"></div>
        <div><label class="form-label">App Version (Android)</label><input type="text" class="glass-input" value="1.0.0"></div>
        <div><label class="form-label">Minimum Required Version</label><input type="text" class="glass-input" value="1.0.0"></div>
        <div><label class="form-label">Support Email</label><input type="email" class="glass-input" value="support@powercabs.ie"></div>
        <div><label class="form-label">Support Phone</label><input type="tel" class="glass-input" value="+353 1 000 0000"></div>
        <div><label class="form-label">Default Currency</label><select class="glass-select"><option selected>EUR (€)</option><option>GBP (£)</option></select></div>
        <div><label class="form-label">Time Zone</label><select class="glass-select"><option selected>Europe/Dublin</option><option>Europe/London</option></select></div>
      </div>
    </div>

    <?php elseif ($tab === 'rides'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-car-front" style="color:var(--accent)"></i><div class="card-title">Ride Types &amp; Vehicle Categories</div>
        <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Add type coming soon.','info')"><i class="bi bi-plus"></i> Add Type</button>
      </div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
        <?php foreach ([['Economy','bi-car-front','Standard 4-seat vehicle','€3.00 base + €1.20/km','Active'],['Economy XL','bi-car-front-fill','SUV/MPV 5+ seats','€3.50 base + €1.40/km','Active']] as [$name,$icon,$desc,$fare,$status]): ?>
        <div style="display:flex;align-items:center;gap:16px;padding:16px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
          <i class="bi <?=$icon?>" style="font-size:28px;color:var(--accent)"></i>
          <div style="flex:1">
            <div style="font-weight:600"><?=$name?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?=$desc?> · <?=$fare?></div>
          </div>
          <span class="badge-pill badge-active"><span class="dot"></span><?=$status?></span>
          <button class="btn-icon"><i class="bi bi-pencil"></i></button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'commission'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-percent" style="color:var(--accent)"></i><div class="card-title">Commission &amp; Fee Structure</div></div>
      <div style="padding:24px;display:flex;flex-direction:column;gap:16px;max-width:520px">
        <?php foreach (['Platform Commission (%)'=>'15','Base Fare (€)'=>'3.00','Per KM Rate (€)'=>'1.20','Per Minute Rate (€)'=>'0.20','Minimum Fare (€)'=>'5.00','Booking Fee (€)'=>'0.50','Cancellation Fee (€)'=>'2.00'] as $label=>$val): ?>
        <div><label class="form-label"><?=$label?></label><input type="text" class="glass-input" value="<?=$val?>"></div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($tab === 'surge'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-lightning-charge-fill" style="color:#d97706"></i><div class="card-title">Surge Pricing Settings</div></div>
      <div style="padding:24px;display:flex;flex-direction:column;gap:16px;max-width:520px">
        <div><label class="form-label">Enable Surge Pricing</label>
          <select class="glass-select"><option>Enabled</option><option>Disabled</option></select>
        </div>
        <div><label class="form-label">Demand Threshold to Trigger Surge</label><input type="text" class="glass-input" value="80%" placeholder="e.g. 80% driver utilisation"></div>
        <div><label class="form-label">Minimum Surge Multiplier</label><input type="text" class="glass-input" value="1.0x"></div>
        <div><label class="form-label">Maximum Surge Multiplier</label><input type="text" class="glass-input" value="3.0x"></div>
        <div><label class="form-label">Surge Step Increment</label><input type="text" class="glass-input" value="0.5x"></div>
        <div><label class="form-label">Show Surge Warning to Passengers</label><select class="glass-select"><option>Yes — always</option><option>Only above 1.5x</option><option>No</option></select></div>
      </div>
    </div>

    <?php elseif ($tab === 'referral'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-share-fill" style="color:var(--accent)"></i><div class="card-title">Referral &amp; Promo Rules</div></div>
      <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:640px">
        <div><label class="form-label">Referrer Credit (€)</label><input type="text" class="glass-input" value="5.00"></div>
        <div><label class="form-label">Referee Discount (€)</label><input type="text" class="glass-input" value="5.00"></div>
        <div><label class="form-label">Min Rides Before Reward</label><input type="number" class="glass-input" value="1"></div>
        <div><label class="form-label">Credit Expiry (days)</label><input type="number" class="glass-input" value="30"></div>
        <div style="grid-column:1/-1"><label class="form-label">Max Referrals Per User</label><input type="number" class="glass-input" value="10"></div>
      </div>
    </div>

    <?php elseif ($tab === 'content'): ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-file-text" style="color:var(--accent)"></i><div class="card-title">Terms &amp; Privacy Content</div></div>
      <div style="padding:24px;display:flex;flex-direction:column;gap:16px">
        <div>
          <label class="form-label">Terms of Service (Passenger)</label>
          <textarea class="glass-input" rows="6" placeholder="Paste your terms of service here..."></textarea>
        </div>
        <div>
          <label class="form-label">Terms of Service (Driver)</label>
          <textarea class="glass-input" rows="6" placeholder="Paste driver terms here..."></textarea>
        </div>
        <div>
          <label class="form-label">Privacy Policy</label>
          <textarea class="glass-input" rows="6" placeholder="Paste your privacy policy here..."></textarea>
        </div>
      </div>
    </div>

    <?php else: ?>
    <div class="glass-card">
      <div class="card-header-bar"><i class="bi bi-envelope" style="color:var(--accent)"></i><div class="card-title">Email / SMS Templates</div>
        <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Add template coming soon.','info')"><i class="bi bi-plus"></i> Add Template</button>
      </div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:10px">
        <?php foreach (['Welcome — Passenger','Welcome — Driver','Ride Confirmation','Driver Assigned','Ride Completed — Receipt','Password Reset','Account Suspended','Driver Approved'] as $tmpl): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
          <div style="font-size:13px;font-weight:500"><?=$tmpl?></div>
          <div style="display:flex;gap:6px">
            <button class="btn-icon" onclick="Toast.show('Edit template coming soon.','info')"><i class="bi bi-pencil"></i></button>
            <button class="btn-icon" onclick="Toast.show('Preview coming soon.','info')"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
