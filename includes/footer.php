  </main><!-- /content-area -->
</div><!-- /page-body -->
</div><!-- /admin-shell -->

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- App JS -->
<script src="assets/js/app.js"></script>

<script>
// Fullscreen toggle
document.getElementById('fullscreenBtn')?.addEventListener('click', () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
    document.querySelector('#fullscreenBtn i').className = 'bi bi-fullscreen-exit';
  } else {
    document.exitFullscreen();
    document.querySelector('#fullscreenBtn i').className = 'bi bi-fullscreen';
  }
});

// Update sidebar header position on toggle
(function(){
  const header = document.getElementById('topHeader');
  const sidebar = document.getElementById('sidebar');
  if(!header || !sidebar) return;
  const obs = new MutationObserver(() => {
    const collapsed = sidebar.classList.contains('collapsed');
    header.style.left = collapsed ? '72px' : 'var(--sidebar-w)';
  });
  obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
})();
</script>

<?php if (isset($extraScripts)): ?>
  <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
