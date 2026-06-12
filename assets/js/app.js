/* PowerCabs Admin — Global JS */

/* ─── Sidebar Toggle ─────────────────────────────────────────── */
(function () {
  const sidebar = document.querySelector('.sidebar');
  const toggleBtn = document.querySelector('.sidebar-toggle');

  if (!sidebar || !toggleBtn) return;

  const COLLAPSED_KEY = 'pc_sidebar_collapsed';

  // Restore state
  if (localStorage.getItem(COLLAPSED_KEY) === '1') {
    sidebar.classList.add('collapsed');
    document.body.classList.add('sidebar-collapsed');
  }

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem(COLLAPSED_KEY, isCollapsed ? '1' : '0');
    toggleBtn.querySelector('i').className = isCollapsed
      ? 'bi bi-layout-sidebar-inset-reverse'
      : 'bi bi-layout-sidebar-inset';
  });

  // Mobile overlay close
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768
      && sidebar.classList.contains('mobile-open')
      && !sidebar.contains(e.target)
      && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('mobile-open');
    }
  });

  // On mobile, toggle shows/hides rather than collapses
  if (window.innerWidth <= 768) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
    });
  }
})();

/* ─── Modal Helpers ──────────────────────────────────────────── */
const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  },
  close(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  },
  init() {
    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('open');
      });
    });
    // Close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.closest('.modal-overlay')?.classList.remove('open');
      });
    });
    // Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });
  }
};

/* ─── Toast Notifications ────────────────────────────────────── */
const Toast = {
  container: null,

  init() {
    this.container = document.querySelector('.toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'info', duration = 3500) {
    if (!this.container) this.init();

    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    const colors = { success: '#16a34a', error: '#dc2626', info: '#F37A20' };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <i class="bi ${icons[type] || icons.info}" style="color:${colors[type] || colors.info};font-size:16px;flex-shrink:0"></i>
      <span style="flex:1">${message}</span>
      <i class="bi bi-x" style="cursor:pointer;font-size:16px;color:var(--text-subtle,#A0AEC0)" onclick="this.closest('.toast').remove()"></i>
    `;

    this.container.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideIn 0.3s ease reverse';
      setTimeout(() => toast.remove(), 280);
    }, duration);
  }
};

/* ─── AJAX Fetch Helper ──────────────────────────────────────── */
async function apiFetch(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...options
    });
    const data = await res.json();
    return data;
  } catch (err) {
    Toast.show('Network error. Please try again.', 'error');
    return { success: false, message: 'Network error' };
  }
}

/* ─── Table Search Filter ────────────────────────────────────── */
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ─── Confirm Dialog ─────────────────────────────────────────── */
function confirmAction(message, onConfirm) {
  if (confirm(message)) onConfirm();
}

/* ─── Format Helpers ─────────────────────────────────────────── */
const fmt = {
  currency: (n) => '€' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','),
  number:   (n) => parseInt(n || 0).toLocaleString(),
  date:     (d) => d ? new Date(d).toLocaleDateString('en-IE', { day: '2-digit', month: 'short', year: 'numeric' }) : '—',
  datetime: (d) => d ? new Date(d).toLocaleString('en-IE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—',
  initials: (name) => name ? name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2) : '?',
};

/* ─── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  Modal.init();
  Toast.init();

  // Auto-dismiss flash messages
  const flash = document.querySelector('.flash-message');
  if (flash) setTimeout(() => flash.remove(), 4000);

  // Tooltips via title attr (Bootstrap)
  if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  }
});
