<?php
require_once '../config.php';
requireLogin();

$db = getDB();
$admin = currentAdmin();

// Stats
$totalSessions  = $db->query("SELECT COUNT(*) FROM training_sessions")->fetchColumn();
$openSessions   = $db->query("SELECT COUNT(*) FROM training_sessions WHERE status='open'")->fetchColumn();
$totalAttendees = $db->query("SELECT COUNT(*) FROM attendees")->fetchColumn();
$totalCerts     = $db->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// Recent sessions - FIXED QUERY
$recentSessions = $db->query(
    "SELECT ts.*, COUNT(a.id) as attendee_count, u.full_name as creator
     FROM training_sessions ts
     LEFT JOIN attendees a ON a.session_id = ts.id
     LEFT JOIN admin_users u ON u.id = ts.created_by
     GROUP BY ts.id, ts.session_code, ts.course_title, ts.training_type, 
              ts.date_conducted, ts.time_start, ts.time_end, ts.location, 
              ts.facilitator, ts.company, ts.status, ts.qr_token, 
              ts.created_by, ts.created_at, ts.updated_at, u.full_name
     ORDER BY ts.created_at DESC LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — 88 Aces Maritime</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'partials/sidebar.php'; ?>

<main class="main-content">
  <?php include 'partials/topbar.php'; ?>

  <div class="page-body">
    <div class="page-header">
      <h2>Dashboard</h2>
      <a href="sessions.php?action=create" class="btn btn-primary">+ New Training Session</a>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div>
          <div class="stat-num"><?= $totalSessions ?></div>
          <div class="stat-label">Total Sessions</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">🟢</div>
        <div>
          <div class="stat-num"><?= $openSessions ?></div>
          <div class="stat-label">Open / Active</div>
        </div>
      </div>
      <div class="stat-card orange">
        <div class="stat-icon">👥</div>
        <div>
          <div class="stat-num"><?= $totalAttendees ?></div>
          <div class="stat-label">Total Attendees</div>
        </div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon">🎓</div>
        <div>
          <div class="stat-num"><?= $totalCerts ?></div>
          <div class="stat-label">Certificates Issued</div>
        </div>
      </div>
    </div>

    <!-- Recent Sessions Table -->
    <div class="card">
      <div class="card-header">
        <h3>Recent Training Sessions</h3>
        <a href="sessions.php" class="link-all">View all →</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Session Code</th>
              <th>Course Title</th>
              <th>Date</th>
              <th>Location</th>
              <th>Attendees</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSessions as $s): ?>
            <tr>
              <td><code><?= sanitize($s['session_code']) ?></code></td>
              <td><?= sanitize($s['course_title']) ?></td>
              <td><?= date('M d, Y', strtotime($s['date_conducted'])) ?></td>
              <td><?= sanitize($s['location']) ?></td>
              <td>
                <span class="badge"><?= $s['attendee_count'] ?></span>
              </td>
              <td>
                <span class="status-badge <?= $s['status'] === 'open' ? 'status-open' : 'status-closed' ?>">
                  <?= ucfirst($s['status']) ?>
                </span>
              </td>
              <td class="actions">
                <a href="sessions.php?action=view&id=<?= $s['id'] ?>" title="View">👁</a>
                <a href="sessions.php?action=qr&id=<?= $s['id'] ?>"   title="QR Code">📱</a>
                <a href="sessions.php?action=edit&id=<?= $s['id'] ?>" title="Edit">✏️</a>
                <a href="../api/download.php?type=attendance&id=<?= $s['id'] ?>" title="Download PDF">⬇</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentSessions)): ?>
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:32px">No sessions yet. Create your first one!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>