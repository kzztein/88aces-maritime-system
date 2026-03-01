<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">⚓</div>
    <div>
      <div class="brand-name">88 Aces</div>
      <div class="brand-sub">Maritime Training</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?= strtolower($currentPage) === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a href="sessions.php" class="nav-item <?= strtolower($currentPage) === 'sessions' ? 'active' : '' ?>">
      <span class="nav-icon">📋</span> Training Sessions
    </a>
    <a href="attendees.php" class="nav-item <?= strtolower($currentPage) === 'attendees' ? 'active' : '' ?>">
      <span class="nav-icon">👥</span> Attendees
    </a>
    <a href="certificates.php" class="nav-item <?= strtolower($currentPage) === 'certificates' ? 'active' : '' ?>">
      <span class="nav-icon">🎓</span> Certificates
    </a>
    <div class="nav-divider"></div>
    <a href="logout.php" class="nav-item nav-logout">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </nav>
</aside>