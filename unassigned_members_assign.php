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

// ✅ Find or create leader’s active cell group
$group_stmt = $mysqli->prepare("SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group_data = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group_data) {
    $leader_name_res = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ?");
    $leader_name_res->bind_param("i", $leader_id);
    $leader_name_res->execute();
    $leader_data = $leader_name_res->get_result()->fetch_assoc();
    $leader_name_res->close();

    $group_name = $leader_data ? $leader_data['leader_name'] . "'s Cell Group" : "Unnamed Group";

    $create_group = $mysqli->prepare("INSERT INTO cell_groups (group_name, leader_id, status, created_at) VALUES (?, ?, 'active', NOW())");
    $create_group->bind_param("si", $group_name, $leader_id);
    $create_group->execute();
    $group_id = $create_group->insert_id;
    $create_group->close();
} else {
    $group_id = $group_data['id'];
}

// ✅ Assign members
$updated = 0;
foreach ($member_ids as $member_id) {
    $member_id = intval($member_id);

    $stmt = $mysqli->prepare("SELECT user_code FROM users WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) continue;

    $user_code = $user['user_code'];

    $mysqli->query("DELETE FROM cell_group_members WHERE user_code = '$user_code'");

    $add_member = $mysqli->prepare("INSERT IGNORE INTO cell_group_members (cell_group_id, user_code) VALUES (?, ?)");
    $add_member->bind_param("is", $group_id, $user_code);
    $add_member->execute();
    $add_member->close();

    $update_user = $mysqli->prepare("UPDATE users SET leader_id = ?, is_cell_member = 1, last_unassigned_at = NULL WHERE id = ?");
    $update_user->bind_param("ii", $leader_id, $member_id);
    $update_user->execute();
    $update_user->close();

    $updated++;
}

echo json_encode(['success' => true, 'message' => "✅ Successfully reassigned $updated member(s) to the selected leader."]);
exit;
?>
