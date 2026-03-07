<?php
/**
 * api/download.php
 * Generates PDF downloads for attendance sheets, certificates, and reports.
 * Uses mPDF (install via: composer require mpdf/mpdf)
 * Falls back to basic HTML if mPDF not available.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
if (!isLoggedIn()) { header('Location: ../admin/login.php'); exit; };

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$db   = getDB();

switch ($type) {
    case 'attendance': generateAttendancePDF($id, $db); break;
    case 'certificate': generateCertificatePDF($id, $db); break;
    case 'report':     generateReportPDF($id, $db); break;
    default: die('Invalid download type.');
}

// ── ATTENDANCE SHEET PDF ─────────────────────────────────────
function generateAttendancePDF(int $sessionId, PDO $db): void {
    $stmt = $db->prepare("SELECT * FROM training_sessions WHERE id=?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) die('Session not found.');

    $stmt = $db->prepare("SELECT * FROM attendees WHERE session_id=? ORDER BY submitted_at ASC");
    $stmt->execute([$sessionId]);
    $attendees = $stmt->fetchAll();

    $html = buildAttendanceHTML($session, $attendees);
    outputPDF($html, 'Attendance_' . $session['session_code'] . '.pdf', 'L'); // Landscape
}

// ── CERTIFICATE PDF ──────────────────────────────────────────
function generateCertificatePDF(int $attendeeId, PDO $db): void {
    $stmt = $db->prepare(
        "SELECT a.*, ts.*, c.cert_number as cert_no, ts.date_conducted
         FROM attendees a
         JOIN training_sessions ts ON ts.id = a.session_id
         LEFT JOIN certificates c ON c.attendee_id = a.id
         WHERE a.id=?"
    );
    $stmt->execute([$attendeeId]);
    $data = $stmt->fetch();
    if (!$data) die('Attendee not found.');

    $html = buildCertificateHTML($data);
    outputPDF($html, 'Certificate_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data['cert_no'] ?? 'cert') . '.pdf');
}

// ── MONTHLY REPORT PDF ───────────────────────────────────────
function generateReportPDF(int $sessionId, PDO $db): void {
    $stmt = $db->prepare("SELECT * FROM training_sessions WHERE id=?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) die('Session not found.');

    $stmt = $db->prepare(
        "SELECT a.*, c.cert_number as cert_no FROM attendees a
         LEFT JOIN certificates c ON c.attendee_id = a.id
         WHERE a.session_id=? ORDER BY a.submitted_at ASC"
    );
    $stmt->execute([$sessionId]);
    $attendees = $stmt->fetchAll();

    $html = buildReportHTML($session, $attendees);
    outputPDF($html, 'Report_' . $session['session_code'] . '.pdf', 'L');
}

// ── OUTPUT ───────────────────────────────────────────────────
function outputPDF(string $html, string $filename, string $orientation = 'P'): void {
    $mpdfPath = __DIR__ . '/../vendor/mpdf/mpdf/src/Mpdf.php';
    if (file_exists($mpdfPath)) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf([
            'orientation' => $orientation,
            'margin_top'  => 10,
            'margin_bottom'=> 10,
            'margin_left' => 10,
            'margin_right'=> 10,
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, 'D');
    } else {
        // Fallback: serve as printable HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $html . '<script>window.onload=function(){window.print()}</script>';
    }
    exit;
}

// ── HTML BUILDERS ────────────────────────────────────────────
function buildAttendanceHTML(array $session, array $attendees): string {
    $date       = date('F d, Y', strtotime($session['date_conducted']));
    $time       = date('h:i A', strtotime($session['time_start'])) . ' - ' . date('h:i A', strtotime($session['time_end']));
    $rows       = '';
    $maxRows    = max(20, count($attendees));

    for ($i = 0; $i < $maxRows; $i++) {
        $a = $attendees[$i] ?? null;
        $rows .= '<tr>
            <td style="text-align:center">' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($a['surname']       ?? '', ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['given_name']    ?? '', ENT_QUOTES) . '</td>
            <td style="text-align:center">' . htmlspecialchars($a['middle_initial'] ?? '', ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['rank']          ?? '', ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['vessel']        ?? '', ENT_QUOTES) . '</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
      body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; }
      .header { text-align: center; margin-bottom: 10px; }
      .header img { max-height: 60px; }
      .header h2 { font-size: 13px; margin: 4px 0; text-transform: uppercase; }
      .info-table { width: 100%; margin-bottom: 8px; font-size: 10px; }
      .info-table td { padding: 2px 6px; }
      .label { font-weight: bold; width: 120px; }
      table.main { width: 100%; border-collapse: collapse; font-size: 9px; }
      table.main th, table.main td { border: 1px solid #333; padding: 4px 6px; }
      table.main th { background: #1a4a8a; color: #fff; text-align: center; font-size: 9px; }
      table.main td { min-height: 18px; height: 18px; }
      .sig-section { margin-top: 16px; display: flex; justify-content: space-between; }
      .sig-box { text-align: center; width: 200px; }
      .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; font-size: 9px; }
    </style></head><body>
    <div class="header">
      <h2>88 Aces Maritime Services Inc.</h2>
      <p>12th Floor Trium Square Building Sen. Gil Puyat Ave. Corner Leveriza, Pasay City 1300</p>
      <h2 style="margin-top:8px">TRAINING ATTENDANCE SHEET</h2>
      <p>Form No.: QD-TRA-02 Rev.1</p>
    </div>
    <table class="info-table">
      <tr>
        <td class="label">COURSE TITLE:</td><td colspan="3">' . htmlspecialchars($session['course_title'], ENT_QUOTES) . '</td>
      </tr>
      <tr>
        <td class="label">DATE &amp; TIME:</td><td>' . $date . ' | ' . $time . '</td>
        <td class="label">LOCATION:</td><td>' . htmlspecialchars($session['location'], ENT_QUOTES) . '</td>
      </tr>
    </table>
    <table class="main">
      <thead>
        <tr>
          <th style="width:30px">No.</th>
          <th>Surname</th>
          <th>Given Name</th>
          <th style="width:30px">M.I.</th>
          <th>Rank</th>
          <th>Vessel</th>
          <th style="width:80px">Signature</th>
          <th style="width:80px">Principal</th>
          <th style="width:60px">Remarks</th>
        </tr>
      </thead>
      <tbody>' . $rows . '</tbody>
    </table>
    <div class="sig-section">
      <div class="sig-box"><div class="sig-line">Facilitator: ' . htmlspecialchars($session['facilitator'], ENT_QUOTES) . '</div></div>
      <div class="sig-box"><div class="sig-line">Date: ' . $date . '</div></div>
    </div>
    </body></html>';
}

function buildCertificateHTML(array $data): string {
    $fullName   = strtoupper($data['given_name'] . ' ' . $data['middle_initial'] . '. ' . $data['surname']);
    $date       = date('F d, Y', strtotime($data['date_conducted']));
    $day        = date('j', strtotime($data['date_conducted']));
    $month      = date('F', strtotime($data['date_conducted']));
    $year       = date('Y', strtotime($data['date_conducted']));
    $certNo     = $data['cert_no'] ?? 'PENDING';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
      @page { margin: 0; }
      body { font-family: "Times New Roman", serif; margin: 0; padding: 40px 60px; background: #fff; }
      .cert-container { border: 8px double #1a4a8a; padding: 40px; min-height: 550px; text-align: center; }
      .cert-header { font-size: 12px; color: #555; margin-bottom: 8px; }
      .cert-org { font-size: 20px; font-weight: bold; color: #0f2c5c; letter-spacing: 2px; text-transform: uppercase; }
      .cert-number { font-size: 11px; color: #888; margin: 4px 0 20px; }
      .cert-title  { font-size: 36px; font-weight: bold; color: #1a4a8a; margin: 12px 0; letter-spacing: 3px; }
      .cert-body   { font-size: 14px; color: #333; margin: 12px 0; line-height: 1.8; }
      .cert-name   { font-size: 28px; font-weight: bold; color: #0f2c5c; border-bottom: 2px solid #1a4a8a;
                     display: inline-block; padding: 0 20px 4px; margin: 8px 0; font-style: italic; }
      .cert-course { font-size: 16px; font-weight: bold; color: #1a4a8a; text-transform: uppercase; margin: 12px 0; }
      .cert-details { font-size: 12px; color: #555; margin: 8px 0; }
      .signatures  { display: flex; justify-content: space-around; margin-top: 48px; }
      .sig-item    { text-align: center; width: 200px; }
      .sig-name    { font-weight: bold; font-size: 13px; border-top: 1px solid #333; padding-top: 6px; margin-top: 40px; }
      .sig-role    { font-size: 11px; color: #666; }
    </style></head><body>
    <div class="cert-container">
      <div class="cert-header">88 Aces Maritime Services Inc.<br>12th Floor Trium Square Building Sen. Gil Puyat Ave. Corner Leveriza, Pasay City 1300</div>
      <div class="cert-number">CERTIFICATE NUMBER: ' . htmlspecialchars($certNo, ENT_QUOTES) . '</div>
      <div class="cert-title">Certificate of Completion</div>
      <div class="cert-body">This certificate is issued to</div>
      <div class="cert-name">' . htmlspecialchars($fullName, ENT_QUOTES) . '</div>
      <div class="cert-body">for having successfully completed the training requirements in</div>
      <div class="cert-course">' . htmlspecialchars($data['course_title'], ENT_QUOTES) . '</div>
      <div class="cert-details">
        Conducted on ' . $date . ' at 88 Aces Maritime Services Inc.<br>
        As per module approved by the Philippine Overseas Employment Administration,<br>
        pursuant to POEA Governing Board Resolution No.4 and Memorandum Circular No.14, both series of 2009.
      </div>
      <div class="cert-details" style="margin-top:16px">Given this ' . $day . 'th day of ' . $month . ' ' . $year . ', at Manila City, Philippines.</div>
      <div class="signatures">
        <div class="sig-item">
          <div class="sig-name">JAY B. ALFARO</div>
          <div class="sig-role">Accredited Trainor</div>
        </div>
        <div class="sig-item">
          <div class="sig-name">CAPT. CRISANDO S. BLAS</div>
          <div class="sig-role">President</div>
        </div>
      </div>
    </div>
    </body></html>';
}

function buildReportHTML(array $session, array $attendees): string {
    $month  = date('F Y', strtotime($session['date_conducted']));
    $rows   = '';
    foreach ($attendees as $i => $a) {
        $rows .= '<tr>
            <td style="text-align:center">' . ($i+1) . '</td>
            <td>' . htmlspecialchars($a['cert_no'] ?? '-', ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['rank'], ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['surname'] . ', ' . $a['given_name'] . ', ' . $a['middle_initial'], ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['crew_type'], ENT_QUOTES) . '</td>
            <td>' . htmlspecialchars($a['vessel'], ENT_QUOTES) . '</td>
            <td>' . date('M d, Y', strtotime($session['date_conducted'])) . '</td>
            <td>88 ACES</td>
        </tr>';
    }
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
      body { font-family: Arial, sans-serif; font-size: 9px; }
      h2 { text-align: center; font-size: 12px; text-transform: uppercase; }
      p  { text-align: center; font-size: 10px; }
      table { width: 100%; border-collapse: collapse; margin-top: 12px; }
      th { background: #1a4a8a; color: #fff; padding: 5px; font-size: 9px; }
      td { border: 1px solid #ccc; padding: 4px 6px; }
    </style></head><body>
    <h2>Anti Piracy Awareness Training Report</h2>
    <p>For the Month of ' . $month . '</p>
    <table>
      <thead>
        <tr><th>NO.</th><th>CERT NO.</th><th>RANK</th><th>FULL NAME (LN, FN, MN)</th><th>EX/NEW CREW</th><th>VESSEL</th><th>DATE CONDUCTED</th><th>COMPANY</th></tr>
      </thead>
      <tbody>' . $rows . '</tbody>
    </table>
    </body></html>';
}
