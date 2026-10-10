    </div><!-- /.content-inner -->
  </main>
</div>

<!-- Global Toast Container -->
<div id="toastContainer" class="toast-container-custom"></div>

<script>
window.showToast = function(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  
  const toast = document.createElement('div');
  toast.className = `toast-custom toast-${type}`;
  
  let iconSvg = '';
  if (type === 'success') {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  } else if (type === 'danger') {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  } else {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }
  
  const iconDiv = document.createElement('div');
  iconDiv.className = 'toast-icon';
  iconDiv.innerHTML = iconSvg;

  const msgDiv = document.createElement('div');
  msgDiv.className = 'toast-message';
  msgDiv.textContent = message;

  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'toast-close';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

  toast.appendChild(iconDiv);
  toast.appendChild(msgDiv);
  toast.appendChild(closeBtn);
  
  closeBtn.addEventListener('click', () => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px) scale(0.95)';
    setTimeout(() => toast.remove(), 200);
  });
  
  container.appendChild(toast);
  
  setTimeout(() => {
    if (toast.parentNode) {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px) scale(0.95)';
      setTimeout(() => toast.remove(), 200);
    }
  }, 4200);
};

// Check for toast in URL params or session flash
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const toastParam = urlParams.get('toast');
  if (toastParam) {
    if (toastParam === 'deleted') {
      window.showToast('Exam deleted', 'danger');
    } else if (toastParam === 'updated' || toastParam === 'saved') {
      window.showToast('Changes saved', 'success');
    } else if (toastParam === 'created') {
      window.showToast('Exam created', 'success');
    } else if (toastParam === 'generated') {
      window.showToast('Seating generated', 'success');
    } else {
      const decoded = decodeURIComponent(toastParam);
      const isSuccess = /saved|success|added|updated|created/i.test(decoded);
      const isDanger = /delete|error|fail|invalid/i.test(decoded);
      window.showToast(decoded, isDanger ? 'danger' : (isSuccess ? 'success' : 'info'));
    }
    // Clean URL without reloading
    const cleanUrl = window.location.pathname + (window.location.search.replace(/[?&]toast=[^&]+/, '').replace(/^&/, '?'));
    window.history.replaceState({}, document.title, cleanUrl);
  }
});
</script>
</body>
</html>