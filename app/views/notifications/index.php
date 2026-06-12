<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'push');
$tabs = ['push'=>'Push Campaigns','sms'=>'SMS & Email Broadcast','alerts'=>'System Alerts','scheduled'=>'Scheduled Announcements'];
if (!isset($tabs[$tab])) $tab = 'push';
?>

<div class="page-header">
  <div>
    <h1>Notifications &amp; Alerts</h1>
    <p>Send push notifications, SMS, email broadcasts, and configure system alerts.</p>
  </div>
  <button class="btn-primary-glass" onclick="Toast.show('Send notification coming soon.','info')"><i class="bi bi-send-fill"></i> Send Notification</button>
</div>

<div class="stats-grid">
  <?php foreach ([
    ['bi-bell-fill',       'Sent Today',          '—','#F37A20'],
    ['bi-phone',           'Push Delivered',       '—','#16a34a'],
    ['bi-envelope',        'Emails Sent (MTD)',    '—','#7c3aed'],
    ['bi-chat-text',       'SMS Sent (MTD)',       '—','#d97706'],
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
  <a href="?page=notifications&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'push'): ?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-phone" style="color:var(--accent)"></i><div class="card-title">Push Notification Campaigns</div>
      <span class="badge-pill badge-active ms-2" style="font-size:11px"><?= count($campaigns ?? []) ?></span>
    </div>
    <div class="table-wrap">
      <table class="glass-table">
        <thead><tr><th>Title</th><th>Audience</th><th>Message</th><th>FCM</th><th>Sent At</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($campaigns)): ?>
          <tr><td colspan="6"><div class="empty-state"><i class="bi bi-bell"></i><h4>No campaigns sent yet</h4><p>Compose your first push notification.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($campaigns as $c): ?>
          <tr>
            <td style="font-size:13px;font-weight:500"><?= htmlspecialchars($c['title'] ?? '') ?></td>
            <?php
              $aud = $c['audience'] ?? 'all';
              if (($aud === 'specific_driver' || $aud === 'specific_passenger') && !empty($c['target_name'])) {
                  $audLabel = ($aud === 'specific_driver' ? 'Driver' : 'Passenger') . ': ' . $c['target_name'];
              } else {
                  $audLabel = ucwords(str_replace('_', ' ', $aud));
              }
            ?>
            <td class="text-muted fs-12"><?= htmlspecialchars($audLabel) ?></td>
            <td style="max-width:180px"><span style="font-size:12px;color:var(--text-muted);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['message'] ?? '') ?></span></td>
            <td>
              <?php if ($c['fcm_sent'] ?? false): ?>
                <span class="badge-pill badge-active" style="font-size:11px"><span class="dot"></span>Delivered</span>
              <?php elseif (!empty($c['fcm_error'])): ?>
                <span class="badge-pill badge-suspended" style="font-size:11px;cursor:help" title="<?= htmlspecialchars($c['fcm_error']) ?>"><span class="dot"></span>Failed</span>
              <?php else: ?>
                <span class="badge-pill badge-inactive" style="font-size:11px">Saved</span>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-12"><?= !empty($c['sent_at']) ? date('d M H:i', strtotime($c['sent_at'])) : '—' ?></td>
            <td><span class="badge-pill badge-completed" style="font-size:11px">Sent</span></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-send" style="color:var(--accent)"></i><div class="card-title">Compose Push</div></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
      <div><label class="form-label">Audience</label>
        <select id="pushAudience" class="glass-select" onchange="onAudienceChange()">
          <option value="all">All Users</option>
          <option value="all_passengers">All Passengers</option>
          <option value="all_drivers">All Drivers</option>
          <option value="active_drivers">Active Drivers</option>
          <option value="pending_drivers">Pending Drivers</option>
          <option disabled style="color:var(--text-subtle);font-size:11px">──────────────</option>
          <option value="specific_driver">Specific Driver</option>
          <option value="specific_passenger">Specific Passenger</option>
        </select>
      </div>

      <!-- User search — shown only for specific_driver / specific_passenger -->
      <div id="userSearchWrap" style="display:none">
        <label class="form-label">Search User</label>
        <div style="position:relative">
          <div id="selectedUserChip" style="display:none;align-items:center;gap:8px;padding:8px 10px;background:var(--accent-soft);border:1px solid var(--accent);border-radius:var(--radius-sm);font-size:13px">
            <i class="bi bi-person-fill" style="color:var(--accent);flex-shrink:0"></i>
            <div style="flex:1;min-width:0">
              <div id="selectedUserName" style="font-weight:600;color:var(--text-primary);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
              <div id="selectedUserEmail" style="font-size:11px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
            </div>
            <button onclick="clearSelectedUser()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0" title="Clear selection"><i class="bi bi-x"></i></button>
          </div>
          <input id="userSearchInput" type="text" class="glass-input" placeholder="Type name, email or phone…" oninput="debounceUserSearch(this.value)" autocomplete="off">
          <div id="userSearchResults" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);box-shadow:0 8px 24px rgba(0,0,0,0.25);z-index:999;overflow:hidden;max-height:240px;overflow-y:auto"></div>
        </div>
        <input type="hidden" id="targetUserId">
        <input type="hidden" id="targetUserType">
      </div>

      <div><label class="form-label">Title</label><input id="pushTitle" type="text" class="glass-input" placeholder="Notification title..." maxlength="100"></div>
      <div><label class="form-label">Message</label><textarea id="pushMessage" class="glass-input" rows="3" placeholder="Your message here..." maxlength="500"></textarea></div>
      <button id="pushSendBtn" class="btn-primary-glass w-100" style="justify-content:center" onclick="sendPush()"><i class="bi bi-send"></i> Send Now</button>
    </div>
  </div>
