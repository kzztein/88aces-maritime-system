<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = ["ts.status = 'closed'"];
$params = [];

if ($search) {
    $where[]  = '(ts.course_title LIKE ? OR ts.session_code LIKE ? OR ts.facilitator LIKE ? OR ts.location LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM training_sessions ts WHERE $whereStr");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT ts.*,
           COUNT(a.id) as attendee_count,
           COUNT(c.id) as cert_count
    FROM training_sessions ts
    LEFT JOIN attendees a ON a.session_id = ts.id
    LEFT JOIN certificates c ON c.session_id = ts.id
    WHERE $whereStr
    GROUP BY ts.id
    ORDER BY ts.date_conducted DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();

$typeLabels = [
    'anti_piracy'     => ['label' => 'Anti-Piracy',    'color' => '#1a4a8a', 'icon' => '⚓'],
    'pdos'            => ['label' => 'PDOS',            'color' => '#16a34a', 'icon' => '✈️'],
    'secat'           => ['label' => 'SECAT',           'color' => '#d97706', 'icon' => '🚑'],
    'attendance_only' => ['label' => 'Attendance Only', 'color' => '#6b7280', 'icon' => '📋'],
];

// Archive stats
$totalArchived  = $db->query("SELECT COUNT(*) FROM training_sessions WHERE status='closed'")->fetchColumn();
$totalAttendees = $db->query("SELECT COUNT(*) FROM attendees a JOIN training_sessions ts ON ts.id=a.session_id WHERE ts.status='closed'")->fetchColumn();
$totalCerts     = $db->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
$yearsActive    = $db->query("SELECT COUNT(DISTINCT YEAR(date_conducted)) FROM training_sessions WHERE status='closed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Archives — 88 Aces</title>
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
.session-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:14px;border-left:4px solid #e5e7eb;transition:box-shadow .2s;}
.session-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);}
.session-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.session-title{font-size:15px;font-weight:700;color:#1f2937;}
.session-code{font-family:monospace;font-size:11px;background:#f3f4f6;padding:2px 8px;border-radius:4px;color:#6b7280;}
.session-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#6b7280;margin-bottom:12px;}
.session-meta span{display:flex;align-items:center;gap:4px;}
.session-actions{display:flex;gap:8px;flex-wrap:wrap;}
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
      <h2>🗄️ Archives</h2>
      <span style="font-size:13px;color:#6b7280"><?= $totalCount ?> closed session<?= $totalCount != 1 ? 's' : '' ?></span>
    </div>

    <div class="stats-grid">
      <div class="stat-card" style="border-left:4px solid #1a4a8a">
        <div class="stat-icon">🗄️</div>
        <div class="stat-num"><?= $totalArchived ?></div>
        <div class="stat-lbl">Archived Sessions</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #16a34a">
        <div class="stat-icon">👥</div>
        <div class="stat-num"><?= number_format($totalAttendees) ?></div>
        <div class="stat-lbl">Total Attendees</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #d97706">
        <div class="stat-icon">🎓</div>
        <div class="stat-num"><?= number_format($totalCerts) ?></div>
        <div class="stat-lbl">Certificates Issued</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #7c3aed">
        <div class="stat-icon">📅</div>
        <div class="stat-num"><?= $yearsActive ?></div>
        <div class="stat-lbl">Years of Records</div>
      </div>
    </div>

    <form method="GET" class="search-bar">
      <input type="text" name="search" placeholder="Search by title, code, facilitator, location..." value="<?= sanitize($search) ?>">
      <button type="submit">🔍 Search</button>
      <?php if ($search): ?>
        <a href="archives.php" style="padding:9px 16px;background:#f3f4f6;border-radius:8px;text-decoration:none;color:#374151;font-size:14px;">✕ Clear</a>
      <?php endif; ?>
    </form>

    <?php if (empty($sessions)): ?>
      <div class="card" style="text-align:center;padding:60px;color:#9ca3af">
        <div style="font-size:48px;margin-bottom:12px">🗄️</div>
        <div style="font-size:16px;font-weight:600">No archived sessions yet</div>
        <div style="font-size:13px;margin-top:6px">Closed sessions will appear here</div>
      </div>
    <?php else: ?>
      <?php foreach ($sessions as $s):
        $ti = $typeLabels[$s['training_type']] ?? $typeLabels['attendance_only'];
      ?>
      <div class="session-card" style="border-left-color:<?= $ti['color'] ?>">
        <div class="session-header">
          <div>
            <div class="session-title"><?= sanitize($s['course_title']) ?></div>
            <div style="margin-top:4px">
              <span class="session-code"><?= sanitize($s['session_code']) ?></span>
              &nbsp;
              <span style="background:<?= $ti['color'] ?>22;color:<?= $ti['color'] ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600">
                <?= $ti['icon'] ?> <?= $ti['label'] ?>
              </span>
            </div>
          </div>
          <div style="text-align:right;font-size:13px;color:#6b7280">
            <?= date('F d, Y', strtotime($s['date_conducted'])) ?>
          </div>
        </div>

        <div class="session-meta">
          <span>📍 <?= sanitize($s['location']) ?></span>
          <span>👤 <?= sanitize($s['facilitator']) ?></span>
          <span>👥 <?= $s['attendee_count'] ?> attendees</span>
          <span>🎓 <?= $s['cert_count'] ?> certificates</span>
          <?php if ($s['principal']): ?>
            <span>🏢 <?= sanitize($s['principal']) ?></span>
          <?php endif; ?>
        </div>

        <div class="session-actions">
          <a href="sessions.php?action=view&id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">👁 View Details</a>
          <a href="../api/download.php?type=attendance&id=<?= $s['id'] ?>" class="btn btn-primary btn-sm" target="_blank">⬇ Attendance PDF</a>
          <a href="../api/excel.php?type=attendance&id=<?= $s['id'] ?>" class="btn btn-success btn-sm" target="_blank">📊 Excel</a>
          <a href="../api/download.php?type=report&id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" target="_blank">📄 Report</a>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&year=<?= $yearFilter ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <?php if ($p == $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&year=<?= $yearFilter ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&year=<?= $yearFilter ?>">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>
</body>
</html>