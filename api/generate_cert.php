<?php
// ============================================================
// api/generate_cert.php
// Auto-generates pixel-perfect PDF certificates
// ============================================================
require_once '../config.php';
requireLogin();

$attendeeId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT a.*, 
           ts.course_title, ts.training_type, ts.date_conducted,
           ts.facilitator, ts.company, ts.session_code, 
           ts.location, ts.principal,
           c.cert_number
    FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    LEFT JOIN certificates c ON c.attendee_id = a.id
    WHERE a.id = ?
");
$stmt->execute([$attendeeId]);
$row = $stmt->fetch();

if (!$row) die('Attendee not found.');
if (!$row['cert_number']) die('No certificate yet. Please close the session first.');

$date        = new DateTime($row['date_conducted']);
$day         = $date->format('j');
$dayOrd      = ordSuffix((int)$day);
$month       = $date->format('F');
$year        = $date->format('Y');
$fullDate    = $date->format('F d, Y');
$certParts   = explode(' ', $row['cert_number']);
$certSuffix  = end($certParts);

$firstName   = strtoupper(trim($row['given_name']));
$mi          = strtoupper(trim($row['middle_initial']));
$surname     = strtoupper(trim($row['surname']));
$miDot       = $mi ? $mi . '.' : '';
$fullName    = trim($firstName . ' ' . $miDot . ' ' . $surname);
$rank        = strtoupper(trim($row['rank']));
$vessel      = strtoupper(trim($row['vessel']));
$facilitator = strtoupper(trim($row['facilitator']));
$principal   = strtoupper(trim($row['principal'] ?? $vessel));
$type        = $row['training_type'];
$imgDir      = '../cert_images/';

function ordSuffix(int $n): string {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v-20)%10] ?? $s[$v] ?? $s[0]);
}
function b64img(string $path): string {
    if (!file_exists($path)) return '';
    return 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
}
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

require_once '../vendor/autoload.php';

switch ($type) {
    case 'pdos':
        $html = pdfPDOS($firstName, $miDot, $surname, $rank, $principal, $fullDate, $certSuffix, $imgDir);
        $fn   = 'PDOS_' . $surname . '_' . $firstName . '_' . $certSuffix . '.pdf';
        break;
    case 'secat':
        $html = pdfSECAT($fullName, $fullDate, $facilitator, $certSuffix, $imgDir);
        $fn   = 'SECAT_' . $surname . '_' . $firstName . '.pdf';
        break;
    default:
        $html = pdfAntiPiracy($firstName, $miDot, $surname, $day, $dayOrd, $month, $year, $certSuffix, $imgDir);
        $fn   = 'APAT_' . $surname . '_' . $firstName . '_' . $certSuffix . '.pdf';
}

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 0, 'margin_right'  => 0,
    'margin_top'    => 0, 'margin_bottom' => 0,
    'margin_header' => 0, 'margin_footer' => 0,
]);
$mpdf->WriteHTML($html);
$mpdf->Output($fn, 'D');
exit;

