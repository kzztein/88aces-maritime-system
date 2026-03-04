<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$error  = '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $courseTitle  = trim($_POST['course_title'] ?? '');
        $trainingType = $_POST['training_type']   ?? 'anti_piracy';
        $dateConducted= $_POST['date_conducted']  ?? '';
        $timeStart    = $_POST['time_start']      ?? '';
        $timeEnd      = $_POST['time_end']        ?? '';
        $location     = trim($_POST['location']   ?? '');
        $facilitator  = trim($_POST['facilitator']?? '');
        $company      = trim($_POST['company']    ?? '88 ACES MARITIME SERVICES INC.');

        if (!$courseTitle || !$dateConducted || !$location || !$facilitator) {
            $error = 'Please fill in all required fields.';
        } else {
            if ($postAction === 'create') {
                $token = generateToken(16);
                $code  = generateSessionCode();
                $stmt  = $db->prepare(
                    "INSERT INTO training_sessions
                     (session_code,course_title,training_type,date_conducted,time_start,time_end,location,facilitator,company,qr_token,created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$code,$courseTitle,$trainingType,$dateConducted,$timeStart,$timeEnd,$location,$facilitator,$company,$token,$admin['id']]);
                $newId = $db->lastInsertId();
                auditLog('CREATE_SESSION','session',$newId,"Code: $code");
                $msg = 'Training session created successfully!';
                header("Location: sessions.php?action=view&id=$newId&msg=" . urlencode($msg));
                exit;
            } else {
                $stmt = $db->prepare(
                    "UPDATE training_sessions SET course_title=?,training_type=?,date_conducted=?,
                     time_start=?,time_end=?,location=?,facilitator=?,company=? WHERE id=?"
                );
                $stmt->execute([$courseTitle,$trainingType,$dateConducted,$timeStart,$timeEnd,$location,$facilitator,$company,$id]);
                auditLog('UPDATE_SESSION','session',$id);
                $msg = 'Session updated successfully.';
            }
        }
    }

    if ($postAction === 'toggle_status') {
        $sid = (int)$_POST['session_id'];
        $cur = $db->prepare("SELECT status FROM training_sessions WHERE id=?");
        $cur->execute([$sid]);
        $current = $cur->fetchColumn();
        $newStatus = ($current === 'open') ? 'closed' : 'open';
        $db->prepare("UPDATE training_sessions SET status=? WHERE id=?")->execute([$newStatus,$sid]);

        if ($newStatus === 'closed') {
            generateCertificatesForSession($sid, $db, $admin['id']);
        }
        header("Location: sessions.php?action=view&id=$sid&msg=Status+updated.");
        exit;
    }

    if ($postAction === 'delete') {
        $sid = (int)$_POST['session_id'];
        $db->prepare("DELETE FROM training_sessions WHERE id=?")->execute([$sid]);
        auditLog('DELETE_SESSION','session',$sid);
        header("Location: sessions.php?msg=Session+deleted.");
        exit;
    }
}

if ($action === 'delete' && $id) {
    $action = 'list';
}

$session = null;
if ($id) {
    $stmt = $db->prepare("SELECT * FROM training_sessions WHERE id=?");
    $stmt->execute([$id]);
    $session = $stmt->fetch();
}

$attendees = [];
if ($action === 'view' && $session) {
    $stmt = $db->prepare(
        "SELECT a.*, c.cert_number, c.pdf_filename FROM attendees a
         LEFT JOIN certificates c ON c.attendee_id = a.id
         WHERE a.session_id = ? ORDER BY a.submitted_at ASC"
    );
    $stmt->execute([$id]);
    $attendees = $stmt->fetchAll();
}

$sessions = [];
if ($action === 'list') {
    $sessions = $db->query(
        "SELECT ts.*, COUNT(a.id) as attendee_count
         FROM training_sessions ts
         LEFT JOIN attendees a ON a.session_id = ts.id
         GROUP BY ts.id ORDER BY ts.created_at DESC"
    )->fetchAll();
}

