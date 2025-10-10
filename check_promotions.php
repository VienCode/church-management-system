<?php
include 'database.php';

// Fetch eligible non-members (10+ attendances)
$query = "
    SELECT id, firstname, lastname, contact, email, attendances_count
    FROM non_members
    WHERE attendances_count >= 10
    ORDER BY lastname ASC
";
$result = $mysqli->query($query);

$eligible = [];
while ($row = $result->fetch_assoc()) {
    $eligible[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'count' => count($eligible),
    'members' => $eligible
]);
exit;
?>
