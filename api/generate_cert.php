<?php
require_once '../config.php';
requireLogin();

$attendeeId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT a.*,
           ts.course_title, ts.training_type, ts.date_conducted,
           ts.facilitator, ts.company, ts.session_code,
           ts.location, ts.principal, ts.cert_validity_years,
           c.cert_number
    FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    LEFT JOIN certificates c ON c.attendee_id = a.id
    WHERE a.id = ?
");
$stmt->execute([$attendeeId]);
$row = $stmt->fetch();

if (!$row) die('Attendee not found.');
if (!$row['cert_number']) die('No certificate yet. Close the session first.');

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
    $s = array('th','st','nd','rd');
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}

function b64img(string $path): string {
    if (!file_exists($path)) return '';
    return 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

require_once '../vendor/autoload.php';

if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "training_type: [" . $type . "]\n";
    echo "cert_number: " . $row['cert_number'] . "\n";
    echo "principal: " . $row['principal'] . "\n";
    exit;
}

switch ($type) {
    case 'pdos':
        $html    = buildPDOS($firstName, $miDot, $surname, $rank, $principal, $fullDate, $certSuffix, $imgDir);
        $fn      = 'PDOS_' . $surname . '_' . $firstName . '_' . $certSuffix . '.pdf';
        $wmark   = $imgDir . 'pdos_image2.png';
        break;
    case 'secat':
        $html  = buildSECAT($fullName, $fullDate, $facilitator, $certSuffix, $imgDir, (int)($row['cert_validity_years'] ?? 2), $row['date_conducted']);
        $fn    = 'SECAT_' . $surname . '_' . $firstName . '.pdf';
        $wmark = '';
        break;
    default:
        $html  = buildAntiPiracy($firstName, $miDot, $surname, $day, $dayOrd, $month, $year, $certSuffix, $imgDir);
        $fn    = 'APAT_' . $surname . '_' . $firstName . '_' . $certSuffix . '.pdf';
        $wmark = '';
}

$mpdf = new \Mpdf\Mpdf(array(
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 0,
    'margin_right'  => 0,
    'margin_top'    => 0,
    'margin_bottom' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
));

if (!empty($wmark) && file_exists($wmark)) {
    $mpdf->SetWatermarkImage($wmark, 0.15, array(150, 115), 'P');
    $mpdf->showWatermarkImage = true;
}

$mpdf->WriteHTML($html);
$mpdf->Output($fn, 'D');
exit;

