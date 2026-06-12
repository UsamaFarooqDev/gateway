<?php
$activeTab = $tab ?? 'types';

function complianceDot(string $url, string $label, string $expiry = ''): string {
    if ($label === 'Licence') {
        if (empty($expiry)) return '<span title="Not set" style="color:#94a3b8">—</span>';
        $days = (int)round((strtotime(substr($expiry,0,10)) - time()) / 86400);
        if ($days < 0)   return "<span title='EXPIRED' style='color:#dc2626;font-weight:700'>EXP</span>";
        if ($days < 30)  return "<span title='Expires in {$days} days' style='color:#d97706;font-weight:600'>{$days}d</span>";
        return "<span title='" . date('d M Y', strtotime($expiry)) . "' style='color:#16a34a'>✓</span>";
    }
    if (!empty($url)) return "<span title='Uploaded' style='color:#16a34a'>✓</span>";
    return "<span title='Missing' style='color:#dc2626'>✗</span>";
}

function overallStatus(array $d): array {
    $docs    = [$d['license_url'], $d['vehicle_reg_url'], $d['insurance_url'], $d['nct_cert'], $d['rt_cert'], $d['suitability_cert']];
    $missing = count(array_filter($docs, fn($v) => empty($v)));
    $licOk   = true;
    if (!empty($d['license_expiry'])) {
        $days  = (int)round((strtotime(substr($d['license_expiry'],0,10)) - time()) / 86400);
        $licOk = $days >= 0;
    }
    if ($missing === 0 && $licOk)  return ['Compliant',    '#16a34a', '#DCFCE7'];
    if ($missing >= 3 || !$licOk)  return ['Non-Compliant','#dc2626', '#FEE2E2'];
    return ['Incomplete', '#d97706', '#FEF3C7'];
}
?>

<!-- Page header -->
<div class="page-header">
  <div>
    <h1>Fleet Management</h1>
    <p>Manage vehicle types, driver fleet, and document compliance.</p>
  </div>
  <?php if ($activeTab === 'types'): ?>
  <button class="btn-primary-glass" onclick="openTypeModal()">
    <i class="bi bi-plus-lg"></i> Add Vehicle Type
  </button>
  <?php endif; ?>
</div>

