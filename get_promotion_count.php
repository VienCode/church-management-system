<?php
include 'database.php';
include 'auth_check.php';

// Restrict to specific roles
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

// Count all current non-members
$query = "SELECT COUNT(*) AS total FROM non_members";
$result = $mysqli->query($query);
$count = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;

// Return as JSON
header('Content-Type: application/json');
echo json_encode(['count' => $count]);
exit;
?>