// ============================================================
// ANTI-PIRACY
// ============================================================
function buildAntiPiracy($fn, $mi, $sn, $day, $dayOrd, $month, $year, $cert, $d) {
    $logo = b64img($d . 'antipiracy_image1.png');
    $sig1 = b64img($d . 'antipiracy_image2.jpg');
    $sig2 = b64img($d . 'antipiracy_image3.jpg');

    $name      = esc($fn . ' ' . $mi . ' ' . $sn);
    $certNo    = esc($cert);
    $conducted = esc($day . ' ' . $month . ' ' . $year);
    $givenDay  = esc($dayOrd);
    $givenDate = esc($month . ' ' . $year);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Georgia,serif;background:#fff;color:#111;}
.page{width:210mm;min-height:297mm;padding:14mm 18mm 10mm;}
.logo-row{text-align:center;margin-bottom:2mm;}
.logo-row img{height:22mm;}
.address{text-align:center;font-size:8pt;color:#444;margin-bottom:1mm;}
.hr{border:none;border-top:1px solid #888;margin:2mm 0 0;}
.certno{text-align:right;font-size:8.5pt;margin:5mm 0 0;color:#333;line-height:1.6;}
.main-title{text-align:center;font-size:22pt;font-weight:bold;margin:8mm 0 3mm;}
.sub-title{text-align:center;font-size:11pt;color:#444;margin-bottom:5mm;}
.name-wrap{text-align:center;margin:0 5mm 6mm;}
.name-text{display:inline-block;font-size:22pt;font-weight:bold;border-bottom:2px solid #111;padding:0 8mm 2mm;letter-spacing:1px;}
.body-p{text-align:center;font-size:10.5pt;color:#333;line-height:1.8;margin-bottom:2mm;}
.training-title{text-align:center;font-size:32pt;font-weight:bold;line-height:1.1;margin:4mm 0 6mm;letter-spacing:1px;}
.conducted{text-align:center;font-size:10.5pt;margin-bottom:2mm;line-height:1.8;}
.poea{text-align:justify;font-size:9pt;color:#444;margin:2mm 4mm;line-height:1.7;}
.given{text-align:center;font-size:10.5pt;margin:4mm 0 6mm;line-height:1.8;}
.sigs-table{width:100%;border-collapse:collapse;margin-top:4mm;}
.sigs-table td{width:50%;text-align:center;vertical-align:bottom;padding:0 10mm;}
.sig-img{height:22mm;display:block;margin:0 auto;}
.sig-line{border-top:1.5px solid #111;padding-top:2mm;}
.sig-name{font-size:10pt;font-weight:bold;font-style:italic;}
.sig-role{font-size:9pt;font-style:italic;color:#444;}
</style></head><body><div class="page">
<div class="logo-row"><img src="' . $logo . '"></div>
<div class="address">12<sup>th</sup> Floor Trium Square Building Sen. Gil Puyat Ave. Corner Leveriza, Pasay City 1300</div>
<div class="hr"></div>
<div class="certno">CERTIFICATE NUMBER:<br>APAT-' . $certNo . '</div>
<div class="main-title">Certificate of Completion</div>
<div class="sub-title">This certificate is issued to</div>
<div class="name-wrap"><span class="name-text">' . $name . '</span></div>
<div class="body-p">for having successfully completed the training requirements in</div>
<div class="training-title">ANTI-PIRACY<br>AWARENESS TRAINING</div>
<div class="conducted">Conducted on <strong>' . $conducted . '</strong> at <strong>88 Aces Maritime Services Inc.</strong></div>
<div class="body-p">and</div>
<div class="poea">as per module approved by the Philippine Overseas Employment Administration, pursuant to POEA Governing Board Resolution No.4 and Memorandum Circular No.14, both series of 2009.</div>
<div class="given">Given this <strong>' . $givenDay . '</strong> day of <strong>' . $givenDate . '</strong>, at Manila City, Philippines.</div>
<table class="sigs-table"><tr>
  <td><img class="sig-img" src="' . $sig1 . '"><div class="sig-line"><div class="sig-name">JAY B. ALFARO</div><div class="sig-role">Accredited Trainor</div></div></td>
  <td><img class="sig-img" src="' . $sig2 . '"><div class="sig-line"><div class="sig-name">CAPT. CRISANDO S. BLAS</div><div class="sig-role">President</div></div></td>
</tr></table>
</div></body></html>';

    return $html;
}

// ============================================================
// PDOS
// ============================================================
function buildPDOS($fn, $mi, $sn, $rank, $principal, $fullDate, $cert, $d) {
    $logo   = b64img($d . 'pdos_image1.png');
    $pdosBg = b64img($d . 'pdos_image2.png');
    $sig2   = b64img($d . 'pdos_image3.jpg');

    $nameVal      = esc($fn . ' ' . $mi . ' ' . $sn);
    $rankVal      = esc($rank);
    $principalVal = esc($principal);
    $dateVal      = esc($fullDate);
    $certNo       = esc($cert);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;background:#fff;color:#000;}
.page{width:210mm;min-height:297mm;padding:8mm 16mm 10mm;position:relative;}
.logo-wrap{text-align:center;margin-bottom:2mm;}
.logo-wrap img{height:22mm;}
.dept-text{font-size:8.5pt;color:#555;font-style:italic;letter-spacing:.3px;text-align:center;margin-bottom:1mm;}
.seminar-text{font-size:13pt;font-weight:900;color:#003087;text-transform:uppercase;letter-spacing:.5px;text-align:center;margin-bottom:1mm;}
.attend-text{font-size:11pt;font-weight:bold;color:#c0392b;text-transform:uppercase;letter-spacing:1px;text-decoration:underline;text-align:center;margin-bottom:2mm;}
.div-thick{border-top:3px solid #003087;margin:2mm 0 1mm;}
.div-thin{border-top:1px solid #003087;margin:0 0 2mm;}
.info-table{width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:2mm;}
.info-table td{padding:1.8mm 1mm;vertical-align:bottom;}
.lbl{font-weight:bold;color:#000;width:52mm;white-space:nowrap;}
.col{width:5mm;text-align:center;font-weight:bold;}
.val{font-weight:bold;border-bottom:1px solid #000;font-size:10pt;}
.certify{font-size:9pt;font-weight:bold;color:#000;line-height:1.9;margin:3mm 0;text-align:justify;}
.sigs-table{width:100%;border-collapse:collapse;margin-top:6mm;}
.sigs-table td{width:50%;vertical-align:bottom;text-align:center;padding:0 8mm;}
.sig-spacer{height:20mm;}
.sig-img{height:16mm;display:block;margin:0 auto;}
.sig-name-line{padding-top:1mm;}
.sig-name{font-size:10pt;font-weight:bold;text-decoration:underline;color:#000;}
.sig-role{font-size:9pt;color:#333;font-style:italic;}
</style></head><body><div class="page">
<div class="logo-wrap"><img src="' . $logo . '"></div>
<div class="dept-text">DEPARTMENT OF LABOR AND EMPLOYMENT</div>
<div class="seminar-text">PRE-DEPARTURE ORIENTATION SEMINAR</div>
<div class="attend-text">CERTIFICATE OF ATTENDANCE</div>
<div class="div-thick"></div>
<div class="div-thin"></div>
<table class="info-table">
  <tr><td class="lbl">Name of OFW</td><td class="col">:</td><td class="val">' . $nameVal . '</td></tr>
  <tr><td class="lbl">Skill / Occupation</td><td class="col">:</td><td class="val">' . $rankVal . '</td></tr>
  <tr><td class="lbl">Country of Destination</td><td class="col">:</td><td class="val">WORLD WIDE</td></tr>
  <tr><td class="lbl">Local Recruitment Agency</td><td class="col">:</td><td class="val">88 ACES MARITIME SERVICES INC.</td></tr>
  <tr><td class="lbl">Foreign Principal</td><td class="col">:</td><td class="val">' . $principalVal . '</td></tr>
  <tr><td class="lbl">Foreign Employer</td><td class="col">:</td><td class="val">' . $principalVal . '</td></tr>
</table>
<div class="certify">This certifies that the above named OFW has completed the prescribed requirements for the above program, held on <u>' . $dateVal . '</u> with Certificate No. <u>PDOS-' . $certNo . '</u>.</div>
<table class="sigs-table"><tr>
  <td><div class="sig-spacer"></div><div class="sig-name-line"><div class="sig-name">MR. JAY B. ALFARO</div><div class="sig-role">Accredited Trainor</div></div></td>
  <td><img class="sig-img" src="' . $sig2 . '"><div class="sig-name-line"><div class="sig-name">CAPT. CRISANDO S. BLAS</div><div class="sig-role">President</div></div></td>
</tr></table>
</div></body></html>';

    return $html;
}

// ============================================================
// SECAT — fixed layout matching original template
// ============================================================
function buildSECAT($fullName, $fullDate, $facilitator, $cert, $d, $validityYears = 2, $dateConducted = '') {
    $border   = b64img($d . 'secat_image1.png');
    $logo88   = b64img($d . 'secat_image2.png');
    $secat    = b64img($d . 'secat_image3.jpeg');
    $optimumS = b64img($d . 'secat_image4.jpeg');
    $optimumM = b64img($d . 'secat_image5.png');
    $optra    = b64img($d . 'secat_image6.png');

    $nameVal  = esc($fullName);
    $certNo   = esc($cert);
    $facVal   = esc($facilitator);
    $dateVal  = esc($fullDate);

    // Compute valid until
    $validUntil = '';
    if ($dateConducted) {
        $expiry     = new DateTime($dateConducted);
        $expiry->modify("+{$validityYears} years");
        $validUntil = $expiry->format('F d, Y');
    }

    $borderStyle = $border ? 'background-image:url("' . $border . '");background-size:100% 100%;background-repeat:no-repeat;' : '';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Georgia,serif;background:#fff;' . $borderStyle . '}
.page{width:210mm;min-height:297mm;padding:18mm 20mm 10mm;}
.certno-row{text-align:right;font-size:8.5pt;color:#333;margin-bottom:3mm;}
.logo-row{text-align:center;margin-bottom:3mm;}
.logo-row img{height:16mm;}
.title{text-align:center;font-size:17pt;font-weight:normal;color:#111;margin-bottom:3mm;}
.granted{text-align:center;font-size:10pt;color:#333;margin-bottom:2mm;}
.name-wrap{text-align:center;margin:0 8mm 3mm;}
.name-text{display:inline-block;font-size:17pt;font-weight:bold;color:#111;border-bottom:2px solid #111;padding:0 6mm 1.5mm;letter-spacing:0.5px;}
.body-p{text-align:center;font-size:9.5pt;color:#333;line-height:1.6;margin-bottom:2mm;}
.training-type{text-align:center;font-size:11pt;font-weight:bold;color:#111;text-transform:uppercase;margin:2mm 0 2mm;}
.secat-logo{text-align:center;margin:2mm 0 3mm;}
.secat-logo img{height:14mm;}
.sigs-table{width:100%;border-collapse:collapse;margin-top:4mm;}
.sigs-table td{width:50%;text-align:center;vertical-align:bottom;padding:0 10mm;}
.sig-space{height:10mm;}
.sig-line{border-top:1.5px solid #111;padding-top:2mm;}
.sig-label{font-size:8pt;color:#555;margin-bottom:1mm;}
.sig-val{font-size:9pt;font-weight:bold;}
.sig-sub{font-size:7.5pt;color:#555;margin-top:1mm;}
.logos-bot{text-align:center;margin-top:5mm;}
.logos-bot img{height:9mm;margin:0 3mm;vertical-align:middle;display:inline-block;}
.footer{text-align:center;font-size:6.5pt;color:#666;margin-top:3mm;line-height:1.5;}
.footer strong{color:#1a237e;}
</style></head><body>
<div class="page">
  <div class="certno-row">Certificate No.: <strong>' . $certNo . '</strong></div>
  <div class="logo-row"><img src="' . $logo88 . '"></div>
  <div class="title">Certificate of Training Completion</div>
  <div class="granted">is hereby granted to</div>
  <div class="name-wrap"><span class="name-text">' . $nameVal . '</span></div>
  <div class="body-p">to certify his/her successful completion of the</div>
  <div class="training-type">Ship Emergency Care Attendant Training</div>
  <div class="secat-logo"><img src="' . $secat . '"></div>
  <table class="sigs-table"><tr>
    <td>
      <div class="sig-space"></div>
      <div class="sig-line">
        <div class="sig-label">Facilitator</div>
        <div class="sig-val">' . $facVal . '</div>
      </div>
    </td>
    <td>
      <div class="sig-space"></div>
      <div class="sig-line">
        <div class="sig-label">Training Date</div>
        <div class="sig-val">' . $dateVal . '</div>
        ' . ($validUntil ? '<div class="sig-sub">VALID UNTIL: ' . esc($validUntil) . '</div>' : '') . '
      </div>
    </td>
  </tr></table>
  <div class="logos-bot">
    <img src="' . $optimumS . '"><img src="' . $optra . '"><img src="' . $optimumM . '">
  </div>
  <div class="footer">
    <strong>QD-TRA-04 Rev 0 30 November 2022</strong><br>
    *for certificate verification please send an email to: manila.training@shipmanning.net*
  </div>
</div>
</body></html>';

    return $html;
}
