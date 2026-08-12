<?php
$selfId     = $_SESSION['admin_id']   ?? '';
$selfRole   = $_SESSION['admin_role'] ?? '';
$isSA       = $selfRole === 'super_admin';

$roleLabels = [
    'super_admin'  => 'Super Admin',
    'dispatcher'   => 'Dispatcher',
    'finance'      => 'Finance',
    'support'      => 'Support',
    'fleet_manager'=> 'Fleet Manager',
];
$roleBadge = fn(string $r): string => match($r) {
    'super_admin'   => 'rgba(239,68,68,0.15)',
    'dispatcher'    => 'rgba(59,130,246,0.15)',
    'finance'       => 'rgba(16,185,129,0.15)',
    'support'       => 'rgba(245,158,11,0.15)',
    'fleet_manager' => 'rgba(139,92,246,0.15)',
    default         => 'rgba(255,255,255,0.08)',
};
$roleColor = fn(string $r): string => match($r) {
    'super_admin'   => '#f87171',
    'dispatcher'    => '#60a5fa',
    'finance'       => '#34d399',
    'support'       => '#fbbf24',
    'fleet_manager' => '#a78bfa',
    default         => 'var(--text-muted)',
};

// Gateway and Dispatcher module definitions
$gwModules = Permission::GATEWAY_MODULES;
$dpModules = Permission::DISPATCHER_MODULES;
?>

<div class="page-header">
  <div>
    <h1>Admin Users</h1>
    <p>Manage gateway and dispatcher user accounts, roles, and permissions.</p>
  </div>
  <?php if ($canCreate): ?>
  <button type="button" class="btn-primary-glass" onclick="openUserModal()">
    <i class="bi bi-person-plus-fill"></i> Add User
  </button>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="--cols:4">
  <?php
    $total      = count($users);
    $active     = count(array_filter($users, fn($u) => $u['is_active']));
    $gwUsers    = count(array_filter($users, fn($u) => in_array('gateway',    $u['apps'])));
    $dpUsers    = count(array_filter($users, fn($u) => in_array('dispatcher', $u['apps'])));
  ?>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:rgba(243,122,32,0.15);color:var(--accent)"><i class="bi bi-people-fill"></i></div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:rgba(22,163,74,0.15);color:#4ade80"><i class="bi bi-person-check-fill"></i></div>
    <div class="stat-value"><?= $active ?></div>
    <div class="stat-label">Active</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:rgba(59,130,246,0.15);color:#60a5fa"><i class="bi bi-window"></i></div>
    <div class="stat-value"><?= $gwUsers ?></div>
    <div class="stat-label">Gateway Access</div>
  </div>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:rgba(139,92,246,0.15);color:#a78bfa"><i class="bi bi-broadcast"></i></div>
    <div class="stat-value"><?= $dpUsers ?></div>
    <div class="stat-label">Dispatcher Access</div>
  </div>
</div>

