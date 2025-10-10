<?php
include 'database.php';
include 'auth_check.php';
header('Content-Type: application/json');
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leader_id = intval($_POST['leader_id']);
    $member_ids = json_decode($_POST['member_ids'], true);

    if (empty($leader_id) || empty($member_ids)) {
        echo json_encode(['success' => false, 'message' => 'Missing data.']);
        exit;
    }

    // Assign members to leader
    $stmt = $mysqli->prepare("UPDATE users SET leader_id = ? WHERE id = ?");
    foreach ($member_ids as $mid) {
        $stmt->bind_param("ii", $leader_id, $mid);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true, 'message' => count($member_ids) . ' member(s) reassigned successfully!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
?>
