<?php
$mysqli = include 'database.php';
session_start();

// Get the selected date from session or default to today
$current_date = $_SESSION['attendance_date'] ?? date("Y-m-d");

// Fetch attendance data for that date
$query = "
    SELECT 
        m.full_name,
        a.attendance_date,
        a.status,
        a.arrival_time
    FROM members m
    LEFT JOIN attendance a ON m.member_id = a.member_id AND a.attendance_date = ?
    ORDER BY m.full_name ASC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=attendance_{$current_date}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output column headers
echo "Member Name\tAttendance Date\tStatus\tArrival Time\n";

// Output data rows
while ($row = $result->fetch_assoc()) {
    echo htmlspecialchars($row['full_name']) . "\t";
    echo ($row['attendance_date'] ?? '-') . "\t";
    echo ($row['status'] ?? 'Not Marked') . "\t";
    echo ($row['arrival_time'] ?? '-') . "\n";
}

exit();
?>