<!-- User Table -->
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-shield-lock" style="color:var(--accent)"></i>
    <div class="card-title">All Admin Users</div>
    <div style="margin-left:auto">
      <input type="text" id="userSearch" class="glass-input" placeholder="Search users…" style="width:220px;padding:6px 12px;font-size:13px"
             oninput="filterUsers(this.value)">
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table" id="usersTable">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>App Access</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Created</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $initials = strtoupper(substr($u['name'],0,1));
          if (strpos($u['name'],' ')!==false) $initials .= strtoupper($u['name'][strpos($u['name'],' ')+1]);
          $roleKey  = $u['role'] ?? 'dispatcher';
          $apps     = $u['apps'] ?? [];
          $isActive = (bool)$u['is_active'];
          $isSelf   = $u['id'] === $selfId;
          $lastLogin = $u['last_login'] ? date('d M y H:i', strtotime($u['last_login'])) : '—';
          $created   = date('d M Y', strtotime($u['created_at']));
        ?>
        <tr data-search="<?= htmlspecialchars(strtolower($u['name'].' '.$u['email'].' '.($roleLabels[$roleKey]??''))) ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#e06010);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">
                <?= htmlspecialchars($initials) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($u['name']) ?><?= $isSelf ? ' <span style="font-size:10px;color:var(--accent)">(you)</span>' : '' ?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?= $roleBadge($roleKey) ?>;color:<?= $roleColor($roleKey) ?>">
              <?= htmlspecialchars($roleLabels[$roleKey] ?? ucfirst($roleKey)) ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <?php if (in_array('gateway',    $apps)): ?><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(59,130,246,0.15);color:#60a5fa">Gateway</span><?php endif; ?>
              <?php if (in_array('dispatcher', $apps)): ?><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:rgba(139,92,246,0.15);color:#a78bfa">Dispatcher</span><?php endif; ?>
              <?php if (empty($apps)): ?><span style="font-size:11px;color:var(--text-subtle)">—</span><?php endif; ?>
            </div>
          </td>
          <td>
            <?php if ($isActive): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(22,163,74,0.15);color:#4ade80">
                <i class="bi bi-circle-fill" style="font-size:7px"></i> Active
              </span>
            <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(239,68,68,0.12);color:#f87171">
                <i class="bi bi-circle" style="font-size:7px"></i> Inactive
              </span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($lastLogin) ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($created) ?></td>
          <td style="text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:nowrap">
              <?php if ($canEdit): ?>
              <button type="button" class="btn-glass" style="padding:4px 10px;font-size:12px"
                      onclick="editUser('<?= htmlspecialchars($u['id']) ?>')"
                      title="Edit user">
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn-glass" style="padding:4px 10px;font-size:12px"
                      onclick="openPermissionsModal('<?= htmlspecialchars($u['id']) ?>','<?= htmlspecialchars(addslashes($u['name'])) ?>')"
                      title="Manage permissions">
                <i class="bi bi-key"></i>
              </button>
              <button type="button" class="btn-glass" style="padding:4px 10px;font-size:12px"
                      onclick="openResetPwModal('<?= htmlspecialchars($u['id']) ?>','<?= htmlspecialchars(addslashes($u['name'])) ?>')"
                      title="Reset password">
                <i class="bi bi-lock-fill"></i>
              </button>
              <?php if (!$isSelf): ?>
              <button type="button" class="btn-glass" style="padding:4px 10px;font-size:12px;color:<?= $isActive ? '#fbbf24' : '#4ade80' ?>"
                      onclick="toggleUser('<?= htmlspecialchars($u['id']) ?>',<?= $isActive ? 'false' : 'true' ?>)"
                      title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                <i class="bi bi-<?= $isActive ? 'person-dash' : 'person-check' ?>"></i>
              </button>
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($canDelete && !$isSelf): ?>
              <button type="button" class="btn-glass" style="padding:4px 10px;font-size:12px;color:#f87171"
                      onclick="confirmDeleteUser('<?= htmlspecialchars($u['id']) ?>','<?= htmlspecialchars(addslashes($u['name'])) ?>')"
                      title="Delete user">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><h4>No users found</h4></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════════ CREATE / EDIT USER MODAL ═══════════════════ -->
