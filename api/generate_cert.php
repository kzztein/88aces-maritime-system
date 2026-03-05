<?php
// ============================================================
// api/generate_cert.php — Fill certificate template with data
// ============================================================
require_once '../config.php';
requireLogin();

$attendeeId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("
    SELECT a.*, ts.course_title, ts.training_type, ts.date_conducted,
           ts.facilitator, ts.company, ts.session_code,
           c.cert_number
    FROM attendees a
    JOIN training_sessions ts ON ts.id = a.session_id
    LEFT JOIN certificates c ON c.attendee_id = a.id
    WHERE a.id = ?
");
$stmt->execute([$attendeeId]);
$data = $stmt->fetch();

if (!$data) die('Attendee not found.');
if (!$data['cert_number']) die('No certificate yet. Please close the session first.');

// Map training type to template file
$templateMap = [
    'anti_piracy'     => '../cert_templates/anti_piracy.docx',
    'pdos'            => '../cert_templates/pdos.docx',
    'secat'           => '../cert_templates/secat.docx',
    'attendance_only' => '../cert_templates/anti_piracy.docx',
];

$templateFile = $templateMap[$data['training_type']] ?? '../cert_templates/anti_piracy.docx';

if (!file_exists($templateFile)) {
    die('
    <!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px">
    <div style="max-width:400px;margin:0 auto;background:#fff;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1)">
    <div style="font-size:48px">⚠️</div>
    <h2 style="color:#dc2626">Template Not Found</h2>
    <p style="color:#6b7280">Please upload the <strong>' . strtoupper($data['training_type']) . '</strong> certificate template first.</p>
    <p style="color:#6b7280;font-size:13px">Go to: Certificates → Manage Templates → Upload</p>
    <a href="../admin/certificates.php" style="display:inline-block;margin-top:16px;padding:10px 20px;background:#1a4a8a;color:#fff;border-radius:8px;text-decoration:none">Go to Certificates</a>
    </div></body></html>');
}

// Prepare all merge field values
$date      = new DateTime($data['date_conducted']);
$day       = $date->format('j');
$month     = $date->format('F');
$year      = $date->format('Y');
$fullDate  = $date->format('F d, Y');

$certNumber = $data['cert_number'];
$certParts  = explode(' ', $certNumber);
$certSuffix = end($certParts); // e.g. "2026-0001"

$mergeFields = [
    'CERTIFICATE_NUMBER' => $certSuffix,
    'FIRST_NAME'         => strtoupper($data['given_name']),
    'MIDDLE_NAME'        => strtoupper($data['middle_initial']),
    'MIDDLE_INITIAL'     => strtoupper($data['middle_initial']),
    'SURNAME'            => strtoupper($data['surname']),
    'RELEASE_DAY'        => $day,
    'MONTH'              => $month,
    'YEAR'               => $year,
    'RANK'               => strtoupper($data['rank']),
    'PRINCIPAL'          => strtoupper($data['vessel']),
    'DATE_ATTENDED'      => $fullDate,
    'NAME'               => strtoupper($data['given_name'] . ' ' . $data['middle_initial'] . '. ' . $data['surname']),
    'DATE'               => $fullDate,
    'FACILITATOR'        => strtoupper($data['facilitator']),
];

$outputFile = fillDocxTemplate($templateFile, $mergeFields);

$filename = strtoupper($data['training_type']) . '_' . $data['surname'] . '_' . $data['given_name'] . '_' . $certSuffix . '.docx';
$filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename);

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($outputFile));
header('Cache-Control: max-age=0');
readfile($outputFile);
unlink($outputFile);
exit;

function fillDocxTemplate(string $templatePath, array $fields): string {
    $tmpFile = tempnam(sys_get_temp_dir(), 'cert_') . '.docx';
    copy($templatePath, $tmpFile);

    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) die('Cannot open template.');

    $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml',
                 'word/header2.xml', 'word/footer2.xml'];

    foreach ($xmlFiles as $xmlFile) {
        $content = $zip->getFromName($xmlFile);
        if ($content === false) continue;
        $content = replaceMergeFields($content, $fields);
        $zip->addFromString($xmlFile, $content);
    }

    $zip->close();
    return $tmpFile;
}

function replaceMergeFields(string $xml, array $fields): string {
    foreach ($fields as $fieldName => $value) {
        $safeValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Replace «FIELDNAME» display values
        $xml = str_replace('«' . $fieldName . '»', $safeValue, $xml);

        // Replace <<FIELDNAME>> style
        $xml = str_replace('&lt;&lt;' . $fieldName . '&gt;&gt;', $safeValue, $xml);
        $xml = str_replace('<<' . $fieldName . '>>', $safeValue, $xml);

        // Replace full MERGEFIELD XML blocks
        $pattern = '/<w:fldChar\s[^>]*w:fldCharType="begin"[^>]*>.*?<w:instrText[^>]*>\s*MERGEFIELD\s+"?' 
                 . preg_quote($fieldName, '/') 
                 . '"?\s*\\\\MERGEFORMAT\s*<\/w:instrText>.*?<w:fldChar\s[^>]*w:fldCharType="end"[^>]*>/s';
        $replacement = '<w:r><w:t>' . $safeValue . '</w:t></w:r>';
        $xml = preg_replace($pattern, $replacement, $xml);

        // Simpler pattern without MERGEFORMAT
        $pattern2 = '/<w:fldChar\s[^>]*w:fldCharType="begin"[^>]*>.*?<w:instrText[^>]*>\s*MERGEFIELD\s+"?' 
                  . preg_quote($fieldName, '/') 
                  . '"?\s*<\/w:instrText>.*?<w:fldChar\s[^>]*w:fldCharType="end"[^>]*>/s';
        $xml = preg_replace($pattern2, $replacement, $xml);
    }
    return $xml;
}