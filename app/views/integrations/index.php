<?php
$s      = $settings ?? [];
$stripe = $s['stripe']      ?? [];
$gmaps  = $s['google_maps'] ?? [];
$fcm    = $s['firebase']    ?? [];
$sms    = $s['sms']         ?? [];
$turn   = $s['turn']        ?? [];

function hv(mixed $v): string { return htmlspecialchars((string)($v ?? '')); }

function intgStatus(array $cfg, string $keyField = 'secret_key'): array {
    $hasKey = !empty($cfg[$keyField]);
    $ts     = $cfg['last_test_status'] ?? null;
    if ($ts === 'ok')    return ['badge-active',   'Connected',       '#16a34a'];
    if ($ts === 'error') return ['badge-suspended', 'Error',           '#dc2626'];
    if ($hasKey)         return ['badge-pending',   'Not tested',      '#d97706'];
    return                      ['badge-inactive',  'Not configured',  '#6b7280'];
}

function fmtTested(?string $dt): string {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60)   . 'm ago';
    if ($diff < 86400) return floor($diff / 3600)  . 'h ago';
    return date('d M', strtotime($dt));
}

function testResultDiv(string $id, array $cfg): string {
    $status = $cfg['last_test_status'] ?? null;
    $msg    = $cfg['last_test_msg']    ?? null;
    if (!$msg || !$status) return '<div id="' . $id . '" class="test-result"></div>';
    $cls  = $status === 'ok' ? 'ok' : 'error';
    $icon = $status === 'ok' ? 'check-circle-fill' : 'x-circle-fill';
    return '<div id="' . $id . '" class="test-result ' . $cls . '"><i class="bi bi-' . $icon . '"></i> ' . htmlspecialchars($msg) . '</div>';
}

[$stripeClass, $stripeStatus] = intgStatus($stripe, 'secret_key');
[$gmapsClass,  $gmapsStatus]  = intgStatus($gmaps,  'maps_js_key');
[$fcmClass,    $fcmStatus]    = intgStatus($fcm,    'service_account_json');
[$smsClass,    $smsStatus]    = intgStatus($sms,    'api_key');
[$turnClass,   $turnStatus]   = (function() use ($turn) {
    $ts = $turn['last_test_status'] ?? null;
    if (!empty($turn['url'])) {
        if ($ts === 'ok')    return ['badge-active',   'Reachable'];
        if ($ts === 'error') return ['badge-suspended', 'Unreachable'];
        return                      ['badge-pending',   'Configured'];
    }
    return ['badge-inactive', 'Not configured'];
})();

$mapsKey    = $gmaps['maps_js_key'] ?? '';
$fcmSaEmail = '';
if (!empty($fcm['service_account_json'])) {
    $fcmSa      = json_decode($fcm['service_account_json'], true);
    $fcmSaEmail = $fcmSa['client_email'] ?? '';
}
?>

