<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'users');
$tabs = ['users'=>'Admin Users','roles'=>'Roles & Permissions','audit'=>'Audit Logs','security'=>'Security Settings'];
if (!isset($tabs[$tab])) $tab = 'users';
?>

<div class="page-header">
  <div>
    <h1>Admin User Management</h1>
    <p>Manage admin accounts, roles, permissions, and security settings.</p>
  </div>
  <?php if ($tab === 'users'): ?>
  <button class="btn-primary-glass" onclick="Modal.open('addAdminModal')"><i class="bi bi-person-plus"></i> Add Admin</button>
  <?php endif; ?>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=admins&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'users'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-person-fill" style="color:var(--accent)"></i><div class="card-title">Admin Users</div></div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Admin</th><th>Email</th><th>Role</th><th>2FA</th><th>Last Login</th><th>Sessions</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <tr>
          <td><div class="user-cell"><div class="user-avatar-sm">A</div><div class="user-cell-info"><div class="name">Admin</div><div class="meta">PowerCabs Admin</div></div></div></td>
          <td>admin@powercabs.ie</td>
          <td><span class="badge-pill" style="background:#EDE9FE;color:#7c3aed">Super Admin</span></td>
          <td><span style="color:#d97706;font-size:12px"><i class="bi bi-shield-x"></i> Disabled</span></td>
          <td class="text-muted fs-12">—</td>
          <td class="text-muted fs-12">0 active</td>
          <td><span class="badge-pill badge-active"><span class="dot"></span>Active</span></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" onclick="Toast.show('Edit admin coming soon.','info')"><i class="bi bi-pencil"></i></button>
              <button class="btn-icon danger" onclick="Toast.show('Suspend admin coming soon.','info')"><i class="bi bi-slash-circle"></i></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'roles'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-shield-fill" style="color:var(--accent)"></i><div class="card-title">Roles &amp; Permissions Matrix</div></div>
  <div style="padding:20px;overflow-x:auto">
    <?php
    $roles = ['super_admin'=>'Super Admin','dispatcher'=>'Dispatcher','finance'=>'Finance','support'=>'Support','fleet_manager'=>'Fleet Manager'];
    $modules = ['Dashboard','Rides','Dispatcher Console','Drivers','Passengers','Corporate','Fleet','Finance','Promotions','Zones','Notifications','Analytics','Support','Ratings','Settings','Admin Users','Integrations'];
    $access = [
      'super_admin'  => array_fill(0, count($modules), true),
      'dispatcher'   => [true,true,true,false,false,false,false,false,false,false,false,false,false,false,false,false,false],
      'finance'      => [true,false,false,false,false,true,false,true,false,false,false,true,false,false,false,false,false],
      'support'      => [true,false,false,false,true,false,false,false,false,false,false,false,true,true,false,false,false],
      'fleet_manager'=> [true,false,false,true,false,false,true,false,false,false,false,false,false,false,false,false,false],
    ];
    ?>
    <table class="glass-table" style="min-width:700px">
      <thead>
        <tr>
          <th>Module</th>
          <?php foreach ($roles as $slug => $label): ?>
          <th style="text-align:center"><?=$label?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($modules as $idx => $module): ?>
        <tr>
          <td style="font-weight:500"><?=$module?></td>
          <?php foreach ($roles as $slug => $label): ?>
          <td style="text-align:center">
            <?php if ($access[$slug][$idx] ?? false): ?>
            <i class="bi bi-check-circle-fill" style="color:#16a34a;font-size:16px"></i>
            <?php else: ?>
            <i class="bi bi-dash-circle" style="color:#CBD5E0;font-size:16px"></i>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'audit'): ?>
<div class="glass-card">
  <div class="card-header-bar"><i class="bi bi-journal-text" style="color:var(--accent)"></i><div class="card-title">Audit Logs</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <div class="glass-input-icon" style="width:200px"><i class="bi bi-search input-icon"></i><input class="glass-input" placeholder="Search actions..."></div>
      <input type="date" class="glass-input" style="width:145px">
      <button class="btn-glass" onclick="Toast.show('Export audit log coming soon.','info')"><i class="bi bi-download"></i></button>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Admin</th><th>Action</th><th>Target</th><th>IP Address</th><th>User Agent</th><th>Timestamp</th></tr></thead>
      <tbody><tr><td colspan="6"><div class="empty-state"><i class="bi bi-journal"></i><h4>No audit logs yet</h4><p>All admin actions will be logged here.</p></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-shield-lock-fill" style="color:var(--accent)"></i><div class="card-title">Two-Factor Authentication</div></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;max-width:400px">
      <div style="padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
        <div style="font-weight:600;margin-bottom:4px">Require 2FA for all admins</div>
        <div style="font-size:12px;color:var(--text-muted)">Forces all admin accounts to enable TOTP 2FA before login.</div>
        <select class="glass-select mt-3"><option>Disabled</option><option>Optional</option><option>Required</option></select>
      </div>
      <div style="padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
        <div style="font-weight:600;margin-bottom:4px">Session Timeout (minutes)</div>
        <input type="number" class="glass-input mt-2" value="60">
      </div>
      <div style="padding:12px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--hover-bg)">
        <div style="font-weight:600;margin-bottom:4px">Max Active Sessions Per Admin</div>
        <input type="number" class="glass-input mt-2" value="3">
      </div>
      <button class="btn-primary-glass" onclick="Toast.show('Security settings saved.','success')"><i class="bi bi-check-lg"></i> Save Settings</button>
    </div>
  </div>
  <div class="glass-card">
    <div class="card-header-bar"><i class="bi bi-window-stack" style="color:var(--accent)"></i><div class="card-title">Active Sessions</div></div>
    <div class="empty-state"><i class="bi bi-window"></i><h4>No active sessions</h4><p>All logged-in admin sessions appear here.</p></div>
  </div>
</div>
<?php endif; ?>

<!-- Add Admin Modal -->
<div class="modal-overlay" id="addAdminModal">
  <div class="modal-box">
    <div class="modal-header">
      <i class="bi bi-person-plus-fill" style="color:var(--accent);font-size:20px"></i>
      <span class="modal-title">Add Admin User</span>
      <button class="modal-close" onclick="Modal.close('addAdminModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <form style="display:flex;flex-direction:column;gap:14px">
        <div><label class="form-label">Full Name</label><input type="text" class="glass-input" placeholder="John Murphy" required></div>
        <div><label class="form-label">Email Address</label><input type="email" class="glass-input" placeholder="john@powercabs.ie" required></div>
        <div><label class="form-label">Role</label>
          <select class="glass-select">
            <option value="super_admin">Super Admin</option>
            <option value="dispatcher">Dispatcher</option>
            <option value="finance">Finance</option>
            <option value="support">Support</option>
            <option value="fleet_manager">Fleet Manager</option>
          </select>
        </div>
        <div><label class="form-label">Temporary Password</label><input type="password" class="glass-input" placeholder="••••••••" required></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('addAdminModal')">Cancel</button>
      <button class="btn-primary-glass" onclick="Toast.show('Admin creation coming soon.','info')"><i class="bi bi-check-lg"></i> Create Admin</button>
    </div>
  </div>
</div>
