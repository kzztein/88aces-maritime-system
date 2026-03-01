<?php
require_once '../config.php';

$token = trim($_GET['token'] ?? '');
if (!$token) {
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:60px">Invalid form link.</h2>');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM training_sessions WHERE qr_token = ? AND status = 'open' LIMIT 1");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Form Closed</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"></head><body style="font-family:Inter,sans-serif;text-align:center;padding:60px;background:#f9fafb">
    <div style="max-width:400px;margin:0 auto;background:#fff;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1)">
    <div style="font-size:48px;margin-bottom:16px">🔒</div>
    <h2 style="color:#1f2937;margin-bottom:8px">Form Closed</h2>
    <p style="color:#6b7280">This training form is no longer accepting submissions. Please contact your administrator.</p>
    </div></body></html>');
}

$successMsg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $surname       = trim($_POST['surname'] ?? '');
    $givenName     = trim($_POST['given_name'] ?? '');
    $middleInitial = trim($_POST['middle_initial'] ?? '');
    $rank          = trim($_POST['rank'] ?? '');
    $vessel        = trim($_POST['vessel'] ?? '');
    $crewType      = $_POST['crew_type'] ?? 'NEW CREW';

    if (!$surname)    $errors[] = 'Surname is required.';
    if (!$givenName)  $errors[] = 'Given name is required.';
    if (!$rank)       $errors[] = 'Rank is required.';
    if (!$vessel)     $errors[] = 'Vessel is required.';

    if (empty($errors)) {
        $stmt = $db->prepare(
            "INSERT INTO attendees (session_id, surname, given_name, middle_initial, rank, vessel, crew_type, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $session['id'], $surname, $givenName, $middleInitial,
            $rank, $vessel, $crewType,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        $successMsg = 'Your attendance has been recorded! Thank you.';
    }
}