<div class="modal-overlay" id="userModal">
  <div class="modal-box" style="max-width:760px;max-height:90vh;overflow-y:auto">
    <div class="modal-header">
      <h3 id="userModalTitle">Add User</h3>
      <button class="modal-close" onclick="Modal.close('userModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editUserId">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <label class="form-label">Full Name *</label>
          <input type="text" id="uName" class="glass-input" placeholder="John Murphy">
        </div>
        <div>
          <label class="form-label">Email Address *</label>
          <input type="email" id="uEmail" class="glass-input" placeholder="john@example.com">
        </div>
        <div>
          <label class="form-label" id="uPwLabel">Password *</label>
          <input type="password" id="uPassword" class="glass-input" placeholder="Min. 8 characters" autocomplete="new-password">
        </div>
        <div>
          <label class="form-label">Role *</label>
          <select id="uRole" class="glass-select">
            <option value="dispatcher">Dispatcher</option>
            <option value="finance">Finance</option>
            <option value="support">Support</option>
            <option value="fleet_manager">Fleet Manager</option>
            <?php if ($isSA): ?><option value="super_admin">Super Admin</option><?php endif; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Status</label>
          <select id="uIsActive" class="glass-select">
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Application Access -->
      <div style="margin-top:20px">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">
          <i class="bi bi-grid-3x3-gap me-1"></i> Application Access
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:1px solid var(--border);cursor:pointer;background:var(--hover-bg)">
            <input type="checkbox" id="uAppGateway" value="gateway" style="accent-color:var(--accent);width:15px;height:15px">
            <i class="bi bi-window" style="color:#60a5fa"></i>
            <span style="font-size:13px;font-weight:500">Gateway</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:1px solid var(--border);cursor:pointer;background:var(--hover-bg)">
            <input type="checkbox" id="uAppDispatcher" value="dispatcher" style="accent-color:var(--accent);width:15px;height:15px">
            <i class="bi bi-broadcast" style="color:#a78bfa"></i>
            <span style="font-size:13px;font-weight:500">Dispatcher</span>
          </label>
        </div>
      </div>

      <!-- Module Permissions -->
      <div style="margin-top:20px" id="permSection">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">
          <i class="bi bi-key me-1"></i> Module Permissions
        </div>
        <div id="permTabs" style="display:flex;gap:4px;margin-bottom:14px">
          <button type="button" class="perm-tab active" data-perm-app="gateway" onclick="switchPermTab('gateway')">
            <i class="bi bi-window"></i> Gateway
          </button>
          <button type="button" class="perm-tab" data-perm-app="dispatcher" onclick="switchPermTab('dispatcher')">
            <i class="bi bi-broadcast"></i> Dispatcher
          </button>
        </div>

        <!-- Gateway modules -->
        <div id="permPanel-gateway">
          <?= buildPermMatrix('gateway', $gwModules) ?>
        </div>

        <!-- Dispatcher modules -->
        <div id="permPanel-dispatcher" style="display:none">
          <?= buildPermMatrix('dispatcher', $dpModules) ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn-glass" onclick="Modal.close('userModal')">Cancel</button>
        <button type="button" class="btn-primary-glass" id="saveUserBtn" onclick="saveUser(this)">
          <i class="bi bi-check-lg"></i> Save User
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ STANDALONE PERMISSIONS MODAL ═══════════════ -->
<div class="modal-overlay" id="permModal">
  <div class="modal-box" style="max-width:700px;max-height:90vh;overflow-y:auto">
    <div class="modal-header">
      <h3>Permissions — <span id="permModalName"></span></h3>
      <button class="modal-close" onclick="Modal.close('permModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="permUserId">

      <!-- App access -->
      <div style="margin-bottom:16px">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Application Access</div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer">
            <input type="checkbox" id="pAppGateway" value="gateway" style="accent-color:var(--accent);width:14px;height:14px">
            <i class="bi bi-window" style="color:#60a5fa"></i>
            <span style="font-size:13px">Gateway</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer">
            <input type="checkbox" id="pAppDispatcher" value="dispatcher" style="accent-color:var(--accent);width:14px;height:14px">
            <i class="bi bi-broadcast" style="color:#a78bfa"></i>
            <span style="font-size:13px">Dispatcher</span>
          </label>
        </div>
      </div>

      <div style="display:flex;gap:4px;margin-bottom:14px">
        <button type="button" class="perm-tab active" onclick="switchPermTab2('gateway')">
          <i class="bi bi-window"></i> Gateway
        </button>
        <button type="button" class="perm-tab" onclick="switchPermTab2('dispatcher')">
          <i class="bi bi-broadcast"></i> Dispatcher
        </button>
      </div>
      <div id="pPanel-gateway">
        <?= buildPermMatrix('gateway', $gwModules, 'p') ?>
      </div>
      <div id="pPanel-dispatcher" style="display:none">
        <?= buildPermMatrix('dispatcher', $dpModules, 'p') ?>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn-glass" onclick="Modal.close('permModal')">Cancel</button>
        <button type="button" class="btn-primary-glass" onclick="savePermissions(this)">
          <i class="bi bi-check-lg"></i> Save Permissions
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ RESET PASSWORD MODAL ═══════════════════════ -->
<div class="modal-overlay" id="resetPwModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h3>Reset Password — <span id="resetPwName"></span></h3>
      <button class="modal-close" onclick="Modal.close('resetPwModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="resetPwUserId">
      <label class="form-label">New Password *</label>
      <input type="password" id="resetPwValue" class="glass-input" placeholder="Min. 8 characters" autocomplete="new-password">
      <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn-glass" onclick="Modal.close('resetPwModal')">Cancel</button>
        <button type="button" class="btn-primary-glass" onclick="doResetPassword(this)">
          <i class="bi bi-lock-fill"></i> Reset Password
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ DELETE CONFIRM MODAL ════════════════════════ -->
<div class="modal-overlay" id="deleteUserModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <h3 style="color:#f87171"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete User</h3>
      <button class="modal-close" onclick="Modal.close('deleteUserModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-muted);margin-bottom:4px">You are about to permanently delete:</p>
      <p style="font-weight:700;font-size:16px" id="deleteUserName"></p>
      <p style="font-size:12px;color:var(--text-muted)">This will also remove all their application access and permissions. This action cannot be undone.</p>
      <input type="hidden" id="deleteUserId">
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn-glass" onclick="Modal.close('deleteUserModal')">Cancel</button>
        <button type="button" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer"
                onclick="doDeleteUser(this)">
          <i class="bi bi-trash"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.perm-tab {
  padding:7px 16px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;
  border:1px solid var(--border);background:var(--bg-card);color:var(--text-muted);
  display:inline-flex;align-items:center;gap:6px;transition:var(--t);
}
.perm-tab.active { border-color:var(--accent);background:var(--accent-soft);color:var(--accent); }
.perm-matrix { width:100%;border-collapse:collapse;font-size:12px }
.perm-matrix th { padding:6px 8px;text-align:left;color:var(--text-muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border) }
.perm-matrix th:not(:first-child) { text-align:center;width:64px }
.perm-matrix td { padding:7px 8px;border-bottom:1px solid rgba(255,255,255,0.05) }
.perm-matrix td:not(:first-child) { text-align:center }
.perm-matrix tr:last-child td { border-bottom:none }
.perm-matrix tr:hover td { background:var(--hover-bg) }
.perm-cb { width:15px;height:15px;accent-color:var(--accent);cursor:pointer }
.select-all-row { background:rgba(243,122,32,0.04) }
</style>

