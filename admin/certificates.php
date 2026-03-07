<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();

// Search / filter
$search   = trim($_GET['search']   ?? '');
$typeFilter = $_GET['type']        ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(a.surname LIKE ? OR a.given_name LIKE ? OR c.cert_number LIKE ? OR a.vessel LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($typeFilter) {
    $where[]  = 'ts.training_type = ?';
    $params[] = $typeFilter;
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("
    SELECT COUNT(*) FROM certificates c
    JOIN attendees a ON a.id = c.attendee_id
    JOIN training_sessions ts ON ts.id = c.session_id
    WHERE $whereStr
");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT c.*, 
           a.surname, a.given_name, a.middle_initial, a.rank, a.vessel, a.crew_type,
           ts.course_title, ts.training_type, ts.date_conducted, ts.facilitator
    FROM certificates c
    JOIN attendees a ON a.id = c.attendee_id
    JOIN training_sessions ts ON ts.id = c.session_id
    WHERE $whereStr
    ORDER BY c.generated_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$certs = $stmt->fetchAll();

$typeLabels = [
    'anti_piracy'     => ['label' => 'Anti-Piracy',    'color' => '#1a4a8a', 'icon' => '⚓'],
    'pdos'            => ['label' => 'PDOS',            'color' => '#16a34a', 'icon' => '✈️'],
    'secat'           => ['label' => 'SECAT',           'color' => '#d97706', 'icon' => '🚑'],
    'attendance_only' => ['label' => 'Attendance Only', 'color' => '#6b7280', 'icon' => '📋'],
];

// Stats
$stats = $db->query("
    SELECT ts.training_type, COUNT(*) as cnt
    FROM certificates c
    JOIN training_sessions ts ON ts.id = c.session_id
    GROUP BY ts.training_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalCerts = array_sum($stats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Certificates — 88 Aces</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; border-radius:12px; padding:16px 20px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.stat-num  { font-size:28px; font-weight:700; color:#1f2937; }
.stat-lbl  { font-size:12px; color:#6b7280; margin-top:2px; }
.stat-icon { font-size:22px; margin-bottom:4px; }
.search-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.search-bar input { flex:1; min-width:200px; padding:9px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
.search-bar select { padding:9px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#fff; }
.search-bar button { padding:9px 20px; background:#1a4a8a; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px; }
.cert-num  { font-family:monospace; font-size:12px; background:#f3f4f6; padding:3px 8px; border-radius:4px; font-weight:600; color:#1f2937; }
.pagination { display:flex; gap:6px; justify-content:center; margin-top:20px; }
.pagination a, .pagination span { padding:6px 12px; border-radius:6px; font-size:13px; text-decoration:none; }
.pagination a { background:#fff; border:1px solid #e5e7eb; color:#374151; }
.pagination a:hover { background:#f3f4f6; }
.pagination .active { background:#1a4a8a; color:#fff; border:1px solid #1a4a8a; }
</style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <div class="page-header">
      <h2>🎓 Certificates</h2>
      <span style="font-size:13px;color:#6b7280"><?= $totalCount ?> certificate<?= $totalCount != 1 ? 's' : '' ?> issued</span>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">🎓</div>
        <div class="stat-num"><?= $totalCerts ?></div>
        <div class="stat-lbl">Total Certificates</div>
      </div>
      <?php foreach ($typeLabels as $tv => $ti): ?>
      <div class="stat-card" style="border-left:4px solid <?= $ti['color'] ?>">
        <div class="stat-icon"><?= $ti['icon'] ?></div>
        <div class="stat-num" style="color:<?= $ti['color'] ?>"><?= $stats[$tv] ?? 0 ?></div>
        <div class="stat-lbl"><?= $ti['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Search & Filter -->
    <form method="GET" class="search-bar">
      <input type="text" name="search" placeholder="Search by name, cert number, vessel..." value="<?= sanitize($search) ?>">
      <select name="type">
        <option value="">All Types</option>
        <?php foreach ($typeLabels as $tv => $ti): ?>
        <option value="<?= $tv ?>" <?= $typeFilter === $tv ? 'selected' : '' ?>><?= $ti['icon'] ?> <?= $ti['label'] ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">🔍 Search</button>
      <?php if ($search || $typeFilter): ?>
        <a href="certificates.php" style="padding:9px 16px;background:#f3f4f6;border-radius:8px;text-decoration:none;color:#374151;font-size:14px;">✕ Clear</a>
      <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Certificate No.</th>
              <th>Name</th>
              <th>Rank</th>
              <th>Vessel</th>
              <th>Type</th>
              <th>Course</th>
              <th>Date Conducted</th>
              <th>Date Issued</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($certs)): ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:#9ca3af">
              <?= $search || $typeFilter ? 'No certificates found matching your search.' : 'No certificates issued yet.' ?>
            </td></tr>
          <?php else: ?>
          <?php foreach ($certs as $i => $c):
            $ti = $typeLabels[$c['training_type']] ?? $typeLabels['attendance_only'];
            $fullName = strtoupper($c['surname'] . ', ' . $c['given_name'] . ' ' . $c['middle_initial']);
          ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td><span class="cert-num"><?= sanitize($c['cert_number']) ?></span></td>
              <td style="font-weight:600"><?= sanitize($fullName) ?></td>
              <td><?= sanitize($c['rank']) ?></td>
              <td><?= sanitize($c['vessel']) ?></td>
              <td>
                <span style="background:<?= $ti['color'] ?>22;color:<?= $ti['color'] ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">
                  <?= $ti['icon'] ?> <?= $ti['label'] ?>
                </span>
              </td>
              <td style="font-size:12px"><?= sanitize($c['course_title']) ?></td>
              <td><?= date('M d, Y', strtotime($c['date_conducted'])) ?></td>
              <td style="font-size:12px;color:#6b7280"><?= date('M d, Y', strtotime($c['generated_at'])) ?></td>
              <td class="actions">
                <a href="../api/generate_cert.php?id=<?= $c['attendee_id'] ?>" target="_blank" title="Download PDF">🎓 PDF</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <?php if ($p == $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>
</body>
</html>