$rankOptions = ['Captain/Master','Chief Officer','2nd Officer','3rd Officer','Chief Engineer','2nd Engineer','3rd Engineer','4th Engineer',
    'Bosun','Able Seaman (AB)','Ordinary Seaman (OS)','Deck Cadet','Engine Cadet','Chief Cook (CCK)','Messman (MSM)',
    'Motorman (MST)','Electrician','Oiler (OLR)','Wiper (WPR)','Fitter (FTR)','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= sanitize($session['course_title']) ?> — Attendance Form</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0f2c5c 0%, #1a4a8a 100%);
    min-height: 100vh;
    padding: 20px;
  }
  .form-container {
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
  }
  .form-header {
    background: linear-gradient(135deg, #0f2c5c, #1a4a8a);
    color: #fff;
    padding: 28px 32px;
    text-align: center;
  }
  .form-header .org { font-size: 12px; opacity: .7; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 8px; }
  .form-header h1  { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
  .form-header .meta { font-size: 13px; opacity: .8; }
  .form-body { padding: 32px; }
  .form-section { margin-bottom: 28px; }
  .form-section h3 {
    font-size: 14px; font-weight: 600; color: #374151;
    text-transform: uppercase; letter-spacing: .05em;
    padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;
    margin-bottom: 16px;
  }
  .form-group { margin-bottom: 18px; }
  .form-group label {
    display: block; font-size: 13px; font-weight: 500;
    color: #374151; margin-bottom: 6px;
  }
  .required::after { content: ' *'; color: #dc2626; }
  .form-group input,
  .form-group select {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid #e5e7eb; border-radius: 8px;
    font-size: 15px; font-family: inherit;
    outline: none; transition: border-color .2s;
    background: #fff;
  }
  .form-group input:focus,
  .form-group select:focus {
    border-color: #1a4a8a;
    box-shadow: 0 0 0 3px rgba(26,74,138,.1);
  }
  .form-row { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
  .radio-group { display: flex; gap: 20px; margin-top: 6px; }
  .radio-item {
    display: flex; align-items: center; gap: 8px;
    cursor: pointer; font-size: 14px; color: #374151;
  }
  .radio-item input[type=radio] { width: 18px; height: 18px; cursor: pointer; accent-color: #1a4a8a; }
  .btn-submit {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #1a4a8a, #0f2c5c);
    color: #fff; border: none; border-radius: 10px;
    font-size: 16px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: opacity .2s;
    letter-spacing: .02em;
  }
  .btn-submit:hover { opacity: .9; }
  .error-box {
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 8px; padding: 14px; margin-bottom: 20px;
    color: #dc2626; font-size: 13px;
  }
  .error-box ul { margin-left: 16px; }
  .success-box {
    background: #dcfce7; border: 1px solid #86efac;
    border-radius: 12px; padding: 32px;
    text-align: center; color: #166534;
  }
  .success-box .check { font-size: 56px; margin-bottom: 12px; }
  .success-box h2 { font-size: 22px; margin-bottom: 8px; }
  .success-box p  { font-size: 14px; opacity: .8; }
  .form-footer {
    text-align: center; padding: 16px;
    font-size: 12px; color: #9ca3af;
    border-top: 1px solid #f3f4f6;
  }
</style>
</head>
<body>
<div class="form-container">
  <div class="form-header">
    <div class="org">88 Aces Maritime Services Inc.</div>
    <h1><?= sanitize($session['course_title']) ?></h1>
    <div class="meta">
      📅 <?= date('F d, Y', strtotime($session['date_conducted'])) ?>
      &nbsp;·&nbsp; 📍 <?= sanitize($session['location']) ?>
    </div>
  </div>

  <div class="form-body">
    <?php if ($successMsg): ?>
      <div class="success-box">
        <div class="check">✅</div>
        <h2>Attendance Recorded!</h2>
        <p><?= sanitize($successMsg) ?></p>
        <p style="margin-top:12px">You may now close this page.</p>
      </div>
    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <strong>Please fix the following errors:</strong>
          <ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="form-section">
          <h3>📋 Personal Information</h3>

          <div class="form-row">
            <div class="form-group">
              <label class="required">Surname</label>
              <input type="text" name="surname" value="<?= sanitize($_POST['surname'] ?? '') ?>"
                     placeholder="e.g. DELA CRUZ" required>
            </div>
            <div class="form-group">
              <label>Middle Initial</label>
              <input type="text" name="middle_initial" value="<?= sanitize($_POST['middle_initial'] ?? '') ?>"
                     placeholder="e.g. M" maxlength="5">
            </div>
          </div>

          <div class="form-group">
            <label class="required">Given Name</label>
            <input type="text" name="given_name" value="<?= sanitize($_POST['given_name'] ?? '') ?>"
                   placeholder="e.g. JUAN" required>
          </div>
        </div>

        <div class="form-section">
          <h3>🚢 Vessel Information</h3>

          <div class="form-group">
            <label class="required">Rank / Position</label>
            <select name="rank" required>
              <option value="">— Select your rank —</option>
              <?php foreach ($rankOptions as $r): ?>
                <option value="<?= $r ?>" <?= ($_POST['rank'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="required">Vessel Name</label>
            <input type="text" name="vessel" value="<?= sanitize($_POST['vessel'] ?? '') ?>"
                   placeholder="e.g. MV CARAVOS LIBERTY" required>
          </div>

          <div class="form-group">
            <label>Crew Type</label>
            <div class="radio-group">
              <label class="radio-item">
                <input type="radio" name="crew_type" value="NEW CREW" <?= ($_POST['crew_type'] ?? 'NEW CREW') === 'NEW CREW' ? 'checked' : '' ?>>
                New Crew
              </label>
              <label class="radio-item">
                <input type="radio" name="crew_type" value="EX CREW"  <?= ($_POST['crew_type'] ?? '') === 'EX CREW'  ? 'checked' : '' ?>>
                Ex Crew (returning)
              </label>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">✓ Submit Attendance</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="form-footer">QD-TRA-02 Rev.1 · 88 Aces Maritime Services Inc. · Pasay City, Philippines</div>
</div>
</body>
</html>