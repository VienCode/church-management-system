<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER]);

session_start();

$current_date = $_SESSION['attendance_date'] ?? date("Y-m-d");

$query = "
    SELECT 
        u.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS full_name,
        r.role_name,
        a.attendance_date,
        a.status,
        a.time_in
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN attendance a 
        ON a.user_code = u.user_code 
        AND a.attendance_date = ?
    WHERE u.role_id != 4
    ORDER BY u.lastname ASC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=attendance_{$current_date}.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output BOM for UTF-8 compatibility (avoids garbled text)
echo "\xEF\xBB\xBF";

// Output column headers
echo "User Code\tFull Name\tRole\tAttendance Date\tStatus\tTime In\n";

// Output data rows
while ($row = $result->fetch_assoc()) {
    echo "{$row['user_code']}\t";
    echo "{$row['full_name']}\t";
    echo "{$row['role_name']}\t";
    echo ($row['attendance_date'] ?? '-') . "\t";
    echo ($row['status'] ?? 'Not Marked') . "\t";
    echo ($row['time_in'] ?? '-') . "\n";
}

exit();
?>
