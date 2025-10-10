<?php
require 'vendor/autoload.php';
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Filters
$search_name = $_GET['search_name'] ?? '';
$search_status = $_GET['status'] ?? 'all';
$search_start = $_GET['start_date'] ?? '';
$search_end = $_GET['end_date'] ?? '';

$where = "1";
$params = [];
$types = "";

if (!empty($search_name)) {
    $where .= " AND CONCAT(n.firstname, ' ', n.lastname) LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}
if ($search_status !== 'all') {
    $where .= " AND e.status = ?";
    $params[] = $search_status;
    $types .= "s";
}
if (!empty($search_start) && !empty($search_end)) {
    $where .= " AND e.attendance_date BETWEEN ? AND ?";
    $params[] = $search_start;
    $params[] = $search_end;
    $types .= "ss";
}

// Fetch data
$sql = "
    SELECT 
        n.firstname, n.lastname, n.contact, n.email,
        e.attendance_date, e.status, e.time_in, e.recorded_by
    FROM non_members n
    LEFT JOIN evangelism_attendance e ON n.id = e.non_member_id
    WHERE $where
    ORDER BY e.attendance_date DESC, n.lastname ASC
";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Create Excel sheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Evangelism Report');

$headers = ['Full Name', 'Contact', 'Email', 'Attendance Date', 'Status', 'Time In', 'Recorded By'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0271C0');
    $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
}

$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $row['firstname'] . ' ' . $row['lastname']);
    $sheet->setCellValue("B$rowNum", $row['contact']);
    $sheet->setCellValue("C$rowNum", $row['email']);
    $sheet->setCellValue("D$rowNum", $row['attendance_date']);
    $sheet->setCellValue("E$rowNum", $row['status'] ?? 'Not Marked');
    $sheet->setCellValue("F$rowNum", $row['time_in'] ?? '-');
    $sheet->setCellValue("G$rowNum", $row['recorded_by'] ?? '-');
    $rowNum++;
}

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "Evangelism_Report_" . date('Y-m-d_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
