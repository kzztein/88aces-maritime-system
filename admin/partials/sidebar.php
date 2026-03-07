<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$admin = currentAdmin();

function getInitialsSidebar(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

$profilePhoto = !empty($admin['profile_photo']) ? '../uploads/profiles/' . $admin['profile_photo'] : null;
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
    <a href="archives.php" class="nav-item <?= strtolower($currentPage) === 'archives' ? 'active' : '' ?>">
      <span class="nav-icon">🗄️</span> Archives
    </a>
    <a href="logs.php" class="nav-item <?= strtolower($currentPage) === 'logs' ? 'active' : '' ?>">
      <span class="nav-icon">📊</span> System Logs
    </a>
    <div class="nav-divider"></div>
    <a href="profile.php" class="nav-item <?= strtolower($currentPage) === 'profile' ? 'active' : '' ?>">
      <div style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;overflow:hidden;flex-shrink:0">
        <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
          <img src="<?= $profilePhoto ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= getInitialsSidebar($admin['full_name'] ?? 'A') ?>
        <?php endif; ?>
      </div>
      &nbsp;My Profile
    </a>
    <a href="logout.php" class="nav-item nav-logout">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </nav>
</aside>