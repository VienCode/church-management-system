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
    echo json_encode(['success' => false, 'message' => 'Missing leader or member data.']);
    exit;
}

// ✅ Step 1: Find or create the leader’s active cell group
$group_stmt = $mysqli->prepare("SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    $leader_data = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ? LIMIT 1");
    $leader_data->bind_param("i", $leader_id);
    $leader_data->execute();
    $leader_info = $leader_data->get_result()->fetch_assoc();
    $leader_data->close();

    $group_name = $leader_info ? $leader_info['leader_name'] . "'s Cell Group" : "Unnamed Group";

    $create_group = $mysqli->prepare("
        INSERT INTO cell_groups (group_name, leader_id, status, created_at)
        VALUES (?, ?, 'active', NOW())
    ");
    $create_group->bind_param("si", $group_name, $leader_id);
    $create_group->execute();
    $group_id = $create_group->insert_id;
    $create_group->close();
} else {
    $group_id = $group['id'];
}

// ✅ Step 2: Assign members by user_code
$updated = 0;
foreach ($member_ids as $id) {
    if (!is_numeric($id)) continue;

    // Get user_code
    $code_stmt = $mysqli->prepare("SELECT user_code FROM users WHERE id = ?");
    $code_stmt->bind_param("i", $id);
    $code_stmt->execute();
    $code_result = $code_stmt->get_result()->fetch_assoc();
    $code_stmt->close();

    if (!$code_result) continue;
    $user_code = $code_result['user_code'];

    // Update leader assignment
    $update_user = $mysqli->prepare("UPDATE users SET leader_id = ?, last_unassigned_at = NULL WHERE id = ?");
    $update_user->bind_param("ii", $leader_id, $id);
    $update_user->execute();
    $update_user->close();

    // Check if already in group
    $check = $mysqli->prepare("SELECT id FROM cell_group_members WHERE cell_group_id = ? AND user_code = ?");
    $check->bind_param("is", $group_id, $user_code);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        $insert = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, user_code)
            VALUES (?, ?)
        ");
        $insert->bind_param("is", $group_id, $user_code);
        $insert->execute();
        $insert->close();
        $updated++;
    }
}

echo json_encode([
    'success' => true,
    'message' => "✅ Successfully reassigned $updated member(s) to the leader’s group."
]);
exit;
?>