<?php

function buildPermMatrix(string $app, array $modules, string $prefix = 'u'): string
{
    $actions = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];
    $id      = fn(string $m, string $a) => "{$prefix}Perm_{$app}_{$m}_{$a}";
    $name    = fn(string $m, string $a) => "module_perms[{$app}][{$m}][{$a}]";

    $html  = '<table class="perm-matrix">';
    $html .= '<thead><tr><th>Module</th>';
    foreach ($actions as $aKey => $aLabel) {
        $html .= "<th>{$aLabel}</th>";
    }
    $html .= '</tr></thead><tbody>';

    // "Select all" header row per action
    $html .= '<tr class="select-all-row"><td style="font-size:11px;color:var(--text-muted);font-style:italic">Toggle all</td>';
    foreach (array_keys($actions) as $aKey) {
        $allId = "{$prefix}All_{$app}_{$aKey}";
        $html .= "<td><input type='checkbox' class='perm-cb' id='{$allId}' title='Toggle all {$aKey}'"
               . " onchange=\"toggleAllPerms('{$app}','{$aKey}','{$prefix}',this.checked)\"></td>";
    }
    $html .= '</tr>';

    foreach ($modules as $mKey => $mLabel) {
        $html .= "<tr><td style='font-size:12px'>" . htmlspecialchars($mLabel) . "</td>";
        foreach (array_keys($actions) as $aKey) {
            $cbId   = $id($mKey, $aKey);
            $cbName = $name($mKey, $aKey);
            $html  .= "<td><input type='checkbox' class='perm-cb perm-mod-cb {$prefix}-{$app}-{$aKey}' "
                    . "id='{$cbId}' name='{$cbName}' value='1'></td>";
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}
?>

<script>
const GW_MODULES = <?= json_encode(array_keys(Permission::GATEWAY_MODULES)) ?>;
const DP_MODULES = <?= json_encode(array_keys(Permission::DISPATCHER_MODULES)) ?>;

function filterUsers(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#usersTable tbody tr[data-search]').forEach(tr => {
    tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
  });
}

// ── Permission tab switching ──────────────────────────────────────────────────

function switchPermTab(app) {
  document.querySelectorAll('[data-perm-app]').forEach(t => {
    t.classList.toggle('active', t.dataset.permApp === app);
  });
  document.getElementById('permPanel-gateway').style.display    = app === 'gateway'    ? '' : 'none';
  document.getElementById('permPanel-dispatcher').style.display = app === 'dispatcher' ? '' : 'none';
}

function switchPermTab2(app) {
  document.querySelectorAll('#permModal .perm-tab').forEach(t => {
    t.classList.toggle('active', t.textContent.toLowerCase().includes(app));
  });
  document.getElementById('pPanel-gateway').style.display    = app === 'gateway'    ? '' : 'none';
  document.getElementById('pPanel-dispatcher').style.display = app === 'dispatcher' ? '' : 'none';
}

