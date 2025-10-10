<?php
include 'database.php';
include 'auth_check.php';
header('Content-Type: application/json');

// Only admins can assign
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leader_id = intval($_POST['leader_id'] ?? 0);
    $member_ids = json_decode($_POST['member_ids'] ?? '[]', true);

    if (empty($leader_id) || empty($member_ids)) {
        echo json_encode(['success' => false, 'message' => 'Missing leader or member data.']);
        exit;
    }

    // Verify leader exists in the leaders table
    $verifyLeader = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE leader_id = ?");
    $verifyLeader->bind_param("i", $leader_id);
    $verifyLeader->execute();
    $leader = $verifyLeader->get_result()->fetch_assoc();
    $verifyLeader->close();

    if (!$leader) {
        echo json_encode(['success' => false, 'message' => '❌ Selected leader does not exist.']);
        exit;
    }

    // Prepare update query
    $stmt = $mysqli->prepare("UPDATE users SET leader_id = ? WHERE id = ?");
    $count = 0;

    foreach ($member_ids as $mid) {
        $mid = intval($mid);
        $stmt->bind_param("ii", $leader_id, $mid);
        if ($stmt->execute()) $count++;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => "✅ $count member(s) successfully assigned to leader: " . htmlspecialchars($leader['leader_name'])
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
?>
