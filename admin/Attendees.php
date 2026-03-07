<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();

// Search / filter
$search     = trim($_GET['search']   ?? '');
$typeFilter = $_GET['type']          ?? '';
$crewFilter = $_GET['crew_type']     ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(a.surname LIKE ? OR a.given_name LIKE ? OR a.vessel LIKE ? OR a.rank LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($typeFilter) {
    $where[]  = 'ts.training_type = ?';
    $params[] = $typeFilter;
}
if ($crewFilter) {
    $where[]  = 'a.crew_type = ?';
    $params[] = $crewFilter;
}

$whereStr = implode(' AND ', $where);

$total = $db->prepare("
    SELECT COUNT(*) FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    WHERE $whereStr
");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT a.*,
           ts.course_title, ts.training_type, ts.date_conducted, ts.status as session_status,
           ts.session_code
    FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    WHERE $whereStr
    ORDER BY a.submitted_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$attendees = $stmt->fetchAll();

// Summary stats
$totalAttendees  = $db->query("SELECT COUNT(*) FROM attendees")->fetchColumn();
$withCerts       = $db->query("SELECT COUNT(*) FROM attendees WHERE cert_number IS NOT NULL")->fetchColumn();
$newCrew         = $db->query("SELECT COUNT(*) FROM attendees WHERE crew_type = 'NEW CREW'")->fetchColumn();
$exCrew          = $db->query("SELECT COUNT(*) FROM attendees WHERE crew_type = 'EX CREW'")->fetchColumn();

$typeLabels = [
    'anti_piracy'     => ['label' => 'Anti-Piracy',    'color' => '#1a4a8a', 'icon' => '⚓'],
    'pdos'            => ['label' => 'PDOS',            'color' => '#16a34a', 'icon' => '✈️'],
    'secat'           => ['label' => 'SECAT',           'color' => '#d97706', 'icon' => '🚑'],
    'attendance_only' => ['label' => 'Attendance Only', 'color' => '#6b7280', 'icon' => '📋'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Attendees — 88 Aces</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; border-radius:12px; padding:16px 20px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.stat-num  { font-size:28px; font-weight:700; color:#1f2937; }
.stat-lbl  { font-size:12px; color:#6b7280; margin-top:2px; }
.stat-icon { font-size:22px; margin-bottom:4px; }
.search-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.search-bar input  { flex:1; min-width:200px; padding:9px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
.search-bar select { padding:9px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#fff; }
.search-bar button { padding:9px 20px; background:#1a4a8a; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px; }
.cert-num  { font-family:monospace; font-size:11px; background:#f3f4f6; padding:2px 7px; border-radius:4px; font-weight:600; }
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
      <h2>👥 Attendees</h2>
      <span style="font-size:13px;color:#6b7280"><?= $totalCount ?> attendee<?= $totalCount != 1 ? 's' : '' ?> found</span>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card" style="border-left:4px solid #1a4a8a">
        <div class="stat-icon">👥</div>
        <div class="stat-num"><?= $totalAttendees ?></div>
        <div class="stat-lbl">Total Attendees</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #16a34a">
        <div class="stat-icon">🎓</div>
        <div class="stat-num"><?= $withCerts ?></div>
        <div class="stat-lbl">With Certificates</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #d97706">
        <div class="stat-icon">🆕</div>
        <div class="stat-num"><?= $newCrew ?></div>
        <div class="stat-lbl">New Crew</div>
      </div>
      <div class="stat-card" style="border-left:4px solid #6b7280">
        <div class="stat-icon">🔄</div>
        <div class="stat-num"><?= $exCrew ?></div>
        <div class="stat-lbl">Ex Crew</div>
      </div>
    </div>

    <!-- Search & Filter -->
    <form method="GET" class="search-bar">
      <input type="text" name="search" placeholder="Search by name, vessel, rank..." value="<?= sanitize($search) ?>">
      <select name="type">
        <option value="">All Training Types</option>
        <?php foreach ($typeLabels as $tv => $ti): ?>
        <option value="<?= $tv ?>" <?= $typeFilter === $tv ? 'selected' : '' ?>><?= $ti['icon'] ?> <?= $ti['label'] ?></option>
        <?php endforeach; ?>
      </select>
      <select name="crew_type">
        <option value="">All Crew Types</option>
        <option value="NEW CREW" <?= $crewFilter === 'NEW CREW' ? 'selected' : '' ?>>🆕 New Crew</option>
        <option value="EX CREW"  <?= $crewFilter === 'EX CREW'  ? 'selected' : '' ?>>🔄 Ex Crew</option>
      </select>
      <button type="submit">🔍 Search</button>
      <?php if ($search || $typeFilter || $crewFilter): ?>
        <a href="attendees.php" style="padding:9px 16px;background:#f3f4f6;border-radius:8px;text-decoration:none;color:#374151;font-size:14px;">✕ Clear</a>
      <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Surname</th>
              <th>Given Name</th>
              <th>M.I.</th>
              <th>Rank</th>
              <th>Vessel</th>
              <th>Crew Type</th>
              <th>Training</th>
              <th>Session</th>
              <th>Date</th>
              <th>Certificate</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($attendees)): ?>
            <tr><td colspan="12" style="text-align:center;padding:40px;color:#9ca3af">
              <?= $search || $typeFilter || $crewFilter ? 'No attendees found matching your search.' : 'No attendees yet.' ?>
            </td></tr>
          <?php else: ?>
          <?php foreach ($attendees as $i => $a):
            $ti = $typeLabels[$a['training_type']] ?? $typeLabels['attendance_only'];
          ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td style="font-weight:600"><?= sanitize(strtoupper($a['surname'])) ?></td>
              <td><?= sanitize(strtoupper($a['given_name'])) ?></td>
              <td><?= sanitize(strtoupper($a['middle_initial'])) ?></td>
              <td><?= sanitize($a['rank']) ?></td>
              <td><?= sanitize($a['vessel']) ?></td>
              <td>
                <span class="badge" style="background:<?= $a['crew_type']==='NEW CREW'?'#fef3c7':'#f0fdf4' ?>;color:<?= $a['crew_type']==='NEW CREW'?'#92400e':'#166534' ?>">
                  <?= sanitize($a['crew_type']) ?>
                </span>
              </td>
              <td>
                <span style="background:<?= $ti['color'] ?>22;color:<?= $ti['color'] ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600">
                  <?= $ti['icon'] ?> <?= $ti['label'] ?>
                </span>
              </td>
              <td style="font-size:11px;color:#6b7280"><code><?= sanitize($a['session_code']) ?></code></td>
              <td style="font-size:12px"><?= date('M d, Y', strtotime($a['date_conducted'])) ?></td>
              <td>
                <?php if ($a['cert_number']): ?>
                  <span class="cert-num"><?= sanitize($a['cert_number']) ?></span>
                <?php else: ?>
                  <span style="color:#9ca3af;font-size:12px">Pending</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <a href="sessions.php?action=view&id=<?= $a['session_id'] ?>" title="View Session">👁</a>
                <?php if ($a['cert_number']): ?>
                  <a href="../api/generate_cert.php?id=<?= $a['id'] ?>" target="_blank" title="Download Certificate">🎓</a>
                <?php endif; ?>
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
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&crew_type=<?= $crewFilter ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <?php if ($p == $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&crew_type=<?= $crewFilter ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= $typeFilter ?>&crew_type=<?= $crewFilter ?>">Next →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>
</body>
</html>