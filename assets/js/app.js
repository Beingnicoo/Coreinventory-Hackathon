// CoreInventory - Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggle = document.getElementById('sidebarToggle');
  
  if (sidebar && overlay && toggle) {
    toggle.addEventListener('click', function() {
      sidebar.classList.toggle('show');
      overlay.classList.toggle('show');
      document.body.classList.toggle('overflow-hidden', sidebar.classList.contains('show'));
    });
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
      document.body.classList.remove('overflow-hidden');
    });
  }

  // Dark Mode Toggle
  const darkModeToggle = document.getElementById('darkModeToggle');
  const darkModeText = document.getElementById('darkModeText');
  
  // Check for saved dark mode preference or default to light mode
  const isDarkMode = localStorage.getItem('darkMode') === 'true';
  
  // Apply dark mode on page load
  if (isDarkMode) {
    document.body.classList.add('dark-mode');
    updateDarkModeIcon(true);
  }
  
  // Toggle dark mode
  if (darkModeToggle) {
    darkModeToggle.addEventListener('click', function() {
      const isDark = document.body.classList.toggle('dark-mode');
      localStorage.setItem('darkMode', isDark);
      updateDarkModeIcon(isDark);
    });
  }
  
  function updateDarkModeIcon(isDark) {
    if (darkModeToggle) {
      const icon = darkModeToggle.querySelector('i');
      if (icon) {
        if (isDark) {
          icon.className = 'bi bi-sun';
          if (darkModeText) {
            darkModeText.textContent = 'Light Mode';
          }
        } else {
          icon.className = 'bi bi-moon-stars';
          if (darkModeText) {
            darkModeText.textContent = 'Dark Mode';
          }
        }
      }
    }
  }
});