function toggleAllPerms(app, action, prefix, checked) {
  document.querySelectorAll(`.${prefix}-${app}-${action}`).forEach(cb => { cb.checked = checked; });
}

// ── Open create modal ─────────────────────────────────────────────────────────

function openUserModal() {
  document.getElementById('userModalTitle').textContent = 'Add User';
  document.getElementById('editUserId').value = '';
  document.getElementById('uName').value    = '';
  document.getElementById('uEmail').value   = '';
  document.getElementById('uPassword').value= '';
  document.getElementById('uRole').value    = 'dispatcher';
  document.getElementById('uIsActive').value= 'true';
  document.getElementById('uAppGateway').checked    = false;
  document.getElementById('uAppDispatcher').checked = false;
  document.getElementById('uPwLabel').textContent = 'Password *';
  clearPermMatrix('u');
  Modal.open('userModal');
}

// ── Open edit modal ───────────────────────────────────────────────────────────

function editUser(id) {
  const fd = new FormData();
  fd.append('action', 'get_user');
  fd.append('user_id', id);
  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.success) { Toast.show(d.message || 'Failed to load user.', 'error'); return; }
      const u = d.data;
      document.getElementById('userModalTitle').textContent = 'Edit User';
      document.getElementById('editUserId').value      = u.id;
      document.getElementById('uName').value           = u.name     || '';
      document.getElementById('uEmail').value          = u.email    || '';
      document.getElementById('uPassword').value       = '';
      document.getElementById('uRole').value           = u.role     || 'dispatcher';
      document.getElementById('uIsActive').value       = u.is_active ? 'true' : 'false';
      document.getElementById('uPwLabel').textContent  = 'Password (leave blank to keep)';
      document.getElementById('uAppGateway').checked    = (u.apps || []).includes('gateway');
      document.getElementById('uAppDispatcher').checked = (u.apps || []).includes('dispatcher');
      applyPermsToMatrix(u, 'u');
      Modal.open('userModal');
    });
}

// ── Save user (create or update) ─────────────────────────────────────────────

function saveUser(btn) {
  const isEdit  = !!document.getElementById('editUserId').value;
  const orig    = btn.innerHTML;
  const name    = document.getElementById('uName').value.trim();
  const email   = document.getElementById('uEmail').value.trim();
  const pw      = document.getElementById('uPassword').value;

  if (!name || !email)         { Toast.show('Name and email are required.',    'error'); return; }
  if (!isEdit && !pw)          { Toast.show('Password is required.',           'error'); return; }
  if (pw && pw.length < 8)     { Toast.show('Password must be 8+ characters.', 'error'); return; }

  btn.disabled  = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  const fd = new FormData();
  fd.append('action',    isEdit ? 'update_user' : 'create_user');
  fd.append('user_id',   document.getElementById('editUserId').value);
  fd.append('name',      name);
  fd.append('email',     email);
  fd.append('password',  pw);
  fd.append('role',      document.getElementById('uRole').value);
  fd.append('is_active', document.getElementById('uIsActive').value);

  // App access
  ['gateway','dispatcher'].forEach(app => {
    const cb = document.getElementById('uApp' + app.charAt(0).toUpperCase() + app.slice(1));
    if (cb && cb.checked) fd.append('apps[]', app);
  });

  // Module perms
  appendPermsToFd(fd, 'u');

  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      btn.disabled  = false;
      btn.innerHTML = orig;
      Toast.show(d.message || (d.success ? 'Saved.' : 'Failed.'), d.success ? 'success' : 'error');
      if (d.success) { Modal.close('userModal'); setTimeout(() => location.reload(), 600); }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; Toast.show('Network error.', 'error'); });
}

// ── Permissions modal ─────────────────────────────────────────────────────────

function openPermissionsModal(id, name) {
  document.getElementById('permUserId').value  = id;
  document.getElementById('permModalName').textContent = name;
  const fd = new FormData();
  fd.append('action', 'get_permissions');
  fd.append('user_id', id);
  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (!d.success) { Toast.show(d.message || 'Failed.', 'error'); return; }
      const u = d.data;
      document.getElementById('pAppGateway').checked    = (u.apps || []).includes('gateway');
      document.getElementById('pAppDispatcher').checked = (u.apps || []).includes('dispatcher');
      applyPermsToMatrix(u, 'p');
      Modal.open('permModal');
    });
}

