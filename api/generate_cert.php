<?php
/**
 * api/generate_cert.php
 * Generates pixel-perfect certificates using the original PDF templates as backgrounds.
 * Uses mPDF to overlay text on top of the template images.
 *
 * Strategy: Convert each template PDF page to an image (done once, cached),
 * then use mPDF to place that image as full-page background and overlay
 * all dynamic fields at exact coordinates.
 */
require_once '../config.php';
if (!isLoggedIn()) { header('Location: ../admin/login.php'); exit; }

$attendeeId = (int)($_GET['id'] ?? 0);
if (!$attendeeId) die('Invalid request.');

$db = getDB();
$stmt = $db->prepare("
    SELECT a.*, ts.training_type, ts.date_conducted, ts.facilitator, ts.principal,
           ts.course_title, ts.cert_validity_years,
           c.cert_number as cert_no
    FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    LEFT JOIN certificates c ON c.attendee_id = a.id
    WHERE a.id = ?
");
$stmt->execute([$attendeeId]);
$data = $stmt->fetch();
if (!$data) die('Attendee not found.');

$type = $data['training_type'];

// Route to correct generator
switch ($type) {
    case 'anti_piracy':     generateAPAT($data); break;
    case 'pdos':            generatePDOS($data); break;
    case 'secat':           generateSECAT($data); break;
    default: die('No certificate template for this training type.');
}

// ============================================================
// SHARED: mPDF bootstrap
// ============================================================
function getMpdf(string $orientation = 'P'): \Mpdf\Mpdf {
    require_once __DIR__ . '/../vendor/autoload.php';
    return new \Mpdf\Mpdf([
        'orientation'   => $orientation,
        'format'        => 'A4',
        'margin_top'    => 0,
        'margin_bottom' => 0,
        'margin_left'   => 0,
        'margin_right'  => 0,
        'default_font'  => 'dejavusans',
    ]);
}

function outputCert(\Mpdf\Mpdf $mpdf, string $certNo): void {
    $filename = 'Certificate_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $certNo) . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}

// ============================================================
// APAT — Anti-Piracy Awareness Training
// ============================================================
function generateAPAT(array $d): void {
    $mpdf = getMpdf();

    $certNo    = $d['cert_no']    ?? 'PENDING';
    $firstName = strtoupper($d['given_name']);
    $middleName= strtoupper($d['middle_initial'] ?? '');
    $surname   = strtoupper($d['surname']);
    $fullName  = trim("$firstName $middleName $surname");

    $date      = $d['date_conducted'];
    $day       = date('j', strtotime($date));
    $daySuffix = getDaySuffix((int)$day);
    $month     = strtoupper(date('F', strtotime($date)));
    $year      = date('Y', strtotime($date));

    $templateImg = getTemplateImage('APAT');

    $html = '
    <style>
        @page { margin: 0; }
        body  { margin: 0; padding: 0; font-family: dejavusans, sans-serif; }

        .bg {
            position: absolute; top: 0; left: 0;
            width: 210mm; height: 297mm;
            z-index: 0;
        }

        /* Certificate Number — top right */
        .cert-num {
            position: absolute;
            top: 42mm; right: 18mm;
            font-size: 9pt; color: #000;
            text-align: right;
            z-index: 1;
        }

        /* Full name — large, bold, centered */
        .full-name {
            position: absolute;
            top: 103mm; left: 0; right: 0;
            text-align: center;
            font-size: 22pt; font-weight: bold; color: #000;
            z-index: 1;
        }

        /* "Conducted on" line */
        .conducted {
            position: absolute;
            top: 161mm; left: 0; right: 0;
            text-align: center;
            font-size: 10pt; color: #000;
            z-index: 1;
        }

        /* "Given this" line */
        .given-this {
            position: absolute;
            top: 194mm; left: 0; right: 0;
            text-align: center;
            font-size: 10pt; color: #000;
            z-index: 1;
        }
    </style>

    <img class="bg" src="' . $templateImg . '">

    <div class="cert-num">APAT-' . htmlspecialchars($certNo, ENT_QUOTES) . '</div>

    <div class="full-name">' . htmlspecialchars($fullName, ENT_QUOTES) . '</div>

    <div class="conducted">
        Conducted on <strong>' . $day . ' ' . $month . ' ' . $year . '</strong>
        at <strong>88 Aces Maritime Services Inc.</strong>
    </div>

    <div class="given-this">
        Given this <strong>' . $day . '<sup>' . $daySuffix . '</sup></strong>
        day of <strong>' . $month . ' ' . $year . '</strong>, at Manila City, Philippines.
    </div>
    ';

    $mpdf->WriteHTML($html);
    outputCert($mpdf, $certNo);
}

// ============================================================
// PDOS — Pre-Departure Orientation Seminar
// ============================================================
function generatePDOS(array $d): void {
    $mpdf = getMpdf();

    $certNo     = $d['cert_no']    ?? 'PENDING';
    $firstName  = strtoupper($d['given_name']);
    $middleName = strtoupper($d['middle_initial'] ?? '');
    $surname    = strtoupper($d['surname']);
    $rank       = strtoupper($d['rank'] ?? '');
    $principal  = strtoupper($d['principal'] ?? '');

    $date       = $d['date_conducted'];
    $dateStr    = strtoupper(date('d F Y', strtotime($date)));

    $templateImg = getTemplateImage('PDOS');

    $html = '
    <style>
        @page { margin: 0; }
        body  { margin: 0; padding: 0; font-family: dejavusans, sans-serif; }

        .bg {
            position: absolute; top: 0; left: 0;
            width: 210mm; height: 297mm;
            z-index: 0;
        }

        /* Name of OFW — first + middle on first line, surname on second */
        .ofw-firstname {
            position: absolute;
            top: 120mm; left: 95mm;
            font-size: 10.5pt; font-weight: bold; color: #000;
            z-index: 1;
        }
        .ofw-surname {
            position: absolute;
            top: 129mm; left: 16mm;
            font-size: 10.5pt; font-weight: bold; color: #000;
            z-index: 1;
        }

        /* Skill / Occupation */
        .skill {
            position: absolute;
            top: 138mm; left: 95mm;
            font-size: 10.5pt; font-weight: bold; color: #000;
            z-index: 1;
        }

        /* Foreign Principal */
        .principal-1 {
            position: absolute;
            top: 155mm; left: 95mm;
            font-size: 10.5pt; font-weight: bold; color: #000;
            z-index: 1;
        }
        .principal-2 {
            position: absolute;
            top: 163mm; left: 95mm;
            font-size: 10.5pt; font-weight: bold; color: #000;
            z-index: 1;
        }

        /* Date attended + Cert number line */
        .date-cert {
            position: absolute;
            top: 179mm; left: 16mm; right: 16mm;
            font-size: 9.5pt; color: #000;
            z-index: 1;
        }
        .date-cert strong { text-decoration: underline; }
    </style>

    <img class="bg" src="' . $templateImg . '">

    <div class="ofw-firstname">' . htmlspecialchars("$firstName $middleName", ENT_QUOTES) . '</div>
    <div class="ofw-surname">'   . htmlspecialchars($surname,                  ENT_QUOTES) . '</div>
    <div class="skill">'         . htmlspecialchars($rank,                     ENT_QUOTES) . '</div>
    <div class="principal-1">'   . htmlspecialchars($principal,                ENT_QUOTES) . '</div>
    <div class="principal-2">'   . htmlspecialchars($principal,                ENT_QUOTES) . '</div>

    <div class="date-cert">
        This certifies that the above named OFW has completed the prescribed requirements
        for the above program, held on
        <strong>' . htmlspecialchars($dateStr, ENT_QUOTES) . '</strong>
        with Certificate No. <strong>PDOS-' . htmlspecialchars($certNo, ENT_QUOTES) . '</strong>.
    </div>
    ';

    $mpdf->WriteHTML($html);
    outputCert($mpdf, $certNo);
}

// ============================================================
// SECAT — Shipboard Emergency Competency Awareness Training
// ============================================================
function generateSECAT(array $d): void {
    $mpdf = getMpdf();

    $certNo     = $d['cert_no']     ?? 'PENDING';
    $firstName  = strtoupper($d['given_name']);
    $middleName = strtoupper($d['middle_initial'] ?? '');
    $surname    = strtoupper($d['surname']);
    $fullName   = trim("$firstName $middleName $surname");
    $facilitator= strtoupper($d['facilitator'] ?? '');

    $date       = $d['date_conducted'];
    $dateStr    = date('d F Y', strtotime($date));
    $validYears = (int)($d['cert_validity_years'] ?? 2);
    $expiryDate = date('d F Y', strtotime("+{$validYears} years", strtotime($date)));

    $templateImg = getTemplateImage('SECAT');

    $html = '
    <style>
        @page { margin: 0; }
        body  { margin: 0; padding: 0; font-family: dejavusans, sans-serif; }

        .bg {
            position: absolute; top: 0; left: 0;
            width: 210mm; height: 297mm;
            z-index: 0;
        }

        /* Certificate number — top right area */
        .cert-num {
            position: absolute;
            top: 28mm; right: 22mm;
            font-size: 9pt; color: #000;
            text-align: right;
            z-index: 1;
        }

        /* Full name — large bold centered */
        .full-name {
            position: absolute;
            top: 99mm; left: 0; right: 0;
            text-align: center;
            font-size: 20pt; font-weight: bold; color: #000;
            z-index: 1;
        }

        /* Facilitator — bottom left */
        .facilitator {
            position: absolute;
            top: 164mm; left: 22mm;
            font-size: 9pt; font-weight: bold; color: #000;
            text-align: center; width: 60mm;
            z-index: 1;
        }

        /* Training date — bottom center-right */
        .train-date {
            position: absolute;
            top: 164mm; left: 95mm;
            font-size: 9pt; font-weight: bold; color: #000;
            text-align: center; width: 70mm;
            z-index: 1;
        }

        /* Valid until */
        .valid-until {
            position: absolute;
            top: 171mm; left: 95mm;
            font-size: 9pt; font-weight: bold; color: #000;
            text-align: center; width: 70mm;
            z-index: 1;
        }
    </style>

    <img class="bg" src="' . $templateImg . '">

    <div class="cert-num">' . htmlspecialchars($certNo, ENT_QUOTES) . '</div>

    <div class="full-name">' . htmlspecialchars($fullName, ENT_QUOTES) . '</div>

    <div class="facilitator">' . htmlspecialchars($facilitator, ENT_QUOTES) . '</div>

    <div class="train-date">' . htmlspecialchars($dateStr, ENT_QUOTES) . '</div>

    <div class="valid-until">VALID UNTIL: ' . htmlspecialchars($expiryDate, ENT_QUOTES) . '</div>
    ';

    $mpdf->WriteHTML($html);
    outputCert($mpdf, $certNo);
}

// ============================================================
// HELPER: Get template image path (converts PDF to PNG on first run)
// ============================================================
function getTemplateImage(string $type): string {
    $templateDir = ROOT_PATH . 'assets/cert_templates/';
    $imgPath     = $templateDir . $type . '_template.png';

    // If already converted, return it
    if (file_exists($imgPath)) {
        return $imgPath;
    }

    // Make directory if needed
    if (!is_dir($templateDir)) {
        mkdir($templateDir, 0755, true);
    }

    // Map type to PDF source
    $pdfMap = [
        'APAT'  => ROOT_PATH . 'assets/cert_templates/APAT_source.pdf',
        'PDOS'  => ROOT_PATH . 'assets/cert_templates/PDOS_source.pdf',
        'SECAT' => ROOT_PATH . 'assets/cert_templates/SECAT_source.pdf',
    ];

    $pdfSrc = $pdfMap[$type] ?? '';
    if (!file_exists($pdfSrc)) {
        // Fallback — return empty string, mPDF will just show blank background
        return '';
    }

    // Convert PDF page 1 to PNG using Imagick if available
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->setResolution(150, 150);
            $im->readImage($pdfSrc . '[0]');
            $im->setImageFormat('png');
            $im->writeImage($imgPath);
            $im->clear();
            return $imgPath;
        } catch (Exception $e) {
            // Fall through to pdftoppm
        }
    }

    // Fallback: pdftoppm (command line)
    $tmpBase = $templateDir . $type . '_tmp';
    exec("pdftoppm -r 150 -png -f 1 -l 1 " . escapeshellarg($pdfSrc) . " " . escapeshellarg($tmpBase));
    $tmpFile = $tmpBase . '-1.png';
    if (file_exists($tmpFile)) {
        rename($tmpFile, $imgPath);
        return $imgPath;
    }

    return '';
}

// ============================================================
// HELPER: Day suffix (1st, 2nd, 3rd, 4th...)
// ============================================================
function getDaySuffix(int $day): string {
    if ($day >= 11 && $day <= 13) return 'th';
    return match($day % 10) {
        1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th'
    };
}
