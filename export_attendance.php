<?php
require 'vendor/autoload.php';
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER, ROLE_LEADER]);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// === FETCH FILTERS FROM GET (same as attendance_records.php) ===
$search_name = $_GET['search_name'] ?? '';
$search_code = $_GET['search_code'] ?? '';
$search_role = $_GET['role'] ?? 'all';
$search_status = $_GET['status'] ?? 'all';
$search_date = $_GET['date'] ?? '';
$search_start = $_GET['start_date'] ?? '';
$search_end = $_GET['end_date'] ?? '';

$where = "1";
$params = [];
$types = "";

// Apply filters
if (!empty($search_name)) {
    $where .= " AND CONCAT(u.firstname, ' ', u.lastname) LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}
if (!empty($search_code)) {
    $where .= " AND u.user_code LIKE ?";
    $params[] = "%$search_code%";
    $types .= "s";
}
if ($search_role !== 'all') {
    $where .= " AND r.role_id = ?";
    $params[] = $search_role;
    $types .= "i";
}
if ($search_status !== 'all') {
    $where .= " AND a.status = ?";
    $params[] = $search_status;
    $types .= "s";
}
if (!empty($search_date)) {
    $where .= " AND a.attendance_date = ?";
    $params[] = $search_date;
    $types .= "s";
}
if (!empty($search_start) && !empty($search_end)) {
    $where .= " AND a.attendance_date BETWEEN ? AND ?";
    $params[] = $search_start;
    $params[] = $search_end;
    $types .= "ss";
}

// === FETCH DATA ===
$sql = "
    SELECT 
        a.attendance_date,
        u.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        r.role_name,
        a.status,
        a.time_in,
        a.recorded_by
    FROM attendance a
    JOIN users u ON a.user_code = u.user_code
    JOIN roles r ON u.role_id = r.role_id
    WHERE $where
    ORDER BY a.attendance_date DESC, u.lastname ASC
";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// === SETUP SPREADSHEET ===
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance Records');

// === HEADER ROW ===
$headers = ['Date', 'User Code', 'Full Name', 'Role', 'Status', 'Time In', 'Recorded By'];
$colIndex = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($colIndex.'1', $header);
    $sheet->getStyle($colIndex.'1')->getFont()->setBold(true);
    $sheet->getStyle($colIndex.'1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('0271C0');
    $sheet->getStyle($colIndex.'1')->getFont()->getColor()->setARGB('FFFFFF');
    $sheet->getStyle($colIndex.'1')->getAlignment()->setHorizontal('center');
    $colIndex++;
}

// === DATA ROWS ===
$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $row['attendance_date']);
    $sheet->setCellValue("B$rowNum", $row['user_code']);
    $sheet->setCellValue("C$rowNum", $row['fullname']);
    $sheet->setCellValue("D$rowNum", $row['role_name']);
    $sheet->setCellValue("E$rowNum", $row['status']);
    $sheet->setCellValue("F$rowNum", $row['time_in']);
    $sheet->setCellValue("G$rowNum", $row['recorded_by'] ?? '—');
    $rowNum++;
}

// Auto-size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// === SUMMARY (optional footer) ===
$summaryRow = $rowNum + 2;
$sheet->setCellValue("A$summaryRow", "Generated on: " . date('F j, Y g:i A'));
$sheet->getStyle("A$summaryRow")->getFont()->setItalic(true)->setSize(10);

// === OUTPUT FILE ===
$filename = "Attendance_Records_" . date('Y-m-d_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