function savePermissions(btn) {
  const id   = document.getElementById('permUserId').value;
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  const fd = new FormData();
  fd.append('action', 'save_permissions');
  fd.append('user_id', id);
  if (document.getElementById('pAppGateway').checked)    fd.append('apps[]', 'gateway');
  if (document.getElementById('pAppDispatcher').checked) fd.append('apps[]', 'dispatcher');
  appendPermsToFd(fd, 'p');

  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.innerHTML = orig;
      Toast.show(d.message || (d.success ? 'Saved.' : 'Failed.'), d.success ? 'success' : 'error');
      if (d.success) { Modal.close('permModal'); setTimeout(() => location.reload(), 600); }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; Toast.show('Network error.', 'error'); });
}

// ── Reset password ────────────────────────────────────────────────────────────

function openResetPwModal(id, name) {
  document.getElementById('resetPwUserId').value       = id;
  document.getElementById('resetPwName').textContent   = name;
  document.getElementById('resetPwValue').value        = '';
  Modal.open('resetPwModal');
}

function doResetPassword(btn) {
  const id  = document.getElementById('resetPwUserId').value;
  const pw  = document.getElementById('resetPwValue').value;
  if (!pw || pw.length < 8) { Toast.show('Password must be at least 8 characters.', 'error'); return; }
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Resetting…';

  const fd = new FormData();
  fd.append('action', 'reset_password');
  fd.append('user_id', id);
  fd.append('password', pw);
  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.innerHTML = orig;
      Toast.show(d.message, d.success ? 'success' : 'error');
      if (d.success) Modal.close('resetPwModal');
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; Toast.show('Network error.', 'error'); });
}

// ── Toggle active ─────────────────────────────────────────────────────────────

function toggleUser(id, newActive) {
  const fd = new FormData();
  fd.append('action',    'toggle_user');
  fd.append('user_id',   id);
  fd.append('is_active', newActive ? 'true' : 'false');
  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      Toast.show(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 500);
    });
}

// ── Delete user ───────────────────────────────────────────────────────────────

function confirmDeleteUser(id, name) {
  document.getElementById('deleteUserId').value        = id;
  document.getElementById('deleteUserName').textContent = name;
  Modal.open('deleteUserModal');
}

function doDeleteUser(btn) {
  const id   = document.getElementById('deleteUserId').value;
  const orig = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = 'Deleting…';

  const fd = new FormData();
  fd.append('action',  'delete_user');
  fd.append('user_id', id);
  fetch('?page=admins', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.innerHTML = orig;
      Toast.show(d.message, d.success ? 'success' : 'error');
      if (d.success) { Modal.close('deleteUserModal'); setTimeout(() => location.reload(), 600); }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; Toast.show('Network error.', 'error'); });
}

// ── Permission matrix helpers ─────────────────────────────────────────────────

function clearPermMatrix(prefix) {
  document.querySelectorAll(`[id^="${prefix}Perm_"]`).forEach(cb => cb.checked = false);
  document.querySelectorAll(`[id^="${prefix}All_"]`).forEach(cb => cb.checked = false);
}

function applyPermsToMatrix(userData, prefix) {
  clearPermMatrix(prefix);
  const mods = userData.modules || [];
  mods.forEach(m => {
    const app    = m.application || m.app;
    const module = m.module_key  || m.module;
    if (!app || !module) return;
    const set = (action, val) => {
      const el = document.getElementById(`${prefix}Perm_${app}_${module}_${action}`);
      if (el) el.checked = !!val;
    };
    set('view',   m.can_view   ?? m.view);
    set('create', m.can_create ?? m.create);
    set('edit',   m.can_edit   ?? m.edit);
    set('delete', m.can_delete ?? m.delete);
  });
}

function appendPermsToFd(fd, prefix) {
  ['gateway','dispatcher'].forEach(app => {
    const modules = app === 'gateway' ? GW_MODULES : DP_MODULES;
    modules.forEach(mod => {
      ['view','create','edit','delete'].forEach(action => {
        const el = document.getElementById(`${prefix}Perm_${app}_${mod}_${action}`);
        if (el && el.checked) fd.append(`module_perms[${app}][${mod}][${action}]`, '1');
      });
    });
  });
}
</script>