<!-- Stats strip -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <?php foreach ([
      ['bi-car-front',    'Vehicle Types',  $stats['total_types'],   '#F37A20'],
      ['bi-check-circle', 'Active Types',   $stats['active_types'],  '#16a34a'],
      ['bi-people',       'Total Drivers',  $stats['total_drivers'], '#6366f1'],
      ['bi-broadcast',    'Active Drivers', $stats['active_drivers'],'#16a34a'],
  ] as [$icon, $label, $count, $color]): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);min-width:140px;box-shadow:var(--shadow-sm)">
    <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:20px"></i>
    <div>
      <div style="font-size:18px;font-weight:700;color:var(--text-primary);line-height:1"><?= number_format($count) ?></div>
      <div style="font-size:11px;color:var(--text-muted)"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tab nav -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:rgba(255,255,255,0.04);padding:4px;border-radius:10px;width:fit-content;border:1px solid var(--border)">
  <?php foreach (['types' => 'Vehicle Types', 'fleet' => 'Fleet Overview', 'compliance' => 'Compliance'] as $slug => $tlabel): ?>
  <a href="?page=fleet&tab=<?= $slug ?>"
     style="padding:8px 20px;border-radius:7px;font-size:13px;font-weight:500;text-decoration:none;transition:all .2s;
            <?= $activeTab === $slug ? 'background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(243,122,32,.3)' : 'color:var(--text-muted)' ?>">
    <?= $tlabel ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($activeTab === 'types'): /* ═══════ TAB 1: VEHICLE TYPES ═══════ */ ?>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-car-front-fill" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Vehicle Types</div>
      <div class="card-subtitle"><?= count($rideTypes) ?> type<?= count($rideTypes)!==1?'s':'' ?> configured</div>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($rideTypes)): ?>
    <div class="empty-state">
      <i class="bi bi-car-front"></i><h4>No vehicle types yet</h4>
      <p>Add your first vehicle type to get started.</p>
      <button class="btn-primary-glass" onclick="openTypeModal()"><i class="bi bi-plus-lg"></i> Add Vehicle Type</button>
    </div>
    <?php else: ?>
    <table class="glass-table">
      <thead>
        <tr>
          <th style="width:40px">Order</th>
          <th>Name</th>
          <th>Description</th>
          <th>Seats</th>
          <th>Multiplier</th>
          <th>Wait</th>
          <th>Note</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rideTypes as $rt): ?>
        <tr id="rt-row-<?= htmlspecialchars($rt['id']) ?>">
          <td class="text-muted fs-12" style="text-align:center"><?= (int)($rt['sort_order']??0) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if (!empty($rt['image_url'])): ?>
                <img src="<?= htmlspecialchars($rt['image_url']) ?>"
                     alt="<?= htmlspecialchars($rt['name']) ?>"
                     style="width:72px;height:52px;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:#f1f5f9;flex-shrink:0"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div style="display:none;width:72px;height:52px;border-radius:8px;background:rgba(243,122,32,0.08);border:1px solid var(--border);align-items:center;justify-content:center;flex-shrink:0;font-size:24px">
                  <?= !empty($rt['icon_emoji']) ? htmlspecialchars($rt['icon_emoji']) : '<i class="bi bi-car-front" style="color:var(--accent)"></i>' ?>
                </div>
              <?php elseif (!empty($rt['icon_emoji'])): ?>
                <div style="width:72px;height:52px;border-radius:8px;background:rgba(243,122,32,0.08);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:28px">
                  <?= htmlspecialchars($rt['icon_emoji']) ?>
                </div>
              <?php else: ?>
                <div style="width:72px;height:52px;border-radius:8px;background:rgba(243,122,32,0.08);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <i class="bi bi-image" style="color:var(--text-subtle);font-size:22px"></i>
                </div>
              <?php endif; ?>
              <div>
                <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($rt['name']) ?></div>
                <?php if (!empty($rt['note_hint'])): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($rt['note_hint']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="max-width:200px">
            <span style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">
              <?= htmlspecialchars($rt['description']??'—') ?>
            </span>
          </td>
          <td style="font-weight:500"><?= (int)($rt['seats']??4) ?></td>
          <td>
            <span style="font-weight:600;color:<?= ($rt['multiplier']??1)>1?'var(--accent)':'var(--text-primary)' ?>">
              <?= number_format((float)($rt['multiplier']??1),2) ?>×
            </span>
          </td>
          <td class="text-muted fs-12"><?= (int)($rt['waiting_minutes']??3) ?> min</td>
          <td>
            <?php if ($rt['requires_note']??false): ?>
            <span style="font-size:11.5px;color:#6366f1"><i class="bi bi-chat-dots"></i> Required</span>
            <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <label class="rt-switch">
                <input type="checkbox" class="rt-toggle" data-id="<?= htmlspecialchars($rt['id']) ?>"
                  <?= ($rt['is_active']??true)?'checked':'' ?>>
                <span class="rt-slider"></span>
              </label>
              <span id="rt-status-<?= htmlspecialchars($rt['id']) ?>" style="font-size:12px;min-width:44px">
                <?= ($rt['is_active']??true)
                    ? "<span style='color:#16a34a;font-weight:600'>Active</span>"
                    : "<span style='color:#94a3b8'>Inactive</span>" ?>
              </span>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="Edit" onclick="openTypeModal(<?= htmlspecialchars(json_encode($rt['id'])) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn-icon danger" title="Delete" onclick="deleteType(<?= htmlspecialchars(json_encode($rt['id'])) ?>,<?= htmlspecialchars(json_encode($rt['name'])) ?>)">
                <i class="bi bi-trash3"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Add / Edit Vehicle Type Modal -->
<div class="modal-overlay" id="typeModal">
  <div class="modal-box" style="max-width:580px">
    <div class="modal-header">
      <i class="bi bi-car-front-fill" style="color:var(--accent);font-size:20px"></i>
      <span class="modal-title" id="typeModalTitle">Add Vehicle Type</span>
      <button class="modal-close" onclick="Modal.close('typeModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="typeId">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="grid-column:1/-1">
          <label class="form-label">Name <span style="color:#dc2626">*</span></label>
          <input type="text" id="tName" class="glass-input" placeholder="e.g. Economy" maxlength="50">
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">Description</label>
          <input type="text" id="tDesc" class="glass-input" placeholder="Short description shown to passengers">
        </div>
        <div>
          <label class="form-label">Icon Emoji</label>
          <input type="text" id="tEmoji" class="glass-input" placeholder="🚗" maxlength="8">
        </div>
        <div>
          <label class="form-label">Image URL</label>
          <input type="url" id="tImageUrl" class="glass-input" placeholder="https://..." oninput="previewTypeImg(this.value)">
          <img id="tImgPreview" class="rt-img-preview" alt="Preview">
        </div>
        <div>
          <label class="form-label">Seats</label>
          <input type="number" id="tSeats" class="glass-input" value="4" min="1" max="30">
        </div>
        <div>
          <label class="form-label">Fare Multiplier</label>
          <input type="number" id="tMultiplier" class="glass-input" value="1.00" min="0.1" step="0.01">
        </div>
        <div>
          <label class="form-label">Free Waiting (minutes)</label>
          <input type="number" id="tWaiting" class="glass-input" value="3" min="0" max="60">
        </div>
        <div>
          <label class="form-label">Sort Order</label>
          <input type="number" id="tSortOrder" class="glass-input" value="0" min="0">
        </div>
        <div style="grid-column:1/-1;display:flex;gap:24px;align-items:center">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" id="tActive" checked style="accent-color:var(--accent);width:15px;height:15px"> Active
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" id="tRequiresNote" style="accent-color:var(--accent);width:15px;height:15px"> Requires passenger note
          </label>
        </div>
        <div style="grid-column:1/-1" id="tNoteHintWrap" class="d-none">
          <label class="form-label">Note hint (shown to passenger)</label>
          <input type="text" id="tNoteHint" class="glass-input" placeholder="e.g. Please specify number of luggage bags">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('typeModal')"><i class="bi bi-x"></i> Cancel</button>
      <button class="btn-primary-glass" id="typeSaveBtn" onclick="saveType()"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<?php elseif ($activeTab === 'fleet'): /* ═══════ TAB 2: FLEET OVERVIEW ═══════ */ ?>

<div class="glass-card mb-3">
  <form method="GET" action="">
    <input type="hidden" name="page" value="fleet">
    <input type="hidden" name="tab"  value="fleet">
    <div class="filter-bar" style="padding:14px 18px">
      <div class="glass-input-icon" style="flex:1;min-width:200px">
        <i class="bi bi-search input-icon"></i>
        <input type="text" name="search" class="glass-input" placeholder="Search name or plate…"
               value="<?= htmlspecialchars($fleetFilters['search']) ?>">
      </div>
      <select name="fstatus" class="glass-select" style="width:150px" onchange="this.form.submit()">
        <option value="all"       <?= $fleetFilters['status']==='all'       ?'selected':'' ?>>All Status</option>
        <option value="active"    <?= $fleetFilters['status']==='active'    ?'selected':'' ?>>Active</option>
        <option value="pending"   <?= $fleetFilters['status']==='pending'   ?'selected':'' ?>>Pending</option>
        <option value="suspended" <?= $fleetFilters['status']==='suspended' ?'selected':'' ?>>Suspended</option>
      </select>
      <button type="submit" class="btn-primary-glass"><i class="bi bi-search"></i> Search</button>
    </div>
  </form>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-truck-front-fill" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Driver Vehicles</div>
      <div class="card-subtitle"><?= number_format($fleetTotal) ?> driver<?= $fleetTotal!==1?'s':'' ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($fleet)): ?>
    <div class="empty-state"><i class="bi bi-truck-front"></i><h4>No drivers found</h4></div>
    <?php else: ?>
    <table class="glass-table">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Vehicle</th>
          <th>Plate</th>
          <th>Seats</th>
          <th>Assigned Types</th>
          <th>Rides</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fleet as $d):
          $name     = trim($d['full_name']??'');
          $parts    = array_filter(explode(' ', $name));
          $initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_slice($parts,0,2))))?:'?';
          $vehicle  = trim(($d['vehicle_make']??'').' '.($d['vehicle_model']??''));
          $status   = $d['status']??'inactive';
          [$sBg,$sColor,$sLabel] = match($status) {
              'active','approved' => ['#DCFCE7','#16a34a','Active'],
              'pending'           => ['#FEF3C7','#d97706','Pending'],
              'suspended'         => ['#FEE2E2','#dc2626','Suspended'],
              default             => ['#F1F5F9','#94a3b8',ucfirst($status)],
          };
          $rawType    = $d['type']??null;
          $typeArr    = is_string($rawType)?(json_decode($rawType,true)??[]):($rawType??[]);
          $typeLabels = array_map(function($t) {
              return is_array($t)?($t['label']??ucwords(str_replace('_',' ',$t['type']??''))):ucwords(str_replace('_',' ',(string)$t));
          }, $typeArr);
        ?>
        <tr>
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm">
                <?= !empty($d['profile_pic_url'])
                    ? '<img src="'.htmlspecialchars($d['profile_pic_url']).'" alt="">'
                    : htmlspecialchars($initials) ?>
              </div>
              <div class="user-cell-info">
                <div class="name"><?= htmlspecialchars($name?:'—') ?></div>
                <div class="meta"><?= htmlspecialchars(substr($d['id'],0,8)) ?>…</div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;font-weight:500"><?= htmlspecialchars($vehicle?:'—') ?></td>
          <td>
            <?php if (!empty($d['plate_no'])): ?>
            <span style="font-family:monospace;font-size:12.5px;background:rgba(255,255,255,0.06);padding:3px 8px;border-radius:5px;border:1px solid var(--border)">
              <?= htmlspecialchars($d['plate_no']) ?>
            </span>
            <?php else: ?><span class="text-muted fs-12">—</span><?php endif; ?>
          </td>
          <td style="font-size:13px"><?= (int)($d['no_seats']??0)?:'—' ?></td>
          <td>
            <?php if (empty($typeLabels)): ?>
            <span class="text-muted fs-12">None assigned</span>
            <?php else: ?>
              <?php foreach ($typeLabels as $tl): ?>
              <span class="badge-pill" style="background:#EDE9FE;color:#7c3aed;font-size:10.5px;margin-right:3px"><?= htmlspecialchars($tl) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td style="font-weight:500"><?= number_format((int)($d['total_rides']??0)) ?></td>
          <td>
            <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:99px;background:<?= $sBg ?>;color:<?= $sColor ?>">
              <?= $sLabel ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php
  $fleetPages = (int)ceil($fleetTotal / $fleetPer);
  if ($fleetPages > 1): ?>
  <div class="pagination-bar">
    <span>Showing <?= ($fleetPage-1)*$fleetPer+1 ?>–<?= min($fleetPage*$fleetPer,$fleetTotal) ?> of <?= number_format($fleetTotal) ?></span>
    <div class="pagination-controls">
      <?php if ($fleetPage>1): ?>
      <a href="?page=fleet&tab=fleet&search=<?= urlencode($fleetFilters['search']) ?>&fstatus=<?= urlencode($fleetFilters['status']) ?>&fp=<?= $fleetPage-1 ?>" class="page-btn"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i=max(1,$fleetPage-2);$i<=min($fleetPages,$fleetPage+2);$i++): ?>
      <a href="?page=fleet&tab=fleet&search=<?= urlencode($fleetFilters['search']) ?>&fstatus=<?= urlencode($fleetFilters['status']) ?>&fp=<?= $i ?>"
         class="page-btn <?= $i===$fleetPage?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($fleetPage<$fleetPages): ?>
      <a href="?page=fleet&tab=fleet&search=<?= urlencode($fleetFilters['search']) ?>&fstatus=<?= urlencode($fleetFilters['status']) ?>&fp=<?= $fleetPage+1 ?>" class="page-btn"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($activeTab === 'compliance'): /* ═══════ TAB 3: COMPLIANCE ═══════ */ ?>

