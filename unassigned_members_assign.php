<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

header('Content-Type: application/json');

if (!isset($_POST['leader_id'], $_POST['member_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$leader_id = intval($_POST['leader_id']);
$member_ids = json_decode($_POST['member_ids'], true);

if (empty($leader_id) || empty($member_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing data.']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE users SET leader_id = ? WHERE id = ?");
$updated = 0;
foreach ($member_ids as $id) {
    $stmt->bind_param("ii", $leader_id, $id);
    $stmt->execute();
    $updated += $stmt->affected_rows;
}
$stmt->close();

echo json_encode(['success' => true, 'message' => "Reassigned $updated member(s) successfully."]);
exit;