<style>
.intg-toggle { position:relative; display:inline-block; width:38px; height:21px; cursor:pointer; flex-shrink:0 }
.intg-toggle input { opacity:0; width:0; height:0; position:absolute }
.intg-toggle span { position:absolute; inset:0; background:rgba(255,255,255,0.15); border-radius:99px; transition:.2s }
.intg-toggle input:checked + span { background:var(--accent) }
.intg-toggle span::before { content:''; position:absolute; height:15px; width:15px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s }
.intg-toggle input:checked + span::before { transform:translateX(17px) }
.pw-wrap { position:relative }
.pw-wrap .glass-input { padding-right:38px }
.pw-eye { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:15px; line-height:1; padding:2px }
.pw-eye:hover { color:var(--accent) }
.test-result { font-size:12px; margin-top:8px; padding:8px 12px; border-radius:6px; display:none }
.test-result.ok    { background:#16a34a18; color:#16a34a; border:1px solid #16a34a33; display:block }
.test-result.error { background:#dc262618; color:#dc2626; border:1px solid #dc262633; display:block }
</style>

<div class="page-header">
  <div>
    <h1>Integrations</h1>
    <p>Manage third-party service keys, test connections, and configure the Google Maps heatmap.</p>
  </div>
</div>

<!-- ── Summary table ─────────────────────────────────────────────── -->
<div class="glass-card mb-4">
  <div class="card-header-bar">
    <i class="bi bi-grid-1x2-fill" style="color:var(--accent)"></i>
    <div class="card-title">Integration Status</div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead>
        <tr><th>Integration</th><th>Purpose</th><th>Status</th><th>Enabled</th><th>Last Tested</th><th>Test Result</th><th style="text-align:right">Action</th></tr>
      </thead>
      <tbody>
        <?php
        $rows = [
          ['Stripe',       'bi-credit-card-2-front-fill', '#635BFF', 'Payment Gateway',     $stripeClass, $stripeStatus, $stripe['enabled'] ?? false, $stripe['last_tested'] ?? null, $stripe['last_test_msg'] ?? null, 'stripe'],
          ['Supabase',     'bi-database-fill',            '#3FCF8E', 'Database & Auth',      'badge-active','Connected',  true,                        null,                            null,                             'supabase'],
          ['Google Maps',  'bi-map-fill',                 '#4285F4', 'Maps & Routing',       $gmapsClass,  $gmapsStatus,  $gmaps['enabled'] ?? false,  $gmaps['last_tested'] ?? null,   $gmaps['last_test_msg'] ?? null,  'google_maps'],
          ['Firebase FCM', 'bi-bell-fill',                '#FFCA28', 'Push Notifications',   $fcmClass,    $fcmStatus,    $fcm['enabled'] ?? false,    $fcm['last_tested'] ?? null,     $fcm['last_test_msg'] ?? null,    'firebase'],
          ['SMS Gateway',  'bi-chat-text-fill',           '#0EA5E9', 'SMS Messaging',        $smsClass,    $smsStatus,    $sms['enabled'] ?? false,    $sms['last_tested'] ?? null,     $sms['last_test_msg'] ?? null,    'sms'],
          ['TURN Server',  'bi-router-fill',              '#8B5CF6', 'WebRTC Relay',         $turnClass,   $turnStatus,   $turn['enabled'] ?? true,    $turn['last_tested'] ?? null,    $turn['last_test_msg'] ?? null,   'turn'],
        ];
        foreach ($rows as [$name, $icon, $color, $purpose, $badgeCls, $status, $enabled, $tested, $msg, $section]):
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:30px;height:30px;border-radius:8px;background:<?=$color?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi <?=hv($icon)?>" style="color:<?=$color?>;font-size:14px"></i>
              </div>
              <span style="font-weight:600;font-size:13px"><?=hv($name)?></span>
            </div>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?=hv($purpose)?></td>
          <td><span class="badge-pill <?=hv($badgeCls)?>" style="font-size:11px"><span class="dot"></span><?=hv($status)?></span></td>
          <td>
            <?php if ($section === 'supabase'): ?>
              <span style="font-size:11.5px;color:var(--text-muted)">Always on</span>
            <?php else: ?>
              <label class="intg-toggle" title="<?=$enabled ? 'Enabled' : 'Disabled'?>">
                <input type="checkbox" <?=$enabled ? 'checked' : ''?> onchange="toggleSection('<?=$section?>', this.checked)">
                <span></span>
              </label>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?=fmtTested($tested)?></td>
          <td style="max-width:200px">
            <?php if ($msg): ?>
              <span style="font-size:11.5px;color:<?=($status==='Connected'||$status==='Reachable'||str_contains($status??'','OK')||$status==='Not tested')?'#16a34a':'#dc2626'?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block" title="<?=hv($msg)?>"><?=hv($msg)?></span>
            <?php else: ?>
              <span style="font-size:11.5px;color:var(--text-subtle)">—</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right">
            <button class="btn-glass" style="font-size:12px;padding:5px 12px" onclick="runTest('<?=$section?>', this)">
              <i class="bi bi-plug"></i> Test
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Stripe ────────────────────────────────────────────────────── -->
<div class="glass-card mb-4" id="card-stripe">
  <div class="card-header-bar">
    <i class="bi bi-credit-card-2-front-fill" style="color:#635BFF"></i>
    <div>
      <div class="card-title">Stripe — Payment Gateway</div>
      <div class="card-subtitle">Handles passenger payments and driver payouts</div>
    </div>
    <span class="badge-pill <?=$stripeClass?>" style="margin-left:auto"><span class="dot"></span><?=$stripeStatus?></span>
    <label class="intg-toggle ms-3" title="Toggle Stripe">
      <input type="checkbox" <?=($stripe['enabled']??false)?'checked':''?> onchange="toggleSection('stripe', this.checked)">
      <span></span>
    </label>
  </div>
  <div style="padding:22px;display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:660px">
    <div>
      <label class="form-label">Publishable Key</label>
      <div class="pw-wrap"><input id="stripe_pub" type="text" class="glass-input" placeholder="pk_live_..." value="<?=hv($stripe['publishable_key'])?>">
      </div>
    </div>
    <div>
      <label class="form-label">Secret Key</label>
      <div class="pw-wrap"><input id="stripe_sec" type="password" class="glass-input" placeholder="sk_live_..." value="<?=hv($stripe['secret_key'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('stripe_sec',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <div>
      <label class="form-label">Webhook Secret</label>
      <div class="pw-wrap"><input id="stripe_whsec" type="password" class="glass-input" placeholder="whsec_..." value="<?=hv($stripe['webhook_secret'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('stripe_whsec',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <div>
      <label class="form-label">Mode</label>
      <select id="stripe_mode" class="glass-select">
        <option value="live" <?=($stripe['mode']??'')==='live'?'selected':''?>>Live</option>
        <option value="test" <?=($stripe['mode']??'test')!=='live'?'selected':''?>>Test</option>
      </select>
    </div>
    <div style="grid-column:1/-1">
      <?= testResultDiv('stripe-test-result', $stripe) ?>
    </div>
    <div style="grid-column:1/-1;display:flex;gap:10px">
      <button class="btn-primary-glass" onclick="runTest('stripe',this)"><i class="bi bi-plug"></i> Test Connection</button>
      <button class="btn-glass" onclick="saveSection('stripe',[['publishable_key','stripe_pub'],['secret_key','stripe_sec'],['webhook_secret','stripe_whsec'],['mode','stripe_mode']])"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<!-- ── Supabase ──────────────────────────────────────────────────── -->
<div class="glass-card mb-4">
  <div class="card-header-bar">
    <i class="bi bi-database-fill" style="color:#3FCF8E"></i>
    <div>
      <div class="card-title">Supabase — Database &amp; Auth</div>
      <div class="card-subtitle">PostgreSQL, authentication, storage, and realtime</div>
    </div>
    <span class="badge-pill badge-active" style="margin-left:auto"><span class="dot"></span>Always connected</span>
  </div>
  <div style="padding:22px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:660px">
      <div><label class="form-label">Project URL</label>
        <input type="text" class="glass-input" value="https://ijrnahatonxpuzwjtykd.supabase.co" readonly style="opacity:.6;cursor:default">
      </div>
      <div><label class="form-label">Anon Key</label>
        <div class="pw-wrap">
          <input id="supa_anon" type="password" class="glass-input" value="eyJhbGci…(stored in app)" readonly style="opacity:.6;cursor:default">
          <button class="pw-eye" type="button" onclick="togglePw('supa_anon',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
        </div>
      </div>
    </div>
    <div id="supa-test-result" class="test-result" style="margin-top:8px"></div>
    <div style="margin-top:14px;display:flex;gap:10px">
      <button class="btn-glass" onclick="runTest('supabase',this)"><i class="bi bi-activity"></i> Health Check</button>
    </div>
  </div>
</div>

<!-- ── Google Maps ───────────────────────────────────────────────── -->
<div class="glass-card mb-4" id="card-google_maps">
  <div class="card-header-bar">
    <i class="bi bi-map-fill" style="color:#4285F4"></i>
    <div>
      <div class="card-title">Google Maps — Maps &amp; Routing</div>
      <div class="card-subtitle">Live driver map, zone editor, fare distance, and heatmap</div>
    </div>
    <span class="badge-pill <?=$gmapsClass?>" style="margin-left:auto"><span class="dot"></span><?=$gmapsStatus?></span>
    <label class="intg-toggle ms-3">
      <input type="checkbox" <?=($gmaps['enabled']??false)?'checked':''?> onchange="toggleSection('google_maps', this.checked)">
      <span></span>
    </label>
  </div>
  <div style="padding:22px;max-width:440px">
    <div class="mb-3">
      <label class="form-label">Maps JS API Key</label>
      <div class="pw-wrap"><input id="gmaps_js" type="password" class="glass-input" placeholder="AIza..." value="<?=hv($gmaps['maps_js_key'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('gmaps_js',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Directions / Distance Matrix API Key</label>
      <div class="pw-wrap"><input id="gmaps_dir" type="password" class="glass-input" placeholder="AIza..." value="<?=hv($gmaps['directions_key'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('gmaps_dir',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <?= testResultDiv('google_maps-test-result', $gmaps) ?>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-primary-glass" onclick="runTest('google_maps',this)"><i class="bi bi-plug"></i> Test Key</button>
      <button class="btn-glass" onclick="saveSection('google_maps',[['maps_js_key','gmaps_js'],['directions_key','gmaps_dir']])"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<!-- ── Firebase FCM ──────────────────────────────────────────────── -->
<div class="glass-card mb-4" id="card-firebase">
  <div class="card-header-bar">
    <i class="bi bi-bell-fill" style="color:#FFCA28"></i>
    <div>
      <div class="card-title">Firebase FCM — Push Notifications</div>
      <div class="card-subtitle">Send push notifications to passenger and driver apps</div>
    </div>
    <span class="badge-pill <?=$fcmClass?>" style="margin-left:auto"><span class="dot"></span><?=$fcmStatus?></span>
    <label class="intg-toggle ms-3">
      <input type="checkbox" <?=($fcm['enabled']??false)?'checked':''?> onchange="toggleSection('firebase', this.checked)">
      <span></span>
    </label>
  </div>
  <div style="padding:22px;max-width:660px">
    <div class="mb-3">
      <label class="form-label">Service Account JSON <span style="color:var(--text-muted);font-size:11px">(FCM V1 API)</span></label>
      <?php if ($fcmSaEmail): ?>
      <div style="font-size:12px;color:#16a34a;margin-bottom:6px"><i class="bi bi-check-circle-fill"></i> Configured: <?=hv($fcmSaEmail)?></div>
      <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px">Leave empty to keep existing. Paste a new JSON to replace.</div>
      <?php endif; ?>
      <textarea id="fcm_sa" class="glass-input" rows="5" placeholder='{"type":"service_account","project_id":"...","private_key":"-----BEGIN RSA PRIVATE KEY-----\n...","client_email":"firebase-adminsdk-...@...iam.gserviceaccount.com",...}' style="resize:vertical;font-family:monospace;font-size:11px"></textarea>
      <div style="font-size:11px;color:var(--text-subtle);margin-top:4px"><i class="bi bi-info-circle"></i> Firebase Console → Project Settings → Service Accounts → Generate New Private Key</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Firebase Project ID</label>
      <input id="fcm_proj" type="text" class="glass-input" placeholder="powercabs-12345" value="<?=hv($fcm['project_id'])?>">
    </div>
    <?= testResultDiv('firebase-test-result', $fcm) ?>
    <div style="display:flex;gap:10px;margin-top:10px">
      <button class="btn-primary-glass" onclick="runTest('firebase',this)"><i class="bi bi-send"></i> Test (validate_only)</button>
      <button class="btn-glass" onclick="saveSection('firebase',[['service_account_json','fcm_sa'],['project_id','fcm_proj']])"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<!-- ── SMS Gateway ───────────────────────────────────────────────── -->
<div class="glass-card mb-4" id="card-sms">
  <div class="card-header-bar">
    <i class="bi bi-chat-text-fill" style="color:#0EA5E9"></i>
    <div>
      <div class="card-title">SMS Gateway</div>
      <div class="card-subtitle">OTP delivery, ride confirmations, and mass broadcasts</div>
    </div>
    <span class="badge-pill <?=$smsClass?>" style="margin-left:auto"><span class="dot"></span><?=$smsStatus?></span>
    <label class="intg-toggle ms-3">
      <input type="checkbox" <?=($sms['enabled']??false)?'checked':''?> onchange="toggleSection('sms', this.checked)">
      <span></span>
    </label>
  </div>
  <div style="padding:22px;max-width:440px">
    <div class="mb-3">
      <label class="form-label">Provider</label>
      <select id="sms_provider" class="glass-select">
        <?php foreach (['twilio' => 'Twilio', 'vonage' => 'Vonage (Nexmo)', 'messagebird' => 'MessageBird', 'textmagic' => 'TextMagic'] as $v => $l): ?>
        <option value="<?=$v?>" <?=($sms['provider'] ?? 'twilio') === $v ? 'selected' : ''?>><?=$l?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Account SID / API Key</label>
      <input id="sms_key" type="text" class="glass-input" placeholder="ACxxxxxxxxxxxxxxx" value="<?=hv($sms['api_key'])?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Auth Token / Secret</label>
      <div class="pw-wrap"><input id="sms_token" type="password" class="glass-input" placeholder="••••••••" value="<?=hv($sms['auth_token'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('sms_token',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">From Number</label>
      <input id="sms_from" type="tel" class="glass-input" placeholder="+353..." value="<?=hv($sms['from_number'])?>">
    </div>
    <?= testResultDiv('sms-test-result', $sms) ?>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-primary-glass" onclick="runTest('sms',this)"><i class="bi bi-plug"></i> Test Connection</button>
      <button class="btn-glass" onclick="saveSection('sms',[['provider','sms_provider'],['api_key','sms_key'],['auth_token','sms_token'],['from_number','sms_from']])"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<!-- ── TURN Server ───────────────────────────────────────────────── -->
<div class="glass-card mb-4" id="card-turn">
  <div class="card-header-bar">
    <i class="bi bi-router-fill" style="color:#8B5CF6"></i>
    <div>
      <div class="card-title">TURN Server — WebRTC Relay</div>
      <div class="card-subtitle">Driver-dispatcher voice/video and in-app WebRTC calls</div>
    </div>
    <span class="badge-pill <?=$turnClass?>" style="margin-left:auto"><span class="dot"></span><?=$turnStatus?></span>
    <label class="intg-toggle ms-3">
      <input type="checkbox" <?=($turn['enabled']??true)?'checked':''?> onchange="toggleSection('turn', this.checked)">
      <span></span>
    </label>
  </div>
  <div style="padding:22px;max-width:440px">
    <div class="mb-3"><label class="form-label">TURN Server URL <span style="color:var(--text-muted);font-size:11px">(host:port)</span></label>
      <input id="turn_url" type="text" class="glass-input" placeholder="host:3478" value="<?=hv($turn['url'])?>">
    </div>
    <div class="mb-3"><label class="form-label">Username</label>
      <input id="turn_user" type="text" class="glass-input" value="<?=hv($turn['username'])?>">
    </div>
    <div class="mb-3"><label class="form-label">Credential</label>
      <div class="pw-wrap"><input id="turn_cred" type="password" class="glass-input" value="<?=hv($turn['credential'])?>">
        <button class="pw-eye" type="button" onclick="togglePw('turn_cred',this)" tabindex="-1"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <?= testResultDiv('turn-test-result', $turn) ?>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-glass" onclick="runTest('turn',this)"><i class="bi bi-activity"></i> Test Connectivity</button>
      <button class="btn-glass" onclick="saveSection('turn',[['turn_url','turn_url'],['username','turn_user'],['credential','turn_cred']])"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<!-- ── Google Maps Heatmap ───────────────────────────────────────── -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-geo-alt-fill" style="color:#4285F4"></i>
    <div class="card-title">Driver &amp; Pickup Heatmap</div>
    <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
      <span style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted)">
        <span style="width:10px;height:10px;border-radius:50%;background:#F37A20;display:inline-block"></span> Online drivers
      </span>
      <span style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted)">
        <span style="width:10px;height:10px;border-radius:50%;background:#4285F4;display:inline-block"></span> Pickup density (30d)
      </span>
      <button class="btn-glass" style="font-size:12px;padding:5px 12px" onclick="reloadMap()"><i class="bi bi-arrow-repeat"></i> Refresh</button>
    </div>
  </div>

  <?php if (empty($mapsKey)): ?>
  <div style="padding:40px;text-align:center">
    <i class="bi bi-map" style="font-size:36px;color:var(--text-subtle);display:block;margin-bottom:12px"></i>
    <div style="font-weight:600;font-size:14px;color:var(--text-primary)">Google Maps API Key Required</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:5px">Add your Maps JS API key above and save to enable the live heatmap.</div>
  </div>
  <?php else: ?>
  <div style="padding:16px 20px 6px;display:flex;gap:16px;flex-wrap:wrap">
    <div style="font-size:12.5px;color:var(--text-muted)"><i class="bi bi-people-fill" style="color:var(--accent)"></i> <span id="mapDriverCount">—</span> online drivers</div>
    <div style="font-size:12.5px;color:var(--text-muted)"><i class="bi bi-geo-alt-fill" style="color:#4285F4"></i> <span id="mapPickupCount">—</span> pickups in last 30 days</div>
  </div>
  <div id="intgMap" style="height:480px;border-radius:0 0 var(--radius) var(--radius);overflow:hidden;margin-top:10px"></div>
  <?php endif; ?>