<?php
$cCompliant=0;$cIncomplete=0;$cNonCompliant=0;
foreach ($compliance as $cd) {
    [$cs]=overallStatus($cd);
    if ($cs==='Compliant') $cCompliant++;
    elseif ($cs==='Incomplete') $cIncomplete++;
    else $cNonCompliant++;
}
?>

<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ([
      ['bi-shield-check',  'Compliant',     $cCompliant,    '#16a34a','#DCFCE7'],
      ['bi-exclamation-triangle','Incomplete',$cIncomplete,  '#d97706','#FEF3C7'],
      ['bi-shield-x',     'Non-Compliant', $cNonCompliant, '#dc2626','#FEE2E2'],
  ] as [$icon,$label,$count,$color,$bg]): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:<?= $bg ?>;border:1px solid <?= $color ?>33;border-radius:var(--radius-sm);min-width:160px">
    <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:22px"></i>
    <div>
      <div style="font-size:20px;font-weight:700;color:<?= $color ?>;line-height:1"><?= $count ?></div>
      <div style="font-size:11px;color:<?= $color ?>"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-shield-check" style="color:var(--accent);font-size:18px"></i>
    <div>
      <div class="card-title">Document Compliance</div>
      <div class="card-subtitle"><?= count($compliance) ?> drivers</div>
    </div>
    <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
      <span style="font-size:11.5px;padding:3px 10px;border-radius:99px;background:#DCFCE7;color:#16a34a">✓ OK</span>
      <span style="font-size:11.5px;padding:3px 10px;border-radius:99px;background:#FEF3C7;color:#d97706">⚠ Expiring</span>
      <span style="font-size:11.5px;padding:3px 10px;border-radius:99px;background:#FEE2E2;color:#dc2626">✗ Missing/Expired</span>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($compliance)): ?>
    <div class="empty-state"><i class="bi bi-shield"></i><h4>No drivers found</h4></div>
    <?php else: ?>
    <table class="glass-table" style="font-size:12.5px">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Status</th>
          <th style="text-align:center">Licence</th>
          <th style="text-align:center">Vehicle Reg</th>
          <th style="text-align:center">Insurance</th>
          <th style="text-align:center">NCT</th>
          <th style="text-align:center">Road Tax</th>
          <th style="text-align:center">Suitability</th>
          <th>Overall</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($compliance as $cd):
          $cName    = trim($cd['full_name']??'');
          $cParts   = array_filter(explode(' ', $cName));
          $cInit    = strtoupper(implode('', array_map(fn($p)=>$p[0], array_slice($cParts,0,2))))?:'?';
          [$cOver,$cOColor,$cOBg] = overallStatus($cd);
          $cStatus  = $cd['status']??'';
          [$csBg,$csColor] = match($cStatus){
              'active','approved'=>['#DCFCE7','#16a34a'],
              'pending'=>['#FEF3C7','#d97706'],
              default =>['#F1F5F9','#94a3b8']
          };
        ?>
        <tr>
          <td>
            <div class="user-cell">
              <div class="user-avatar-sm" style="width:32px;height:32px;font-size:11px;flex-shrink:0"><?= htmlspecialchars($cInit) ?></div>
              <div class="user-cell-info">
                <div class="name" style="font-size:12.5px"><?= htmlspecialchars($cName?:'—') ?></div>
                <div class="meta"><?= htmlspecialchars($cd['email']??'') ?></div>
              </div>
            </div>
          </td>
          <td>
            <span style="font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:99px;background:<?= $csBg ?>;color:<?= $csColor ?>">
              <?= ucfirst($cStatus?:'—') ?>
            </span>
          </td>
          <td style="text-align:center"><?= complianceDot($cd['license_url']??'',     'Licence',  $cd['license_expiry']??'') ?></td>
          <td style="text-align:center"><?= complianceDot($cd['vehicle_reg_url']??'', 'Reg') ?></td>
          <td style="text-align:center"><?= complianceDot($cd['insurance_url']??'',   'Ins') ?></td>
          <td style="text-align:center"><?= complianceDot($cd['nct_cert']??'',        'NCT') ?></td>
          <td style="text-align:center"><?= complianceDot($cd['rt_cert']??'',         'RT') ?></td>
          <td style="text-align:center"><?= complianceDot($cd['suitability_cert']??'','Suit') ?></td>
          <td>
            <span style="font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:99px;background:<?= $cOBg ?>;color:<?= $cOColor ?>">
              <?= $cOver ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php $extraScripts = <<<'SCRIPTS'