</div>
<script>
(function () {
  // ── Audience toggle ───────────────────────────────────────────────
  window.onAudienceChange = function () {
    const v    = document.getElementById('pushAudience').value;
    const wrap = document.getElementById('userSearchWrap');
    const isSpec = v === 'specific_driver' || v === 'specific_passenger';
    wrap.style.display = isSpec ? 'block' : 'none';
    if (isSpec) {
      document.getElementById('targetUserType').value = v === 'specific_driver' ? 'driver' : 'passenger';
      clearSelectedUser();
    }
  };

  // ── User search ───────────────────────────────────────────────────
  let _st = null;
  window.debounceUserSearch = function (val) {
    clearTimeout(_st);
    const r = document.getElementById('userSearchResults');
    if (val.length < 2) { r.style.display = 'none'; return; }
    _st = setTimeout(() => doSearch(val), 300);
  };

  async function doSearch(q) {
    const type = document.getElementById('targetUserType').value || 'driver';
    const r    = document.getElementById('userSearchResults');
    r.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:var(--text-muted)"><i class="bi bi-arrow-repeat"></i> Searching…</div>';
    r.style.display = 'block';
    try {
      const res  = await fetch('?page=notifications&action=search_users&q=' + encodeURIComponent(q) + '&type=' + type);
      const data = await res.json();
      if (!Array.isArray(data) || !data.length) {
        r.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:var(--text-muted)">No users found.</div>';
        return;
      }
      r.innerHTML = data.map(u =>
        '<div class="usr-row" style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--border)"' +
        ' onmouseover="this.style.background=\'var(--hover-bg)\'" onmouseout="this.style.background=\'\'">' +
        '<div style="width:30px;height:30px;border-radius:50%;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;flex-shrink:0">' +
        '<i class="bi bi-person-fill" style="color:var(--accent);font-size:14px"></i></div>' +
        '<div style="min-width:0;flex:1">' +
          '<div style="font-size:12.5px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + hesc(u.name) + '</div>' +
          '<div style="font-size:11.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
            hesc(u.email || '') + (u.phone ? ' &middot; ' + hesc(u.phone) : '') +
          '</div>' +
        '</div>' +
        (u.has_token ? '<span style="font-size:10px;padding:2px 6px;border-radius:99px;background:#16a34a22;color:#16a34a;flex-shrink:0">FCM</span>' : '') +
        '</div>'
      ).join('');
      // Attach click handlers via data attributes to avoid inline quote issues
      data.forEach((u, i) => {
        r.querySelectorAll('.usr-row')[i].addEventListener('click', () => selectUser(u));
      });
    } catch {
      r.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:var(--text-muted)">Search failed.</div>';
    }
  }

  window.selectUser = function (u) {
    document.getElementById('targetUserId').value         = u.id;
    document.getElementById('selectedUserName').textContent  = u.name || '';
    document.getElementById('selectedUserEmail').textContent = [u.email, u.phone].filter(Boolean).join(' · ');
    document.getElementById('selectedUserChip').style.display  = 'flex';
    document.getElementById('userSearchInput').style.display   = 'none';
    document.getElementById('userSearchResults').style.display = 'none';
  };

  window.clearSelectedUser = function () {
    document.getElementById('targetUserId').value              = '';
    document.getElementById('selectedUserChip').style.display  = 'none';
    document.getElementById('userSearchInput').style.display   = 'block';
    document.getElementById('userSearchInput').value           = '';
    document.getElementById('userSearchResults').style.display = 'none';
  };

  // ── Send push ─────────────────────────────────────────────────────
  window.sendPush = async function () {
    const title    = document.getElementById('pushTitle')?.value.trim();
    const message  = document.getElementById('pushMessage')?.value.trim();
    const audience = document.getElementById('pushAudience')?.value;
    const btn      = document.getElementById('pushSendBtn');
    if (!title || !message) { Toast.show('Title and message are required.', 'error'); return; }

    const isSpec   = audience === 'specific_driver' || audience === 'specific_passenger';
    const targetId = document.getElementById('targetUserId')?.value;
    if (isSpec && !targetId) { Toast.show('Please select a specific user.', 'error'); return; }

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sending…'; }
    const fd = new FormData();
    fd.append('action', 'send_push');
    fd.append('title', title);
    fd.append('message', message);
    fd.append('audience', audience);
    if (isSpec) fd.append('target_id', targetId);
    try {
      const res  = await fetch('?page=notifications', { method: 'POST', body: fd });
      const data = await res.json();
      Toast.show(data.message, data.success ? 'success' : 'error');
      if (data.success) {
        document.getElementById('pushTitle').value   = '';
        document.getElementById('pushMessage').value = '';
        clearSelectedUser();
        setTimeout(() => location.reload(), 900);
      }
    } catch { Toast.show('Network error. Please try again.', 'error'); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Send Now'; } }
  };

  function hesc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>

<?php elseif ($tab === 'sms'): ?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-envelope" style="color:var(--accent)"></i><div class="card-title">SMS &amp; Email Broadcasts</div></div>
    <div class="table-wrap">
      <table class="glass-table">
        <thead><tr><th>Type</th><th>Subject / Message</th><th>Recipients</th><th>Delivered</th><th>Failed</th><th>Sent At</th></tr></thead>
        <tbody><tr><td colspan="6"><div class="empty-state"><i class="bi bi-envelope"></i><h4>No broadcasts sent</h4></div></td></tr></tbody>
      </table>
    </div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-chat-text" style="color:var(--accent)"></i><div class="card-title">Compose Broadcast</div></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
      <div><label class="form-label">Channel</label>
        <select class="glass-select"><option>SMS</option><option>Email</option><option>Both</option></select>
      </div>
      <div><label class="form-label">Audience</label>
        <select class="glass-select"><option>All Users</option><option>All Passengers</option><option>All Drivers</option></select>
      </div>
      <div><label class="form-label">Subject (Email only)</label><input type="text" class="glass-input" placeholder="Email subject..."></div>
      <div><label class="form-label">Message</label><textarea class="glass-input" rows="4" placeholder="Message content..."></textarea></div>
      <button class="btn-primary-glass w-100" style="justify-content:center" onclick="Toast.show('SMS gateway integration coming soon.','info')"><i class="bi bi-send"></i> Send Broadcast</button>
    </div>
  </div>
</div>

<?php elseif ($tab === 'alerts'): ?>
<?php
$licenseAlerts = $alerts['license_expiring'] ?? [];
$pendingAlerts = $alerts['pending_drivers']  ?? [];
$staleAlerts   = $alerts['stale_searching']  ?? [];
$totalAlerts   = count($licenseAlerts) + count($pendingAlerts) + count($staleAlerts);
$now           = new DateTime();
?>

<?php if ($totalAlerts > 0): ?>
<div class="glass-card mb-4">
  <div class="card-header-bar">
    <i class="bi bi-exclamation-circle-fill" style="color:#dc2626"></i>
    <div class="card-title">Active Alerts</div>
    <span class="badge-pill badge-suspended" style="margin-left:8px"><?= $totalAlerts ?></span>
  </div>
  <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">

    <?php foreach ($licenseAlerts as $d):
      $expiry = new DateTime($d['license_expiry']);
      $diff   = max(0, (int)$now->diff($expiry)->days);
    ?>
    <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:var(--radius-sm);border-left:4px solid #d97706;background:rgba(217,119,6,0.08)">
      <i class="bi bi-card-text" style="color:#d97706;font-size:18px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($d['full_name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted)">License expires in <strong><?= $diff ?> day<?= $diff !== 1 ? 's' : '' ?></strong> &mdash; <?= htmlspecialchars(substr($d['license_expiry'], 0, 10)) ?></div>
      </div>
      <a href="?page=drivers" class="btn-glass" style="font-size:12px;padding:5px 12px;white-space:nowrap">View Driver</a>
    </div>
    <?php endforeach; ?>

    <?php foreach ($pendingAlerts as $d):
      $since = new DateTime($d['created_at']);
      $hours = max(0, (int)(($now->getTimestamp() - $since->getTimestamp()) / 3600));
    ?>
    <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:var(--radius-sm);border-left:4px solid #dc2626;background:rgba(220,38,38,0.08)">
      <i class="bi bi-person-x-fill" style="color:#dc2626;font-size:18px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($d['full_name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted)">Pending approval for <strong><?= $hours ?> hour<?= $hours !== 1 ? 's' : '' ?></strong></div>
      </div>
      <a href="?page=drivers&status=pending" class="btn-glass" style="font-size:12px;padding:5px 12px;white-space:nowrap">Review</a>
    </div>
    <?php endforeach; ?>

    <?php foreach ($staleAlerts as $r):
      $since = new DateTime($r['created_at']);
      $mins  = max(0, (int)(($now->getTimestamp() - $since->getTimestamp()) / 60));
    ?>
    <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:var(--radius-sm);border-left:4px solid #7c3aed;background:rgba(124,58,237,0.08)">
      <i class="bi bi-car-front-fill" style="color:#7c3aed;font-size:18px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px">Ride searching for <?= $mins ?> min<?= $mins !== 1 ? 's' : '' ?></div>
        <div style="font-size:12px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['pickup_addr'] ?? 'Unknown pickup') ?></div>
      </div>
      <a href="?page=rides&status=searching" class="btn-glass" style="font-size:12px;padding:5px 12px;white-space:nowrap">View Ride</a>
    </div>
    <?php endforeach; ?>

  </div>
</div>
<?php else: ?>
<div class="glass-card mb-4">
  <div style="padding:32px;text-align:center">
    <i class="bi bi-check-circle-fill" style="font-size:32px;color:#16a34a;display:block;margin-bottom:10px"></i>
    <div style="font-weight:600;font-size:14px;color:var(--text-primary)">All clear — no active alerts</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:4px">No license expirations, pending drivers, or stale rides right now.</div>
  </div>
</div>
<?php endif; ?>

<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-sliders" style="color:var(--accent)"></i><div class="card-title">Alert Configuration</div></div>
  <div style="padding:24px;display:flex;flex-direction:column;gap:14px;max-width:520px">
    <?php foreach ([
      'Driver pending approval for >24 hours',
      'No drivers online in a zone',
      'High ride cancellation rate (>20%)',
      'Payment gateway error detected',
      'Supabase API health check failure',
      'Driver license expiring within 7 days',
    ] as $alert): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
      <span style="font-size:13px"><?= $alert ?></span>
      <label style="position:relative;display:inline-block;width:40px;height:22px;cursor:pointer">
        <input type="checkbox" checked style="opacity:0;width:0;height:0">
        <span style="position:absolute;inset:0;background:var(--accent);border-radius:99px;transition:.3s"></span>
      </label>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-calendar-check" style="color:var(--accent)"></i><div class="card-title">Scheduled Announcements</div>
    <button class="btn-primary-glass" style="margin-left:auto" onclick="Toast.show('Schedule announcement coming soon.','info')"><i class="bi bi-plus"></i> Schedule</button>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Title</th><th>Channel</th><th>Audience</th><th>Scheduled For</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar"></i><h4>No scheduled announcements</h4></div></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>
