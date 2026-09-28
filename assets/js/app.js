/**
 * Application JavaScript Interactivity
 * Personal Expense Tracker
 */

document.addEventListener('DOMContentLoaded', function () {

  // Dark Mode Toggle Logic
  const themeToggleBtn = document.getElementById('theme-toggle-btn');
  const themeIcon = document.getElementById('theme-toggle-icon');
  
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('theme', theme);
    if (themeIcon) {
      if (theme === 'dark') {
        themeIcon.className = 'bi bi-sun-fill text-warning';
      } else {
        themeIcon.className = 'bi bi-moon-stars-fill text-secondary';
      }
    }
  }

  // Load initial theme from localStorage or system setting
  const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(savedTheme);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', function () {
      const currentTheme = document.documentElement.getAttribute('data-bs-theme');
      const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
      applyTheme(newTheme);
    });
  }

  // Sidebar Toggle for Mobile View
  const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
  const sidebar = document.getElementById('sidebar');
  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('active');
    });
  }

  // Auto-dismiss alerts after 6 seconds
  const autoAlerts = document.querySelectorAll('.alert-dismissible');
  autoAlerts.forEach(function (alert) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      if (bsAlert) bsAlert.close();
    }, 6000);
  });

  // Client-side Receipt Image Upload Live Preview
  const receiptInputs = document.querySelectorAll('.image-preview-input');
  receiptInputs.forEach(function(input) {
    input.addEventListener('change', function() {
      const targetId = this.getAttribute('data-preview-target');
      const previewImg = document.getElementById(targetId);
      if (previewImg && this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          previewImg.classList.remove('d-none');
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  });

  // Live Table Search Filter (for client-side fast search)
  const searchInput = document.getElementById('table-live-search');
  if (searchInput) {
    searchInput.addEventListener('keyup', function () {
      const value = this.value.toLowerCase();
      const rows = document.querySelectorAll('#transactions-table tbody tr');
      rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(value) > -1 ? '' : 'none';
      });
    });
  }

});