</div>

<script>
// ── Password show/hide ────────────────────────────────────────────
function togglePw(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  const showing = el.type === 'text';
  el.type = showing ? 'password' : 'text';
  const icon = btn.querySelector('i');
  if (icon) icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// ── Toggle enabled ────────────────────────────────────────────────
async function toggleSection(section, enabled) {
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('section', section);
  fd.append('enabled', enabled ? '1' : '0');
  try {
    const res  = await fetch('?page=integrations', { method: 'POST', body: fd });
    const data = await res.json();
    Toast.show(data.message, data.success ? 'success' : 'error');
  } catch {
    Toast.show('Failed to update setting.', 'error');
  }
}

// ── Save section ──────────────────────────────────────────────────
async function saveSection(section, fields) {
  const fd = new FormData();
  fd.append('section', section);
  for (const [key, elId] of fields) {
    const el = document.getElementById(elId);
    fd.append(key, el ? el.value : '');
  }
  try {
    const res  = await fetch('?page=integrations', { method: 'POST', body: fd });
    const data = await res.json();
    Toast.show(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
  } catch {
    Toast.show('Failed to save settings.', 'error');
  }
}

// ── Real test connection ──────────────────────────────────────────
async function runTest(section, btn) {
  const resultEl = document.getElementById(section + '-test-result') ||
                   document.getElementById('supa-test-result');
  const origHtml = btn.innerHTML;
  btn.disabled   = true;
  btn.innerHTML  = '<i class="bi bi-arrow-repeat"></i> Testing…';
  if (resultEl) { resultEl.className = 'test-result'; resultEl.textContent = ''; }

  try {
    const res  = await fetch('?page=integrations&action=test&section=' + encodeURIComponent(section));
    const data = await res.json();
    Toast.show(data.message, data.success ? 'success' : 'error');
    if (resultEl) {
      resultEl.className = 'test-result ' + (data.success ? 'ok' : 'error');
      resultEl.innerHTML = '<i class="bi bi-' + (data.success ? 'check-circle-fill' : 'x-circle-fill') + '"></i> ' +
        data.message.replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    // Reload page after a short delay to update badge
    if (data.success) setTimeout(() => location.reload(), 1800);
  } catch {
    Toast.show('Test failed — network error.', 'error');
    if (resultEl) { resultEl.className = 'test-result error'; resultEl.innerHTML = '<i class="bi bi-x-circle-fill"></i> Network error.'; }
  } finally {
    btn.disabled  = false;
    btn.innerHTML = origHtml;
  }
}

<?php if (!empty($mapsKey)): ?>
// ── Google Maps Heatmap ───────────────────────────────────────────
let _map, _heatLayer, _markers = [];

function initIntgMap() {
  const center = { lat: 53.3498, lng: -6.2603 }; // Dublin
  _map = new google.maps.Map(document.getElementById('intgMap'), {
    center,
    zoom: 12,
    mapTypeId: 'roadmap',
    styles: [
      { elementType: 'geometry',        stylers: [{ color: '#1a1a2e' }] },
      { elementType: 'labels.text.fill',stylers: [{ color: '#8a9bb0' }] },
      { featureType: 'road',            elementType: 'geometry', stylers: [{ color: '#2a2a4a' }] },
      { featureType: 'water',           elementType: 'geometry', stylers: [{ color: '#0d1b2a' }] },
      { featureType: 'poi',             stylers: [{ visibility: 'off' }] },
      { featureType: 'transit',         stylers: [{ visibility: 'off' }] },
    ],
  });
  loadMapData();
}

async function loadMapData() {
  try {
    const res  = await fetch('?page=integrations&action=map_data');
    const data = await res.json();
    const drivers = data.drivers || [];
    const pickups = data.pickups || [];

    document.getElementById('mapDriverCount').textContent = drivers.length;
    document.getElementById('mapPickupCount').textContent = pickups.length;

    // Clear existing
    _markers.forEach(m => m.setMap(null));
    _markers = [];
    if (_heatLayer) _heatLayer.setMap(null);

    // Heatmap layer (pickup density)
    if (pickups.length) {
      _heatLayer = new google.maps.visualization.HeatmapLayer({
        data: pickups.map(p => new google.maps.LatLng(p.lat, p.lng)),
        map:  _map,
        radius: 30,
        gradient: ['rgba(0,0,0,0)','rgba(0,100,255,0.4)','rgba(0,150,255,0.7)','rgba(66,133,244,1)'],
      });
    }

    // Driver markers
    const driverIcon = {
      path: google.maps.SymbolPath.CIRCLE,
      scale: 8,
      fillColor: '#F37A20',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 2,
    };
    drivers.forEach(d => {
      const marker = new google.maps.Marker({
        position: { lat: d.lat, lng: d.lng },
        map:   _map,
        icon:  driverIcon,
        title: d.name,
      });
      const info = new google.maps.InfoWindow({
        content: `<div style="font-size:13px;font-weight:600;color:#000">${d.name}</div><div style="font-size:11px;color:#666">Online Driver</div>`,
      });
      marker.addListener('click', () => info.open(_map, marker));
      _markers.push(marker);
    });

    // Auto-fit bounds if we have data
    if (drivers.length || pickups.length) {
      const bounds = new google.maps.LatLngBounds();
      [...drivers, ...pickups].forEach(p => bounds.extend({ lat: p.lat, lng: p.lng }));
      _map.fitBounds(bounds);
    }
  } catch (e) {
    console.error('Map data load failed', e);
  }
}

function reloadMap() { loadMapData(); }
<?php endif; ?>
</script>

<?php if (!empty($mapsKey)): ?>
<script
  src="https://maps.googleapis.com/maps/api/js?key=<?=hv($mapsKey)?>&libraries=visualization&callback=initIntgMap"
  async defer></script>
<?php endif; ?>