<style>
.rt-switch { position:relative; display:inline-block; width:46px; height:26px; cursor:pointer; flex-shrink:0 }
.rt-switch input { opacity:0; width:0; height:0; position:absolute }
.rt-slider { position:absolute; inset:0; background:#d1d5db; border-radius:99px; transition:.25s }
.rt-slider::before { content:''; position:absolute; width:20px; height:20px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 4px rgba(0,0,0,.25) }
.rt-switch input:checked + .rt-slider { background:var(--accent) }
.rt-switch input:checked + .rt-slider::before { transform:translateX(20px) }
.rt-img-preview { width:100%; height:120px; border-radius:8px; object-fit:cover; border:1px solid var(--border); margin-top:8px; display:none }
</style>
<script>
function previewTypeImg(url) {
  const img = document.getElementById('tImgPreview');
  if (!url) { img.style.display='none'; img.src=''; return; }
  img.src = url;
  img.style.display = 'block';
  img.onerror = () => { img.style.display='none'; };
}

function openTypeModal(id) {
  document.getElementById('typeModalTitle').textContent = id ? 'Edit Vehicle Type' : 'Add Vehicle Type';
  ['typeId','tName','tDesc','tEmoji','tImageUrl','tNoteHint'].forEach(el => document.getElementById(el).value = '');
  previewTypeImg('');
  document.getElementById('tSeats').value      = '4';
  document.getElementById('tMultiplier').value = '1.00';
  document.getElementById('tWaiting').value    = '3';
  document.getElementById('tSortOrder').value  = '0';
  document.getElementById('tActive').checked      = true;
  document.getElementById('tRequiresNote').checked = false;
  document.getElementById('tNoteHintWrap').classList.add('d-none');

  if (id) {
    document.getElementById('typeId').value = id;
    const fd = new FormData();
    fd.append('action','get_type'); fd.append('id',id);
    fetch(window.location.href,{method:'POST',body:fd})
      .then(r=>r.json()).then(res=>{
        if (!res.success) { Toast.show('Could not load type.','error'); return; }
        const d = res.data;
        document.getElementById('tName').value        = d.name        || '';
        document.getElementById('tDesc').value        = d.description || '';
        document.getElementById('tEmoji').value       = d.icon_emoji  || '';
        document.getElementById('tImageUrl').value    = d.image_url   || '';
        previewTypeImg(d.image_url || '');
        document.getElementById('tSeats').value       = d.seats       || 4;
        document.getElementById('tMultiplier').value  = parseFloat(d.multiplier||1).toFixed(2);
        document.getElementById('tWaiting').value     = d.waiting_minutes ?? 3;
        document.getElementById('tSortOrder').value   = d.sort_order  || 0;
        document.getElementById('tActive').checked       = !!d.is_active;
        document.getElementById('tRequiresNote').checked = !!d.requires_note;
        document.getElementById('tNoteHint').value    = d.note_hint || '';
        if (d.requires_note) document.getElementById('tNoteHintWrap').classList.remove('d-none');
        Modal.open('typeModal');
      });
  } else {
    Modal.open('typeModal');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const rn = document.getElementById('tRequiresNote');
  if (rn) rn.addEventListener('change', function() {
    document.getElementById('tNoteHintWrap').classList.toggle('d-none', !this.checked);
  });

  document.querySelectorAll('.rt-toggle').forEach(cb => {
    cb.addEventListener('change', async function() {
      const id = this.dataset.id, active = this.checked;
      const fd = new FormData();
      fd.append('action','toggle_type'); fd.append('id',id); fd.append('active',active?'1':'0');
      try {
        const res  = await fetch(window.location.href,{method:'POST',body:fd});
        const data = await res.json();
        if (data.success) {
          const lbl = document.getElementById('rt-status-'+id);
          if (lbl) lbl.innerHTML = active
            ? "<span style='color:#16a34a'>Active</span>"
            : "<span style='color:#94a3b8'>Inactive</span>";
          Toast.show('Status updated.','success');
        } else { this.checked=!active; Toast.show(data.message||'Failed.','error'); }
      } catch { this.checked=!active; Toast.show('Network error.','error'); }
    });
  });
});

async function saveType() {
  const name = document.getElementById('tName').value.trim();
  if (!name) { Toast.show('Name is required.','error'); return; }
  const id  = document.getElementById('typeId').value;
  const btn = document.getElementById('typeSaveBtn');
  const orig = btn.innerHTML;
  btn.disabled=true; btn.innerHTML='<i class="bi bi-arrow-repeat"></i> Saving…';
  const fd = new FormData();
  fd.append('action',          id?'update_type':'create_type');
  if (id) fd.append('id',id);
  fd.append('name',            name);
  fd.append('description',     document.getElementById('tDesc').value.trim());
  fd.append('icon_emoji',      document.getElementById('tEmoji').value.trim());
  fd.append('image_url',       document.getElementById('tImageUrl').value.trim());
  fd.append('seats',           document.getElementById('tSeats').value);
  fd.append('multiplier',      document.getElementById('tMultiplier').value);
  fd.append('waiting_minutes', document.getElementById('tWaiting').value);
  fd.append('sort_order',      document.getElementById('tSortOrder').value);
  fd.append('is_active',       document.getElementById('tActive').checked?'1':'0');
  fd.append('requires_note',   document.getElementById('tRequiresNote').checked?'1':'0');
  fd.append('note_hint',       document.getElementById('tNoteHint').value.trim());
  try {
    const res=await fetch(window.location.href,{method:'POST',body:fd});
    const data=await res.json();
    if (data.success) { Toast.show(data.message,'success'); Modal.close('typeModal'); setTimeout(()=>location.reload(),700); }
    else Toast.show(data.message||'Save failed.','error');
  } catch { Toast.show('Network error.','error'); }
  finally { btn.disabled=false; btn.innerHTML=orig; }
}

async function deleteType(id,name) {
  if (!confirm(`Delete vehicle type "${name}"? This cannot be undone.`)) return;
  const fd=new FormData(); fd.append('action','delete_type'); fd.append('id',id);
  try {
    const res=await fetch(window.location.href,{method:'POST',body:fd});
    const data=await res.json();
    if (data.success) { Toast.show(data.message,'success'); const r=document.getElementById('rt-row-'+id); if(r)r.remove(); }
    else Toast.show(data.message||'Delete failed.','error');
  } catch { Toast.show('Network error.','error'); }
}
</script>
SCRIPTS;
?>
