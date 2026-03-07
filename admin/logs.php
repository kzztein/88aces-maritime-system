<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(al.action LIKE ? OR al.notes LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s]);
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("
    SELECT COUNT(*) FROM audit_log al
    LEFT JOIN admin_users adm ON adm.id = al.admin_id
    WHERE $whereStr
");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT al.*, adm.username, adm.full_name
    FROM audit_log al
    LEFT JOIN admin_users adm ON adm.id = al.admin_id
    WHERE $whereStr
    ORDER BY al.logged_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Stats
$todayCount  = $db->query("SELECT COUNT(*) FROM audit_log WHERE DATE(logged_at) = CURDATE()")->fetchColumn();
$weekCount   = $db->query("SELECT COUNT(*) FROM audit_log WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$totalLogs   = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$adminCount  = $db->query("SELECT COUNT(DISTINCT admin_id) FROM audit_log WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$actionColors = [
    'LOGIN'          => ['bg' => '#dcfce7', 'color' => '#166534'],
    'LOGOUT'         => ['bg' => '#f3f4f6', 'color' => '#374151'],
    'CREATE_SESSION' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
    'UPDATE_SESSION' => ['bg' => '#fef9c3', 'color' => '#854d0e'],
    'DELETE_SESSION' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
    'CLOSE_SESSION'  => ['bg' => '#e0e7ff', 'color' => '#3730a3'],
    'GENERATE_CERT'  => ['bg' => '#fce7f3', 'color' => '#9d174d'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>System Logs — 88 Aces</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.stat-num{font-size:28px;font-weight:700;color:#1f2937;}
.stat-lbl{font-size:12px;color:#6b7280;margin-top:2px;}
.stat-icon{font-size:22px;margin-bottom:4px;}
.search-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.search-bar input{flex:1;min-width:200px;padding:9px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;}
.search-bar select{padding:9px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;}
.search-bar button{padding:9px 20px;background:#1a4a8a;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;}
.action-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;}
.log-detail{font-size:12px;color:#6b7280;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px;}
.pagination a,.pagination span{padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;}
.pagination a{background:#fff;border:1px solid #e5e7eb;color:#374151;}
.pagination a:hover{background:#f3f4f6;}
.pagination .active{background:#1a4a8a;color:#fff;border:1px solid #1a4a8a;}
</style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <div class="page-header">
      <h2>📋 System Logs</h2>
      <span style="font-size:13px;color:#6b7280"><?= number_format($totalCount) ?> log entries</span>
    </div>

    <div class="stats-grid">
      <div class="stat-card" style="border-left:4px solid #1a4a8a">
        <div class="stat-icon">📋</div>
        <div class="stat-num"><?= number_format($totalLogs) ?></div>
        <div class="stat-lbl">Total Log Entries</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #16a34a">
        <div class="stat-icon">📅</div>
        <div class="stat-num"><?= $todayCount ?></div>
        <div class="stat-lbl">Today</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #d97706">
        <div class="stat-icon">📆</div>
        <div class="stat-num"><?= $weekCount ?></div>
        <div class="stat-lbl">Last 7 Days</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #7c3aed">
        <div class="stat-icon">👤</div>
        <div class="stat-num"><?= $adminCount ?></div>
        <div class="stat-lbl">Active Admins (7d)</div>
      </div>
    </div>

    <form method="GET" class="search-bar">
      <input type="text" name="search" placeholder="Search action, notes, admin..." value="<?= sanitize($search) ?>">
      <button type="submit">🔍 Search</button>
      <?php if ($search): ?>
        <a href="logs.php" style="padding:9px 16px;background:#f3f4f6;border-radius:8px;text-decoration:none;color:#374151;font-size:14px;">✕ Clear</a>
      <?php endif; ?>
    </form>

    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Date & Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Notes</th></tr>
          </thead>
          <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">No logs found.</td></tr>
          <?php else: ?>
          <?php foreach ($logs as $i => $log):
            $ac = $actionColors[$log['action']] ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
          ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td style="font-size:12px;white-space:nowrap"><?= date('M d, Y h:i A', strtotime($log['logged_at'])) ?></td>
              <td>
                <strong><?= sanitize($log['full_name'] ?? $log['username'] ?? 'System') ?></strong>
              </td>
              <td>
                <span class="action-badge" style="background:<?= $ac['bg'] ?>;color:<?= $ac['color'] ?>">
                  <?= sanitize($log['action']) ?>
                </span>
              </td>
              <td style="font-size:12px;color:#6b7280"><?= sanitize($log['target_type'] ?? '') ?> <?= $log['target_id'] ? '#'.$log['target_id'] : '' ?></td>
              <td><div class="log-detail" title="<?= sanitize($log["notes"] ?? '') ?>"><?= sanitize($log["notes"] ?? '—') ?></div></td>
              <td style="font-size:11px;color:#9ca3af;font-family:monospace">—</td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <?php if ($p == $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>
</body>
</html>
