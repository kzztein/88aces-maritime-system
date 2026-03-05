<?php
require_once '../config.php';
requireLogin();

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

$id   = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'attendance';
$db   = getDB();

if ($type === 'attendance') {
    generateAttendanceExcel($id, $db);
} elseif ($type === 'report') {
    generateReportExcel($id, $db);
}

// ── ATTENDANCE SHEET EXCEL ────────────────────────────────────
function generateAttendanceExcel(int $sessionId, PDO $db): void {
    $stmt = $db->prepare("SELECT * FROM training_sessions WHERE id=?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) die('Session not found.');

    $stmt = $db->prepare("SELECT * FROM attendees WHERE session_id=? ORDER BY submitted_at ASC");
    $stmt->execute([$sessionId]);
    $attendees = $stmt->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Attendance Sheet');

    // ── Set column widths
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(22);
    $sheet->getColumnDimension('H')->setWidth(18);
    $sheet->getColumnDimension('I')->setWidth(15);

    // ── Header - Company Name
    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', '88 ACES MARITIME SERVICES INC.');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2C5C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // ── Header - Address
    $sheet->mergeCells('A2:I2');
    $sheet->setCellValue('A2', '12th Floor Trium Square Building Sen. Gil Puyat Ave. Corner Leveriza, Pasay City 1300');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['size' => 9, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    // ── Title
    $sheet->mergeCells('A3:I3');
    $sheet->setCellValue('A3', 'TRAINING ATTENDANCE SHEET');
    $sheet->getStyle('A3')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(3)->setRowHeight(25);

    // ── Form No
    $sheet->mergeCells('A4:I4');
    $sheet->setCellValue('A4', 'Form No.: QD-TRA-02 Rev.1');
    $sheet->getStyle('A4')->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    // ── Session Info
    $sheet->mergeCells('A5:B5');
    $sheet->setCellValue('A5', 'COURSE TITLE:');
    $sheet->getStyle('A5')->getFont()->setBold(true);
    $sheet->mergeCells('C5:I5');
    $sheet->setCellValue('C5', $session['course_title']);

    $sheet->mergeCells('A6:B6');
    $sheet->setCellValue('A6', 'DATE & TIME:');
    $sheet->getStyle('A6')->getFont()->setBold(true);
    $sheet->mergeCells('C6:E6');
    $date = date('F d, Y', strtotime($session['date_conducted']));
    $timeStart = date('h:i A', strtotime($session['time_start']));
    $timeEnd   = date('h:i A', strtotime($session['time_end']));
    $sheet->setCellValue('C6', "$date | $timeStart - $timeEnd");

    $sheet->mergeCells('F6:G6');
    $sheet->setCellValue('F6', 'LOCATION:');
    $sheet->getStyle('F6')->getFont()->setBold(true);
    $sheet->mergeCells('H6:I6');
    $sheet->setCellValue('H6', $session['location']);

    // Style info rows
    foreach (['5', '6'] as $row) {
        $sheet->getStyle("A$row:I$row")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    // ── Table Headers Row 7
    $headers = ['No.', 'Surname', 'Given Name', 'M.I.', 'Rank', 'Vessel', 'Signature', 'Principal', 'Remarks'];
    foreach ($headers as $col => $header) {
        $colLetter = chr(65 + $col);
        $sheet->setCellValue("{$colLetter}7", $header);
    }
    $sheet->getStyle('A7:I7')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
    $sheet->getRowDimension(7)->setRowHeight(22);

    // ── Attendee Rows
    $maxRows = max(20, count($attendees));
    for ($i = 0; $i < $maxRows; $i++) {
        $rowNum = $i + 8;
        $a = $attendees[$i] ?? null;

        $sheet->setCellValue("A$rowNum", $i + 1);
        $sheet->setCellValue("B$rowNum", $a['surname']       ?? '');
        $sheet->setCellValue("C$rowNum", $a['given_name']    ?? '');
        $sheet->setCellValue("D$rowNum", $a['middle_initial']?? '');
        $sheet->setCellValue("E$rowNum", $a['rank']          ?? '');
        $sheet->setCellValue("F$rowNum", $a['vessel']        ?? '');
        // G, H, I left blank for signature/principal/remarks

        $bgColor = ($i % 2 === 0) ? 'FFFFFF' : 'F0F4FF';
        $sheet->getStyle("A$rowNum:I$rowNum")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($rowNum)->setRowHeight(18);
    }

    // ── Signature Row
    $sigRow = $maxRows + 9;
    $sheet->mergeCells("A$sigRow:C$sigRow");
    $sheet->setCellValue("A$sigRow", 'Facilitator: ' . $session['facilitator']);
    $sheet->getStyle("A$sigRow")->getFont()->setBold(true);

    $sheet->mergeCells("G$sigRow:I$sigRow");
    $sheet->setCellValue("G$sigRow", 'Date: ' . $date);
    $sheet->getStyle("G$sigRow")->getFont()->setBold(true);

    // ── Output
    $filename = 'Attendance_' . $session['session_code'] . '_' . date('Ymd') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── MONTHLY REPORT EXCEL ──────────────────────────────────────
function generateReportExcel(int $sessionId, PDO $db): void {
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

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Training Report');

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(35);
    $sheet->getColumnDimension('E')->setWidth(14);
    $sheet->getColumnDimension('F')->setWidth(22);
    $sheet->getColumnDimension('G')->setWidth(18);
    $sheet->getColumnDimension('H')->setWidth(20);

    // Title
    $month = date('F Y', strtotime($session['date_conducted']));
    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', 'ANTI PIRACY AWARENESS TRAINING REPORT');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F2C5C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    $sheet->mergeCells('A2:H2');
    $sheet->setCellValue('A2', 'FOR THE MONTH OF ' . strtoupper($month));
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(2)->setRowHeight(22);

    // Headers
    $headers = ['NO.', 'CERT NO.', 'RANK', 'FULL NAME (LN, FN, MN)', 'EX/NEW CREW', 'VESSEL', 'DATE CONDUCTED', 'NAME OF COMPANY'];
    foreach ($headers as $col => $header) {
        $colLetter = chr(65 + $col);
        $sheet->setCellValue("{$colLetter}3", $header);
    }
    $sheet->getStyle('A3:H3')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A4A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Data rows
    foreach ($attendees as $i => $a) {
        $rowNum  = $i + 4;
        $fullName = strtoupper($a['surname'] . ', ' . $a['given_name'] . ', ' . $a['middle_initial']);
        $bgColor  = ($i % 2 === 0) ? 'FFFFFF' : 'EEF2FF';

        $sheet->setCellValue("A$rowNum", $i + 1);
        $sheet->setCellValue("B$rowNum", $a['cert_no'] ?? '-');
        $sheet->setCellValue("C$rowNum", $a['rank']);
        $sheet->setCellValue("D$rowNum", $fullName);
        $sheet->setCellValue("E$rowNum", $a['crew_type']);
        $sheet->setCellValue("F$rowNum", $a['vessel']);
        $sheet->setCellValue("G$rowNum", date('M d, Y', strtotime($session['date_conducted'])));
        $sheet->setCellValue("H$rowNum", '88 ACES');

        $sheet->getStyle("A$rowNum:H$rowNum")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($rowNum)->setRowHeight(18);
    }

    // Output
    $filename = 'Report_' . $session['session_code'] . '_' . date('Ymd') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}