// ============================================================
// ANTI-PIRACY CERTIFICATE
// ============================================================
function pdfAntiPiracy($fn, $mi, $sn, $day, $dayOrd, $month, $year, $cert, $d): string {
    $logo = b64img($d.'antipiracy_image1.png');
    $sig1 = b64img($d.'antipiracy_image2.jpg'); // Jay B. Alfaro
    $sig2 = b64img($d.'antipiracy_image3.jpg'); // Capt. Crisando S. Blas

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:"Times New Roman", serif; background:#fff; color:#111; }
.page { width:210mm; min-height:297mm; padding:14mm 18mm 10mm; }
.logo-row { text-align:center; margin-bottom:2mm; }
.logo-row img { height:22mm; }
.address { text-align:center; font-size:8pt; color:#444; margin-bottom:1mm; }
.hr { border:none; border-top:1px solid #888; margin:2mm 0 0; }
.certno { text-align:right; font-size:8.5pt; margin:5mm 0 0; color:#333; line-height:1.6; }
.main-title { text-align:center; font-size:22pt; font-weight:bold; margin:8mm 0 3mm; }
.sub-title { text-align:center; font-size:11pt; color:#444; margin-bottom:5mm; }
.name-wrap { text-align:center; margin:0 5mm 6mm; }
.name-text {
    display:inline-block;
    font-size:22pt; font-weight:bold;
    border-bottom:2px solid #111;
    padding:0 8mm 2mm;
    letter-spacing:1px;
}
.body-p { text-align:center; font-size:10.5pt; color:#333; line-height:1.8; margin-bottom:2mm; }
.training-title {
    text-align:center; font-size:32pt; font-weight:bold;
    line-height:1.1; margin:4mm 0 6mm; letter-spacing:1px;
}
.conducted { text-align:center; font-size:10.5pt; margin-bottom:2mm; line-height:1.8; }
.poea { text-align:justify; font-size:9pt; color:#444; margin:2mm 4mm; line-height:1.7; }
.given { text-align:center; font-size:10.5pt; margin:4mm 0 6mm; line-height:1.8; }

/* ── Side-by-side signatures ── */
.sigs-table { width:100%; border-collapse:collapse; margin-top:4mm; }
.sigs-table td { width:50%; text-align:center; vertical-align:bottom; padding:0 10mm; }
.sig-img { height:22mm; margin-bottom:0; display:block; margin-left:auto; margin-right:auto; }
.sig-line { border-top:1.5px solid #111; padding-top:2mm; margin-top:0; }
.sig-name { font-size:10pt; font-weight:bold; font-style:italic; }
.sig-role { font-size:9pt; font-style:italic; color:#444; }
</style></head><body><div class="page">

<div class="logo-row"><img src="'.e($logo).'"></div>
<div class="address">12<sup>th</sup> Floor Trium Square Building Sen. Gil Puyat Ave. Corner Leveriza, Pasay City 1300</div>
<div class="hr"></div>

<div class="certno">
  CERTIFICATE NUMBER:<br>
  APAT-'.e($cert).'
</div>

<div class="main-title">Certificate of Completion</div>
<div class="sub-title">This certificate is issued to</div>

<div class="name-wrap">
  <span class="name-text">'.e($fn.' '.$mi.' '.$sn).'</span>
</div>

<div class="body-p">for having successfully completed the training requirements in</div>

<div class="training-title">ANTI-PIRACY<br>AWARENESS TRAINING</div>

<div class="conducted">
  Conducted on <strong>'.e($day.' '.$month.' '.$year).'</strong> at <strong>88 Aces Maritime Services Inc.</strong>
</div>
<div class="body-p">and</div>
<div class="poea">
  as per module approved by the Philippine Overseas Employment Administration, pursuant to POEA Governing Board Resolution No.4 and Memorandum Circular No.14, both series of 2009.
</div>
<div class="given">
  Given this <strong>'.e($dayOrd).'</strong> day of <strong>'.e($month.' '.$year).'</strong>, at Manila City, Philippines.
</div>

<table class="sigs-table">
  <tr>
    <td>
      <img class="sig-img" src="'.e($sig1).'">
      <div class="sig-line">
        <div class="sig-name">JAY B. ALFARO</div>
        <div class="sig-role">Accredited Trainor</div>
      </div>
    </td>
    <td>
      <img class="sig-img" src="'.e($sig2).'">
      <div class="sig-line">
        <div class="sig-name">CAPT. CRISANDO S. BLAS</div>
        <div class="sig-role">President</div>
      </div>
    </td>
  </tr>
</table>

</div></body></html>';
}

// ============================================================
// PDOS CERTIFICATE
// ============================================================
function pdfPDOS($fn, $mi, $sn, $rank, $principal, $fullDate, $cert, $d): string {
    $logo    = b64img($d.'pdos_image1.png');
    $pdosImg = b64img($d.'pdos_image2.png');
    $sig2    = b64img($d.'pdos_image3.jpg'); // Capt. Crisando S. Blas

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial, sans-serif; background:#fff; }
.page { width:210mm; min-height:297mm; padding:10mm 15mm; }
.logo-row { text-align:center; margin-bottom:2mm; }
.logo-row img { height:18mm; }
.pdos-wrap { text-align:center; margin:2mm 0; }
.pdos-wrap img { width:62mm; }
.titles h1 { font-size:13pt; font-weight:bold; color:#003087; text-transform:uppercase; text-align:center; letter-spacing:.5px; }
.titles h2 { font-size:11pt; font-weight:bold; color:#003087; text-transform:uppercase; text-align:center; letter-spacing:1px; }
.div-thick { border-top:3px solid #003087; margin:2mm 0; }
.div-thin  { border-top:1px solid #003087; margin:2mm 0 3mm; }
.info { width:100%; border-collapse:collapse; font-size:10pt; margin:2mm 0; }
.info td { padding:2mm 2mm; vertical-align:top; }
.info .lbl { font-weight:bold; color:#003087; width:48%; white-space:nowrap; }
.info .val { font-weight:bold; font-size:10.5pt; border-bottom:1px solid #555; }
.certify { font-size:9.5pt; font-weight:bold; color:#111; line-height:1.8; margin:4mm 0; }
.certify u { text-decoration:underline; }

/* ── Side-by-side signatures ── */
.sigs-table { width:100%; border-collapse:collapse; margin-top:10mm; }
.sigs-table td { width:50%; text-align:center; vertical-align:bottom; padding:0 10mm; }
.sig-img { height:18mm; display:block; margin:0 auto; }
.sig-line { border-top:1.5px solid #111; padding-top:2mm; margin-top:0; }
.sig-name { font-size:10pt; font-weight:bold; text-decoration:underline; }
.sig-role { font-size:9pt; color:#444; }
.sig-space { height:18mm; } /* empty space for left sig (no image) */
</style></head><body><div class="page">

<div class="logo-row"><img src="'.e($logo).'"></div>
<div class="pdos-wrap"><img src="'.e($pdosImg).'"></div>

<div class="titles">
  <h1>Pre-Departure Orientation Seminar</h1>
  <h2>Certificate of Attendance</h2>
</div>

<div class="div-thick"></div>
<div class="div-thin"></div>

<table class="info">
  <tr><td class="lbl">Name of OFW :</td><td class="val">'.e($fn.' '.$mi.' '.$sn).'</td></tr>
  <tr><td class="lbl">Skill / Occupation :</td><td class="val">'.e($rank).'</td></tr>
  <tr><td class="lbl">Country of Destination :</td><td class="val">WORLD WIDE</td></tr>
  <tr><td class="lbl">Local Recruitment Agency :</td><td class="val">88 ACES MARITIME SERVICES INC.</td></tr>
  <tr><td class="lbl">Foreign Principal :</td><td class="val">'.e($principal).'</td></tr>
  <tr><td class="lbl">Foreign Employer :</td><td class="val">'.e($principal).'</td></tr>
</table>

<div class="div-thick"></div>

<div class="certify">
  This certifies that the above named OFW has completed the prescribed requirements
  for the above program, held on <u>'.e($fullDate).'</u> with Certificate No.
  <u>PDOS-'.e($cert).'</u>.
</div>

<table class="sigs-table">
  <tr>
    <td>
      <div class="sig-space"></div>
      <div class="sig-line">
        <div class="sig-name">MR. JAY B. ALFARO</div>
        <div class="sig-role">Accredited Trainor</div>
      </div>
    </td>
    <td>
      <img class="sig-img" src="'.e($sig2).'">
      <div class="sig-line">
        <div class="sig-name">CAPT. CRISANDO S. BLAS</div>
        <div class="sig-role">President</div>
      </div>
    </td>
  </tr>
</table>

</div></body></html>';
}

// ============================================================
// SECAT CERTIFICATE
// ============================================================
function pdfSECAT($fullName, $fullDate, $facilitator, $cert, $d): string {
    $border   = b64img($d.'secat_image1.png');
    $logo88   = b64img($d.'secat_image2.png');
    $secat    = b64img($d.'secat_image3.jpeg');
    $optimumS = b64img($d.'secat_image4.jpeg');
    $optimumM = b64img($d.'secat_image5.png');
    $optra    = b64img($d.'secat_image6.png');

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:"Times New Roman", serif; background:#fff; }
.page { width:210mm; min-height:297mm; padding:16mm 20mm; position:relative; }
.bg { position:absolute; top:0; left:0; width:100%; height:100%; z-index:0; }
.content { position:relative; z-index:1; }
.certno-row { text-align:right; font-size:9pt; color:#333; margin-bottom:2mm; }
.logo-row { text-align:center; margin-bottom:5mm; }
.logo-row img { height:18mm; }
.title { text-align:center; margin-bottom:5mm; }
.title h1 { font-size:22pt; font-weight:normal; color:#111; }
.granted { text-align:center; font-size:11pt; color:#333; margin-bottom:3mm; }
.name-wrap { text-align:center; margin:0 8mm 5mm; }
.name-text {
    display:inline-block;
    font-size:22pt; font-weight:bold; color:#111;
    border-bottom:2px solid #111;
    padding:0 6mm 2mm;
}
.body-p { text-align:center; font-size:11pt; color:#333; line-height:1.8; margin-bottom:2mm; }
.training-type { text-align:center; font-size:13pt; font-weight:bold; color:#111; margin:2mm 0; }
.secat-logo { text-align:center; margin:3mm 0; }
.secat-logo img { height:18mm; }

/* ── Side-by-side signatures ── */
.sigs-table { width:100%; border-collapse:collapse; margin-top:8mm; }
.sigs-table td { width:50%; text-align:center; vertical-align:bottom; padding:0 8mm; }
.sig-space { height:14mm; }
.sig-line { border-top:1.5px solid #111; padding-top:2mm; }
.sig-label { font-size:9.5pt; color:#555; }
.sig-val { font-size:10pt; font-weight:bold; margin-top:1mm; }

.logos-bot { display:flex; justify-content:center; align-items:center; gap:10mm; margin-top:5mm; }
.logos-bot img { height:12mm; }
.footer { text-align:center; font-size:7.5pt; color:#888; margin-top:4mm; }
.footer strong { color:#1a237e; }
</style></head><body><div class="page">
<img class="bg" src="'.e($border).'">
<div class="content">

  <div class="certno-row">Certificate No.: '.e($cert).'</div>
  <div class="logo-row"><img src="'.e($logo88).'"></div>
  <div class="title"><h1>Certificate of Training Completion</h1></div>
  <div class="granted">is hereby granted to</div>

  <div class="name-wrap">
    <span class="name-text">'.e($fullName).'</span>
  </div>

  <div class="body-p">to certify his/her successful completion of the</div>
  <div class="training-type">SHIP EMERGENCY CARE ATTENDANT TRAINING</div>

  <div class="secat-logo"><img src="'.e($secat).'"></div>

  <table class="sigs-table">
    <tr>
      <td>
        <div class="sig-space"></div>
        <div class="sig-line">
          <div class="sig-label">Facilitator</div>
          <div class="sig-val">'.e($facilitator).'</div>
        </div>
      </td>
      <td>
        <div class="sig-space"></div>
        <div class="sig-line">
          <div class="sig-label">Training Date</div>
          <div class="sig-val">'.e($fullDate).'</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="logos-bot">
    <img src="'.e($optimumS).'">
    <img src="'.e($optra).'">
    <img src="'.e($optimumM).'">
  </div>

  <div class="footer">
    <strong>QD-TRA-04 Rev 0 30 November 2022</strong><br>
    *for certificate verification please send an email to: manila.training@shipmanning.net*
  </div>

</div>
</div></body></html>';
}