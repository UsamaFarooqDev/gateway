<div class="page-header">
  <div>
    <h1>Dispatcher Console</h1>
    <p>Live map, manual bookings, and real-time driver assignment.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-glass"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    <button class="btn-primary-glass" onclick="Toast.show('Manual booking form coming soon.','info')"><i class="bi bi-plus-lg"></i> Manual Booking</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

  <!-- Live Map -->
  <div class="glass-card" style="overflow:hidden">
    <div class="card-header-bar">
      <i class="bi bi-map" style="color:var(--accent)"></i>
      <div class="card-title">Live Driver Map</div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="badge-pill badge-online"><span class="dot"></span>0 Online</span>
      </div>
    </div>
    <div style="background:#E8ECF0;height:480px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px">
      <i class="bi bi-map" style="font-size:56px;color:#A0AEC0"></i>
      <div style="font-weight:600;color:var(--text-muted)">Google Maps Integration</div>
      <div style="font-size:12px;color:var(--text-subtle)">Live driver pins will appear here once Google Maps JS API is wired up.</div>
      <button class="btn-primary-glass mt-2" onclick="Toast.show('Maps API key required in settings.','info')">
        <i class="bi bi-geo-alt"></i> Enable Live Map
      </button>
    </div>
  </div>

  <!-- Right panel -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Online drivers -->
    <div class="glass-card">
      <div class="card-header-bar">
        <i class="bi bi-broadcast" style="color:#16a34a"></i>
        <div class="card-title">Online Drivers</div>
        <span class="badge-pill badge-active" style="margin-left:auto">0 Active</span>
      </div>
      <div class="empty-state" style="padding:32px 16px">
        <i class="bi bi-broadcast" style="font-size:32px"></i>
        <h4 style="font-size:14px;margin-top:8px">No drivers online</h4>
        <p style="font-size:12px">Online drivers will appear here in real time.</p>
      </div>
    </div>

    <!-- Pending bookings -->
    <div class="glass-card">
      <div class="card-header-bar">
        <i class="bi bi-hourglass-split" style="color:#d97706"></i>
        <div class="card-title">Pending Bookings</div>
        <span class="badge-pill badge-pending" style="margin-left:auto">0 Waiting</span>
      </div>
      <div class="empty-state" style="padding:32px 16px">
        <i class="bi bi-calendar-plus" style="font-size:32px"></i>
        <h4 style="font-size:14px;margin-top:8px">No pending bookings</h4>
        <p style="font-size:12px">Unassigned rides will appear here.</p>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="glass-card" style="padding:16px">
      <p class="section-title" style="margin-top:0">Quick Actions</p>
      <div style="display:flex;flex-direction:column;gap:8px">
        <button class="btn-glass w-100" style="justify-content:flex-start" onclick="Toast.show('Coming soon.','info')">
          <i class="bi bi-telephone" style="color:var(--accent)"></i> Call Driver
        </button>
        <button class="btn-glass w-100" style="justify-content:flex-start" onclick="Toast.show('Coming soon.','info')">
          <i class="bi bi-chat-dots" style="color:var(--accent)"></i> Message Driver
        </button>
        <button class="btn-glass w-100" style="justify-content:flex-start" onclick="Toast.show('Coming soon.','info')">
          <i class="bi bi-person-check" style="color:var(--accent)"></i> Assign / Reassign
        </button>
      </div>
    </div>

  </div>
</div>
