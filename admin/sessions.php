<?php
require_once '../config.php';
requireLogin();

$db     = getDB();
$admin  = currentAdmin();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$error  = '';

$trainingTypes = [
    'anti_piracy'     => ['label'=>'Anti-Piracy Awareness',                   'icon'=>'⚓','color'=>'#1a4a8a','prefix'=>'APAT', 'title'=>'ANTI-PIRACY AWARENESS TRAINING'],
    'pdos'            => ['label'=>'PDOS (Pre-Departure Orientation Seminar)', 'icon'=>'✈️','color'=>'#16a34a','prefix'=>'PDOS', 'title'=>'PRE-DEPARTURE ORIENTATION SEMINAR'],
    'secat'           => ['label'=>'SECAT (Ship Emergency Care Attendant)',    'icon'=>'🚑','color'=>'#d97706','prefix'=>'SECAT','title'=>'SHIP EMERGENCY CARE ATTENDANT TRAINING'],
    'attendance_only' => ['label'=>'Attendance Only',                          'icon'=>'📋','color'=>'#6b7280','prefix'=>'ATT',  'title'=>'ATTENDANCE'],
];

// Load dropdown data
$facilitators = $db->query("SELECT * FROM facilitators WHERE is_active=1 ORDER BY name ASC")->fetchAll();
$locations    = $db->query("SELECT * FROM locations WHERE is_active=1 ORDER BY is_default DESC, name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $courseTitle   = trim($_POST['course_title']  ?? '');
        $trainingType  = $_POST['training_type']      ?? 'anti_piracy';
        $dateConducted = $_POST['date_conducted']     ?? '';
        $timeStart     = $_POST['time_start']         ?? '';
        $timeEnd       = $_POST['time_end']           ?? '';
        $company       = trim($_POST['company']       ?? '88 ACES MARITIME SERVICES INC.');
        $principal     = trim($_POST['principal']     ?? '');
        $sessionCode   = strtoupper(trim($_POST['session_code'] ?? ''));

        // Handle location — dropdown or new
        $locationSel   = $_POST['location']       ?? '';
        $locationOther = trim($_POST['location_other'] ?? '');
        if ($locationSel === '__other__') {
            $location = $locationOther;
            // Save new location
            if ($location) {
                $chk = $db->prepare("SELECT id FROM locations WHERE name=?");
                $chk->execute([$location]);
                if (!$chk->fetch()) {
                    $db->prepare("INSERT INTO locations (name) VALUES (?)")->execute([$location]);
                }
            }
        } else {
            $location = $locationSel;
        }

        // Handle facilitator — dropdown or new
        $facilitatorSel   = $_POST['facilitator']        ?? '';
        $facilitatorOther = trim($_POST['facilitator_other'] ?? '');
        if ($facilitatorSel === '__other__') {
            $facilitator = $facilitatorOther;
            // Save new facilitator
            if ($facilitator) {
                $chk = $db->prepare("SELECT id FROM facilitators WHERE name=?");
                $chk->execute([$facilitator]);
                if (!$chk->fetch()) {
                    $db->prepare("INSERT INTO facilitators (name) VALUES (?)")->execute([$facilitator]);
                }
            }
        } else {
            $facilitator = $facilitatorSel;
        }

        if (!$courseTitle || !$dateConducted || !$location || !$facilitator) {
            $error = 'Please fill in all required fields.';
        } elseif ($trainingType === 'pdos' && !$principal) {
            $error = 'Foreign Principal / Employer is required for PDOS sessions.';
        } else {
            if ($postAction === 'create') {
                $token = generateToken(16);
                $code  = $sessionCode ?: generateSessionCode();
                $db->prepare(
                    "INSERT INTO training_sessions
                     (session_code,course_title,training_type,date_conducted,time_start,time_end,
                      location,facilitator,company,principal,qr_token,created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([$code,$courseTitle,$trainingType,$dateConducted,$timeStart,$timeEnd,
                             $location,$facilitator,$company,$principal,$token,$admin['id']]);
                $newId = $db->lastInsertId();
                auditLog('CREATE_SESSION','session',$newId,"Code: $code");
                header("Location: sessions.php?action=view&id=$newId&msg=Session+created!");
                exit;
            } else {
                $updateCode = $sessionCode ?: $session['session_code'];
                $db->prepare(
                    "UPDATE training_sessions SET session_code=?,course_title=?,training_type=?,date_conducted=?,
                     time_start=?,time_end=?,location=?,facilitator=?,company=?,principal=? WHERE id=?"
                )->execute([$updateCode,$courseTitle,$trainingType,$dateConducted,$timeStart,$timeEnd,
                             $location,$facilitator,$company,$principal,$id]);
                auditLog('UPDATE_SESSION','session',$id);
                $msg = 'Session updated.';
            }
        }
    }

    if ($postAction === 'toggle_status') {
        $sid = (int)$_POST['session_id'];
        $cur = $db->prepare("SELECT status,training_type FROM training_sessions WHERE id=?");
        $cur->execute([$sid]);
        $current   = $cur->fetch();
        $newStatus = $current['status'] === 'open' ? 'closed' : 'open';
        $db->prepare("UPDATE training_sessions SET status=? WHERE id=?")->execute([$newStatus,$sid]);
        if ($newStatus === 'closed') {
            generateCertsForSession($sid, $db, $admin['id'], $current['training_type']);
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

$session = null;
if ($id) {
    $s = $db->prepare("SELECT * FROM training_sessions WHERE id=?");
    $s->execute([$id]);
    $session = $s->fetch();
}

$attendees = [];
if ($action === 'view' && $session) {
    $a = $db->prepare(
        "SELECT a.*, c.cert_number FROM attendees a
         LEFT JOIN certificates c ON c.attendee_id = a.id
         WHERE a.session_id=? ORDER BY a.submitted_at ASC"
    );
    $a->execute([$id]);
    $attendees = $a->fetchAll();
}

$sessions = [];
if ($action === 'list') {
    $sessions = $db->query(
        "SELECT ts.*, COUNT(a.id) as attendee_count
         FROM training_sessions ts
         LEFT JOIN attendees a ON a.session_id=ts.id
         GROUP BY ts.id ORDER BY ts.created_at DESC"
    )->fetchAll();
}

$msg = $msg ?: sanitize($_GET['msg'] ?? '');

function generateCertsForSession(int $sid, PDO $db, int $adminId, string $type): void {
    global $trainingTypes;
    $prefix    = $trainingTypes[$type]['prefix'] ?? 'CERT';
    $attendees = $db->prepare("SELECT * FROM attendees WHERE session_id=? AND cert_number IS NULL");
    $attendees->execute([$sid]);
    foreach ($attendees->fetchAll() as $a) {
        $year    = date('Y');
        $count   = $db->query("SELECT COUNT(*) FROM certificates WHERE YEAR(generated_at)=$year")->fetchColumn();
        $certNum = $prefix . ' ' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE attendees SET cert_number=?,cert_issued_at=NOW() WHERE id=?")->execute([$certNum,$a['id']]);
        $db->prepare(
            "INSERT INTO certificates (attendee_id,session_id,cert_number,cert_type,generated_by)
             VALUES (?,?,?,'anti_piracy',?) ON DUPLICATE KEY UPDATE cert_number=VALUES(cert_number)"
        )->execute([$a['id'],$sid,$certNum,$adminId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Training Sessions — 88 Aces</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
.type-card{cursor:pointer;border:2px solid #e5e7eb;border-radius:10px;padding:14px 16px;
  display:flex;align-items:center;gap:12px;background:#fff;transition:all .2s;}
.type-card:hover{border-color:#93c5fd;background:#f8faff;}
.type-card.selected{background:#f0f7ff;}
.type-icon{font-size:24px;}
.type-label{font-weight:600;font-size:13px;color:#1f2937;}
.type-sub{font-size:11px;color:#9ca3af;margin-top:2px;}
.principal-field{display:none;animation:fadeIn .3s ease;}
.principal-field.show{display:block;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
select{transition:border-color .2s;}
select:focus{border-color:#1a4a8a!important;box-shadow:0 0 0 3px rgba(26,74,138,.1)!important;outline:none;}
</style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <?php if ($msg):?><div class="alert alert-success"><?=sanitize($msg)?></div><?php endif;?>
    <?php if ($error):?><div class="alert alert-danger"><?=sanitize($error)?></div><?php endif;?>

    <?php if ($action==='list'): ?>
    <!-- ── LIST ── -->
    <div class="page-header">
      <h2>Training Sessions</h2>
      <a href="sessions.php?action=create" class="btn btn-primary">+ New Session</a>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Code</th><th>Course Title</th><th>Type</th><th>Date</th>
                <th>Location</th><th>Attendees</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($sessions as $s):
            $ti = $trainingTypes[$s['training_type']] ?? $trainingTypes['attendance_only'];?>
            <tr>
              <td><code><?=sanitize($s['session_code'])?></code></td>
              <td><?=sanitize($s['course_title'])?></td>
              <td><span style="background:<?=$ti['color']?>22;color:<?=$ti['color']?>;
                    padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">
                  <?=$ti['icon']?> <?=$ti['label']?></span></td>
              <td><?=date('M d, Y',strtotime($s['date_conducted']))?></td>
              <td><?=sanitize($s['location'])?></td>
              <td><span class="badge"><?=$s['attendee_count']?></span></td>
              <td><span class="status-badge <?=$s['status']==='open'?'status-open':'status-closed'?>">
                  <?=ucfirst($s['status'])?></span></td>
              <td class="actions">
                <a href="sessions.php?action=view&id=<?=$s['id']?>" title="View">👁</a>
                <a href="#" onclick="showQR('<?=APP_URL?>/seafarer/form.php?token=<?=$s['qr_token']?>','<?=sanitize($s['course_title'])?>');return false;" title="QR">📱</a>
                <a href="sessions.php?action=edit&id=<?=$s['id']?>" title="Edit">✏️</a>
                <a href="../api/download.php?type=attendance&id=<?=$s['id']?>" title="PDF" target="_blank">⬇PDF</a>
                <a href="../api/excel.php?type=attendance&id=<?=$s['id']?>" title="Excel" target="_blank">📊XLS</a>
                <a href="#" onclick="confirmDelete(<?=$s['id']?>);return false;" title="Delete">🗑</a>
              </td>
            </tr>
          <?php endforeach;?>
          <?php if(empty($sessions)):?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:#9ca3af">No sessions yet.</td></tr>
          <?php endif;?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($action==='create'||($action==='edit'&&$session)): ?>
    <!-- ── CREATE / EDIT ── -->
    <div class="page-header">
      <h2><?=$action==='create'?'+ New Session':'✏ Edit Session'?></h2>
      <a href="sessions.php" class="btn btn-outline">← Back</a>
    </div>
    <div class="card"><div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="<?=$action==='create'?'create':'update'?>">
        <?php if($action==='edit'):?><input type="hidden" name="session_id" value="<?=$id?>"><?php endif;?>

        <div class="form-group" style="margin-bottom:20px">
          <label style="display:block;margin-bottom:10px;font-weight:600;font-size:14px">Training Type *</label>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px">
            <?php foreach($trainingTypes as $tv=>$ti):
              $sel = ($session['training_type']??'anti_piracy')===$tv;?>
            <label class="type-card <?=$sel?'selected':''?>"
                   id="card_<?=$tv?>"
                   style="border-color:<?=$sel?$ti['color']:'#e5e7eb'?>;background:<?=$sel?$ti['color'].'10':'#fff'?>"
                   onclick="selectType('<?=$tv?>','<?=$ti['color']?>','<?=addslashes($ti['title'])?>')">
              <input type="radio" name="training_type" value="<?=$tv?>" <?=$sel?'checked':''?> style="display:none">
              <span class="type-icon"><?=$ti['icon']?></span>
              <div>
                <div class="type-label"><?=$ti['label']?></div>
                <div class="type-sub">Cert prefix: <?=$ti['prefix']?>-YYYY-XXXX</div>
              </div>
            </label>
            <?php endforeach;?>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group full">
            <label>Course Title *</label>
            <input type="text" name="course_title" id="courseTitle"
                   value="<?=sanitize($session['course_title']??'ANTI-PIRACY AWARENESS TRAINING')?>" required>
          </div>
          <div class="form-group">
            <label>Session Code <?=$action==='create'?'<span style="font-size:11px;color:#9ca3af">(leave blank to auto-generate)</span>':''?></label>
            <input type="text" name="session_code"
                   value="<?=sanitize($session['session_code']??'')?>"
                   placeholder="<?=$action==='create'?'Auto: TRN-'.date('Y').'-XXXX':''?>"
                   style="font-family:monospace">
            <?php if($action==='edit'):?>
            <p style="font-size:11px;color:#6b7280;margin-top:4px">
              ⚠️ Changing this will update the next auto-generated code to continue from here.
            </p>
            <?php endif;?>
          </div>
          <div class="form-group">
            <label>Date Conducted *</label>
            <input type="date" name="date_conducted" value="<?=$session['date_conducted']??date('Y-m-d')?>" required>
          </div>
          <div class="form-group">
            <label>Time Start</label>
            <input type="time" name="time_start" value="<?=$session['time_start']??'08:00'?>">
          </div>
          <div class="form-group">
            <label>Time End</label>
            <input type="time" name="time_end" value="<?=$session['time_end']??'17:00'?>">
          </div>

          <!-- LOCATION DROPDOWN -->
          <div class="form-group">
            <label>Location *</label>
            <select name="location" id="locationSelect" onchange="handleLocationChange(this)"
                    style="padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;width:100%;" required>
              <?php foreach($locations as $loc): 
                $currentLoc = $session['location'] ?? '';
                $isSelected = ($currentLoc === $loc['name']) || ($currentLoc === '' && $loc['is_default']);
              ?>
                <option value="<?= sanitize($loc['name']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                  <?= sanitize($loc['name']) ?><?= $loc['is_default'] ? ' (Default)' : '' ?>
                </option>
              <?php endforeach; ?>
              <option value="__other__">+ Add new location...</option>
            </select>
            <input type="text" id="locationOther" name="location_other"
                   placeholder="Type new location name"
                   style="display:none;margin-top:8px;padding:10px 12px;border:1.5px solid #1a4a8a;border-radius:8px;font-size:14px;font-family:inherit;width:100%;outline:none;">
            <p id="locationOtherNote" style="display:none;font-size:11px;color:#16a34a;margin-top:4px">
              ✅ This location will be saved automatically for future sessions.
            </p>
          </div>

          <!-- FACILITATOR DROPDOWN -->
          <div class="form-group">
            <label>Facilitator *</label>
            <select name="facilitator" id="facilitatorSelect" onchange="handleFacilitatorChange(this)"
                    style="padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;width:100%;" required>
              <option value="">— Select Facilitator —</option>
              <?php foreach($facilitators as $fac): ?>
                <option value="<?= sanitize($fac['name']) ?>"
                  <?= ($session['facilitator']??'') === $fac['name'] ? 'selected' : '' ?>>
                  <?= sanitize($fac['name']) ?>
                </option>
              <?php endforeach; ?>
              <option value="__other__">+ Add new facilitator...</option>
            </select>
            <input type="text" id="facilitatorOther" name="facilitator_other"
                   placeholder="Type new facilitator name"
                   style="display:none;margin-top:8px;padding:10px 12px;border:1.5px solid #1a4a8a;border-radius:8px;font-size:14px;font-family:inherit;width:100%;outline:none;">
            <p id="facilitatorOtherNote" style="display:none;font-size:11px;color:#16a34a;margin-top:4px">
              ✅ This facilitator will be saved automatically for future sessions.
            </p>
          </div>

          <div class="form-group">
            <label>Company</label>
            <input type="text" name="company" value="<?=sanitize($session['company']??'88 ACES MARITIME SERVICES INC.')?>">
          </div>

          <div class="form-group full principal-field <?=($session['training_type']??'')==='pdos'?'show':''?>" id="principalField">
            <label style="color:#16a34a;font-weight:700">
              ✈️ Foreign Principal / Employer *
              <span style="font-size:11px;font-weight:400;color:#6b7280">(Required for PDOS — will appear on certificate)</span>
            </label>
            <input type="text" name="principal" id="principalInput"
                   value="<?=sanitize($session['principal']??'')?>"
                   placeholder="e.g. PRINCESS CRUISE LINES LTD.">
            <p style="font-size:11px;color:#6b7280;margin-top:4px">
              This will fill in both "Foreign Principal" and "Foreign Employer" fields on the PDOS certificate.
            </p>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">
            <?=$action==='create'?'✓ Create Session':'✓ Save Changes'?>
          </button>
          <a href="sessions.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div></div>

    <?php elseif($action==='view'&&$session):
      $ti = $trainingTypes[$session['training_type']]??$trainingTypes['attendance_only'];?>
    <!-- ── VIEW SESSION ── -->
    <div class="page-header">
      <div>
        <h2><?=sanitize($session['course_title'])?></h2>
        <p style="color:#6b7280;font-size:14px;margin-top:4px">
          <?=sanitize($session['session_code'])?> ·
          <?=date('F d, Y',strtotime($session['date_conducted']))?> ·
          <span style="background:<?=$ti['color']?>22;color:<?=$ti['color']?>;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600">
            <?=$ti['icon']?> <?=$ti['label']?>
          </span>
          <?php if(!empty($session['principal'])):?>
            · <span style="font-size:12px;color:#16a34a">✈️ <?=sanitize($session['principal'])?></span>
          <?php endif;?>
        </p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="sessions.php" class="btn btn-outline btn-sm">← Back</a>
        <a href="sessions.php?action=edit&id=<?=$id?>" class="btn btn-outline btn-sm">✏ Edit</a>
        <button onclick="showQR('<?=APP_URL?>/seafarer/form.php?token=<?=$session['qr_token']?>','<?=sanitize($session['course_title'])?>')" class="btn btn-outline btn-sm">📱 QR Code</button>
        <a href="../api/download.php?type=attendance&id=<?=$id?>" class="btn btn-primary btn-sm" target="_blank">⬇ Attendance PDF</a>
        <a href="../api/excel.php?type=attendance&id=<?=$id?>" class="btn btn-success btn-sm" target="_blank">📊 Excel</a>
        <?php if($session['status']==='closed'):?>
          <a href="../api/download.php?type=report&id=<?=$id?>" class="btn btn-primary btn-sm" target="_blank">📊 Report</a>
        <?php endif;?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="session_id" value="<?=$id?>">
          <button type="submit" class="btn <?=$session['status']==='open'?'btn-danger':'btn-success'?> btn-sm"
            onclick="return confirm('<?=$session['status']==='open'?'Close session and auto-generate all certificates?':'Reopen session?'?>')">
            <?=$session['status']==='open'?'🔒 Close & Generate Certs':'🔓 Reopen'?>
          </button>
        </form>
      </div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <div class="form-grid">
          <div><strong>Location:</strong> <?=sanitize($session['location'])?></div>
          <div><strong>Facilitator:</strong> <?=sanitize($session['facilitator'])?></div>
          <div><strong>Time:</strong> <?=date('h:i A',strtotime($session['time_start']))?> – <?=date('h:i A',strtotime($session['time_end']))?></div>
          <div><strong>Company:</strong> <?=sanitize($session['company'])?></div>
          <?php if(!empty($session['principal'])):?>
          <div><strong>Principal/Employer:</strong> <?=sanitize($session['principal'])?></div>
          <?php endif;?>
          <div><strong>Status:</strong>
            <span class="status-badge <?=$session['status']==='open'?'status-open':'status-closed'?>">
              <?=ucfirst($session['status'])?>
            </span>
          </div>
          <div style="grid-column:1/-1"><strong>Form Link:</strong>
            <a href="<?=APP_URL?>/seafarer/form.php?token=<?=$session['qr_token']?>" target="_blank" style="font-size:12px">
              <?=APP_URL?>/seafarer/form.php?token=<?=$session['qr_token']?>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Attendees (<?=count($attendees)?>)</h3>
        <?php if($session['status']==='open'):?>
          <span style="font-size:12px;color:#16a34a">● Form is open</span>
        <?php else:?>
          <?php $withCert = count(array_filter($attendees,fn($a)=>$a['cert_number']));?>
          <span style="font-size:12px;color:#6b7280">🔒 Closed · <?=$withCert?>/<?=count($attendees)?> certificates generated</span>
        <?php endif;?>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Surname</th><th>Given Name</th><th>M.I.</th>
                <th>Rank</th><th>Vessel</th><th>Type</th><th>Cert No.</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach($attendees as $i=>$a):?>
            <tr>
              <td><?=$i+1?></td>
              <td><?=sanitize($a['surname'])?></td>
              <td><?=sanitize($a['given_name'])?></td>
              <td><?=sanitize($a['middle_initial'])?></td>
              <td><?=sanitize($a['rank'])?></td>
              <td><?=sanitize($a['vessel'])?></td>
              <td><span class="badge" style="background:#fef3c7;color:#92400e"><?=$a['crew_type']?></span></td>
              <td><?=$a['cert_number']?'<code>'.sanitize($a['cert_number']).'</code>':'<span style="color:#9ca3af">Pending</span>'?></td>
              <td class="actions">
                <?php if($a['cert_number']):?>
                  <a href="../api/generate_cert.php?id=<?=$a['id']?>" title="Download PDF Certificate" target="_blank">🎓 PDF</a>
                <?php endif;?>
                <a href="#" onclick="delAttendee(<?=$a['id']?>);return false;" title="Remove">🗑</a>
              </td>
            </tr>
          <?php endforeach;?>
          <?php if(empty($attendees)):?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#9ca3af">
              No attendees yet. Share the QR code or form link.
            </td></tr>
          <?php endif;?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif;?>

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
      <button onclick="copyLink()" class="btn btn-outline btn-sm">📋 Copy</button>
      <button onclick="window.print()" class="btn btn-primary btn-sm">🖨 Print</button>
      <button onclick="closeQR()" class="btn btn-outline btn-sm">Close</button>
    </div>
  </div>
</div>

<form id="deleteForm" method="POST">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="session_id" id="deleteId">
</form>
<form id="delAttendeeForm" method="POST" action="../api/attendees.php">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="delAttendeeId">
</form>

<script>
const courseTitles = {
  anti_piracy:'ANTI-PIRACY AWARENESS TRAINING',
  pdos:'PRE-DEPARTURE ORIENTATION SEMINAR',
  secat:'SHIP EMERGENCY CARE ATTENDANT TRAINING',
  attendance_only:'ATTENDANCE'
};

function selectType(type, color, title) {
  document.querySelectorAll('.type-card').forEach(card => {
    const radio = card.querySelector('input[type=radio]');
    const isThis = radio.value === type;
    radio.checked = isThis;
    card.classList.toggle('selected', isThis);
    card.style.borderColor = isThis ? color : '#e5e7eb';
    card.style.background  = isThis ? color + '10' : '#fff';
  });
  const t = document.getElementById('courseTitle');
  if (t) t.value = title || courseTitles[type] || '';
  const pf = document.getElementById('principalField');
  const pi = document.getElementById('principalInput');
  if (pf) {
    pf.classList.toggle('show', type === 'pdos');
    if (pi) pi.required = type === 'pdos';
  }
}

function handleLocationChange(sel) {
  const other = document.getElementById('locationOther');
  const note  = document.getElementById('locationOtherNote');
  if (sel.value === '__other__') {
    other.style.display = 'block';
    note.style.display  = 'block';
    other.required = true;
    other.focus();
  } else {
    other.style.display = 'none';
    note.style.display  = 'none';
    other.required = false;
  }
}

function handleFacilitatorChange(sel) {
  const other = document.getElementById('facilitatorOther');
  const note  = document.getElementById('facilitatorOtherNote');
  if (sel.value === '__other__') {
    other.style.display = 'block';
    note.style.display  = 'block';
    other.required = true;
    other.focus();
  } else {
    other.style.display = 'none';
    note.style.display  = 'none';
    other.required = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="training_type"]:checked');
  if (checked) {
    const pf = document.getElementById('principalField');
    const pi = document.getElementById('principalInput');
    if (pf) pf.classList.toggle('show', checked.value === 'pdos');
    if (pi) pi.required = checked.value === 'pdos';
  }
});

let qrInstance = null;
function showQR(url, title) {
  document.getElementById('qrTitle').textContent = title;
  document.getElementById('qrLink').value = url;
  document.getElementById('qrcode').innerHTML = '';
  qrInstance = new QRCode(document.getElementById('qrcode'), {text:url,width:220,height:220});
  document.getElementById('qrModal').classList.add('show');
}
function closeQR() { document.getElementById('qrModal').classList.remove('show'); }
function copyLink() { const i=document.getElementById('qrLink');i.select();document.execCommand('copy');alert('Copied!'); }
function confirmDelete(id) {
  if (!confirm('Delete this session and all its data? Cannot be undone.')) return;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteForm').submit();
}
function delAttendee(id) {
  if (!confirm('Remove this attendee?')) return;
  document.getElementById('delAttendeeId').value = id;
  document.getElementById('delAttendeeForm').submit();
}
window.addEventListener('click', e => {
  if (e.target===document.getElementById('qrModal')) closeQR();
});
</script>
</body>
</html>