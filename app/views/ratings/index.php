<?php
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'drivers');
$tabs = ['drivers'=>'Driver Ratings','passengers'=>'Passenger Ratings','flagged'=>'Flagged Reviews'];
if (!isset($tabs[$tab])) $tab = 'drivers';
?>

<div class="page-header">
  <div>
    <h1>Ratings &amp; Reviews</h1>
    <p>Monitor driver and passenger ratings, and moderate reviews.</p>
  </div>
</div>

<div class="stats-grid">
  <?php foreach ([
    ['bi-star-fill',       'Avg Driver Rating',     '—', '#d97706'],
    ['bi-star-half',       'Avg Passenger Rating',  '—', '#F37A20'],
    ['bi-flag-fill',       'Flagged Reviews',       '—', '#dc2626'],
    ['bi-hand-thumbs-up',  'Total Reviews (MTD)',   '—', '#16a34a'],
  ] as [$i,$l,$v,$c]): ?>
  <div class="glass-card stat-card">
    <div class="stat-icon" style="background:<?=$c?>22;color:<?=$c?>"><i class="bi <?=$i?>"></i></div>
    <div class="stat-value"><?=$v?></div>
    <div class="stat-label"><?=$l?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px">
  <?php foreach ($tabs as $slug => $label): ?>
  <a href="?page=ratings&tab=<?=$slug?>"
     style="padding:8px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;text-decoration:none;border:1px solid <?=$tab===$slug?'var(--accent)':'var(--border)'?>;background:<?=$tab===$slug?'var(--accent-soft)':'#fff'?>;color:<?=$tab===$slug?'var(--accent)':'var(--text-muted)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'drivers'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-star-fill" style="color:#d97706"></i><div class="card-title">Driver Ratings Overview</div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <div class="glass-input-icon" style="width:200px">
        <i class="bi bi-search input-icon"></i><input class="glass-input" placeholder="Search driver...">
      </div>
      <select class="glass-select" style="width:130px"><option>All Ratings</option><option>5 stars</option><option>4 stars</option><option>3 stars or below</option></select>
    </div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Driver</th><th>Avg Rating</th><th>5★</th><th>4★</th><th>3★</th><th>2★</th><th>1★</th><th>Total Reviews</th><th>Recent Review</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="10"><div class="empty-state"><i class="bi bi-star"></i><h4>No driver ratings yet</h4><p>Ratings appear after passengers complete rides.</p></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'passengers'): ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-star-half" style="color:#F37A20"></i><div class="card-title">Passenger Ratings</div>
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Passenger</th><th>Avg Rating</th><th>Total Rated Rides</th><th>Latest Rating</th><th>Rated By</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-star"></i><h4>No passenger ratings yet</h4></div></td></tr></tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="glass-card">
  <div class="card-header-bar">
    <i class="bi bi-flag-fill" style="color:#dc2626"></i><div class="card-title">Flagged &amp; Inappropriate Reviews</div>
  </div>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:var(--radius-sm);margin:16px 20px;padding:12px 16px;font-size:13px;color:#dc2626;display:flex;align-items:center;gap:8px">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Reviews flagged as inappropriate, offensive, or spam. Review and take action.
  </div>
  <div class="table-wrap">
    <table class="glass-table">
      <thead><tr><th>Review</th><th>By</th><th>About</th><th>Rating</th><th>Flag Reason</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody><tr><td colspan="7"><div class="empty-state"><i class="bi bi-flag"></i><h4>No flagged reviews</h4><p>Reviews reported as inappropriate will appear here.</p></div></td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>
