<?php $admin = currentAdmin(); ?>
<header class="topbar">
  <button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-open')">☰</button>
  <div class="topbar-right">
    <span class="admin-name">👤 <?= sanitize($admin['full_name'] ?? 'Admin') ?></span>
    <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
  </div>
</header>