$msg = $msg ?: sanitize($_GET['msg'] ?? '');

function generateCertificatesForSession(int $sid, PDO $db, int $adminId): void {
    $attendees = $db->prepare("SELECT * FROM attendees WHERE session_id=? AND cert_number IS NULL");
    $attendees->execute([$sid]);
    foreach ($attendees->fetchAll() as $a) {
        $certNum = generateCertNumber();
        $db->prepare("UPDATE attendees SET cert_number=?, cert_issued_at=NOW() WHERE id=?")->execute([$certNum, $a['id']]);
        $db->prepare(
            "INSERT INTO certificates (attendee_id,session_id,cert_number,cert_type,generated_by)
             VALUES (?,?,?,'anti_piracy',?) ON DUPLICATE KEY UPDATE cert_number=VALUES(cert_number)"
        )->execute([$a['id'], $sid, $certNum, $adminId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Training Sessions — 88 Aces Maritime</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>

<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <?php if ($msg): ?>
      <div class="alert alert-success"><?= sanitize($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <!-- ── LIST ─────────────────────────────────────────── -->
    <?php if ($action === 'list'): ?>
    <div class="page-header">
      <h2>Training Sessions</h2>
      <a href="sessions.php?action=create" class="btn btn-primary">+ New Session</a>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Code</th><th>Course Title</th><th>Type</th>
              <th>Date</th><th>Location</th><th>Attendees</th>
              <th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($sessions as $s): ?>
            <tr>
              <td><code><?= sanitize($s['session_code']) ?></code></td>
              <td><?= sanitize($s['course_title']) ?></td>
              <td><span class="badge"><?= $s['training_type'] === 'anti_piracy' ? 'Anti-Piracy' : 'Attendance' ?></span></td>
              <td><?= date('M d, Y', strtotime($s['date_conducted'])) ?></td>
              <td><?= sanitize($s['location']) ?></td>
              <td><span class="badge"><?= $s['attendee_count'] ?></span></td>
              <td><span class="status-badge <?= $s['status']==='open'?'status-open':'status-closed' ?>"><?= ucfirst($s['status']) ?></span></td>
              <td class="actions">
                <a href="sessions.php?action=view&id=<?= $s['id'] ?>" title="View">👁</a>
                <a href="#" onclick="showQR('<?= APP_URL ?>/seafarer/form.php?token=<?= $s['qr_token'] ?>','<?= sanitize($s['course_title']) ?>');return false;" title="QR Code">📱</a>
                <a href="sessions.php?action=edit&id=<?= $s['id'] ?>" title="Edit">✏️</a>
                <a href="../api/download.php?type=attendance&id=<?= $s['id'] ?>" title="Download Attendance PDF" target="_blank">⬇PDF</a>
                <a href="../api/excel.php?type=attendance&id=<?= $s['id'] ?>" title="Download Attendance Excel" target="_blank">📊XLS</a>
                <a href="#" onclick="confirmDelete(<?= $s['id'] ?>);return false;" title="Delete">🗑</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($sessions)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:#9ca3af">No sessions found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── CREATE / EDIT ────────────────────────────────── -->
    <?php elseif ($action === 'create' || ($action === 'edit' && $session)): ?>
    <div class="page-header">
      <h2><?= $action === 'create' ? 'Create New Session' : 'Edit Session' ?></h2>
      <a href="sessions.php" class="btn btn-outline">← Back</a>
    </div>
    <div class="card">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
          <?php if ($action === 'edit'): ?>
            <input type="hidden" name="session_id" value="<?= $id ?>">
          <?php endif; ?>
          <div class="form-grid">
            <div class="form-group full">
              <label>Course Title *</label>
              <input type="text" name="course_title" value="<?= sanitize($session['course_title'] ?? 'ANTI-PIRACY AWARENESS TRAINING') ?>" required>
            </div>
            <div class="form-group">
              <label>Training Type *</label>
              <select name="training_type">
                <option value="anti_piracy"    <?= ($session['training_type'] ?? '') === 'anti_piracy'    ? 'selected' : '' ?>>Anti-Piracy Awareness</option>
                <option value="attendance_only" <?= ($session['training_type'] ?? '') === 'attendance_only'? 'selected' : '' ?>>Attendance Only</option>
              </select>
            </div>
            <div class="form-group">
              <label>Date Conducted *</label>
              <input type="date" name="date_conducted" value="<?= $session['date_conducted'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label>Time Start</label>
              <input type="time" name="time_start" value="<?= $session['time_start'] ?? '08:00' ?>">
            </div>
            <div class="form-group">
              <label>Time End</label>
              <input type="time" name="time_end" value="<?= $session['time_end'] ?? '17:00' ?>">
            </div>
            <div class="form-group">
              <label>Location *</label>
              <input type="text" name="location" value="<?= sanitize($session['location'] ?? '') ?>" placeholder="e.g. 12th Floor Trium Square Building" required>
            </div>
            <div class="form-group">
              <label>Facilitator *</label>
              <input type="text" name="facilitator" value="<?= sanitize($session['facilitator'] ?? '') ?>" placeholder="Facilitator name" required>
            </div>
            <div class="form-group">
              <label>Company</label>
              <input type="text" name="company" value="<?= sanitize($session['company'] ?? '88 ACES MARITIME SERVICES INC.') ?>">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $action === 'create' ? '✓ Create Session' : '✓ Save Changes' ?></button>
            <a href="sessions.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- ── VIEW SESSION ──────────────────────────────────── -->
    <?php elseif ($action === 'view' && $session): ?>
    <div class="page-header">
      <div>
        <h2><?= sanitize($session['course_title']) ?></h2>
        <p style="color:#6b7280;font-size:14px;margin-top:4px"><?= sanitize($session['session_code']) ?> · <?= date('F d, Y', strtotime($session['date_conducted'])) ?></p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="sessions.php" class="btn btn-outline btn-sm">← Back</a>
        <a href="sessions.php?action=edit&id=<?= $id ?>" class="btn btn-outline btn-sm">✏ Edit</a>
        <button onclick="showQR('<?= APP_URL ?>/seafarer/form.php?token=<?= $session['qr_token'] ?>','<?= sanitize($session['course_title']) ?>')" class="btn btn-outline btn-sm">📱 QR Code</button>
        <a href="../api/download.php?type=attendance&id=<?= $id ?>" class="btn btn-primary btn-sm" target="_blank">⬇ Attendance PDF</a>
        <a href="../api/excel.php?type=attendance&id=<?= $id ?>" class="btn btn-success btn-sm" target="_blank">📊 Attendance Excel</a>
        <?php if ($session['status'] === 'closed'): ?>
          <a href="../api/download.php?type=report&id=<?= $id ?>" class="btn btn-primary btn-sm" target="_blank">📊 Report PDF</a>
          <a href="../api/excel.php?type=report&id=<?= $id ?>" class="btn btn-success btn-sm" target="_blank">📊 Report Excel</a>
        <?php endif; ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="session_id" value="<?= $id ?>">
          <button type="submit" class="btn <?= $session['status']==='open'?'btn-danger':'btn-success' ?> btn-sm"
                  onclick="return confirm('<?= $session['status']==='open'?'Close this session and generate certificates?':'Reopen this session?' ?>')">
            <?= $session['status']==='open' ? '🔒 Close Session' : '🔓 Reopen Session' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Session info -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <div class="form-grid">
          <div><strong>Location:</strong> <?= sanitize($session['location']) ?></div>
          <div><strong>Facilitator:</strong> <?= sanitize($session['facilitator']) ?></div>
          <div><strong>Time:</strong> <?= date('h:i A', strtotime($session['time_start'])) ?> – <?= date('h:i A', strtotime($session['time_end'])) ?></div>
          <div><strong>Company:</strong> <?= sanitize($session['company']) ?></div>
          <div><strong>Status:</strong> <span class="status-badge <?= $session['status']==='open'?'status-open':'status-closed' ?>"><?= ucfirst($session['status']) ?></span></div>
          <div><strong>Form Link:</strong>
            <a href="<?= APP_URL ?>/seafarer/form.php?token=<?= $session['qr_token'] ?>" target="_blank" style="font-size:12px;word-break:break-all">
              <?= APP_URL ?>/seafarer/form.php?token=<?= $session['qr_token'] ?>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Attendees table -->
    <div class="card">
      <div class="card-header">
        <h3>Attendees (<?= count($attendees) ?>)</h3>
        <?php if ($session['status'] === 'open'): ?>
          <span style="font-size:12px;color:#16a34a">● Form is open — seafarers can submit</span>
        <?php endif; ?>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Surname</th><th>Given Name</th><th>M.I.</th>
              <th>Rank</th><th>Vessel</th><th>Type</th><th>Cert No.</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($attendees as $i => $a): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= sanitize($a['surname']) ?></td>
              <td><?= sanitize($a['given_name']) ?></td>
              <td><?= sanitize($a['middle_initial']) ?></td>
              <td><?= sanitize($a['rank']) ?></td>
              <td><?= sanitize($a['vessel']) ?></td>
              <td><span class="badge" style="background:#fef3c7;color:#92400e"><?= $a['crew_type'] ?></span></td>
              <td><?= $a['cert_number'] ? '<code>'.sanitize($a['cert_number']).'</code>' : '<span style="color:#9ca3af">Pending</span>' ?></td>
              <td class="actions">
                <a href="attendee_edit.php?id=<?= $a['id'] ?>" title="Edit">✏️</a>
                <?php if ($a['cert_number']): ?>
                  <a href="../api/download.php?type=certificate&id=<?= $a['id'] ?>" title="Download Cert" target="_blank">🎓</a>
                <?php endif; ?>
                <a href="#" onclick="deleteAttendee(<?= $a['id'] ?>);return false;" title="Delete">🗑</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($attendees)): ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#9ca3af">
              No attendees yet. Share the QR code or form link with seafarers.
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
  <div class="modal">
    <h3>Scan to Fill Form</h3>
    <p id="qrTitle"></p>
    <div id="qrcode" style="margin:0 auto;width:220px;height:220px"></div>
    <div style="margin-top:12px">
      <input type="text" id="qrLink" readonly style="width:100%;padding:8px;font-size:12px;border:1px solid #e5e7eb;border-radius:6px">
    </div>
    <div class="modal-actions">
      <button onclick="copyLink()" class="btn btn-outline btn-sm">📋 Copy Link</button>
      <button onclick="printQR()"  class="btn btn-primary btn-sm">🖨 Print QR</button>
      <button onclick="closeQR()"  class="btn btn-outline btn-sm">Close</button>
    </div>
  </div>
</div>

<!-- Delete form -->
<form id="deleteForm" method="POST">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="session_id" id="deleteId">
</form>
<form id="deleteAttendeeForm" method="POST" action="api/attendees.php">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteAttendeeId">
</form>

<script>
let qrInstance = null;
function showQR(url, title) {
  document.getElementById('qrTitle').textContent = title;
  document.getElementById('qrLink').value = url;
  const container = document.getElementById('qrcode');
  container.innerHTML = '';
  qrInstance = new QRCode(container, { text: url, width: 220, height: 220 });
  document.getElementById('qrModal').classList.add('show');
}
function closeQR()   { document.getElementById('qrModal').classList.remove('show'); }
function copyLink()  { const inp = document.getElementById('qrLink'); inp.select(); document.execCommand('copy'); alert('Link copied!'); }
function printQR()   { window.print(); }
function confirmDelete(id) {
  if (!confirm('Delete this session and ALL attendee data? This cannot be undone.')) return;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteForm').submit();
}
function deleteAttendee(id) {
  if (!confirm('Remove this attendee?')) return;
  document.getElementById('deleteAttendeeId').value = id;
  document.getElementById('deleteAttendeeForm').submit();
}
window.addEventListener('click', e => {
  if (e.target === document.getElementById('qrModal')) closeQR();
});
</script>
</body>
</html>