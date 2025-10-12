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

// ✅ Step 1: Ensure leader has an active cell group
$group_stmt = $mysqli->prepare("
    SELECT id FROM cell_groups 
    WHERE leader_id = ? AND status = 'active' 
    LIMIT 1
");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    // Create a new cell group
    $leader_info = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ? LIMIT 1");
    $leader_info->bind_param("i", $leader_id);
    $leader_info->execute();
    $leader = $leader_info->get_result()->fetch_assoc();
    $leader_info->close();

    $group_name = $leader ? $leader['leader_name'] . "'s Cell Group" : "Unnamed Group";
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

// ✅ Step 2: Assign members and sync both users + cell_group_members
$updated = 0;

foreach ($member_ids as $member_id) {
    if (!is_numeric($member_id)) continue;

    // Fetch the member’s user_code
    $get_user = $mysqli->prepare("SELECT user_code FROM users WHERE id = ?");
    $get_user->bind_param("i", $member_id);
    $get_user->execute();
    $user = $get_user->get_result()->fetch_assoc();
    $get_user->close();

    if (!$user) continue;
    $user_code = $user['user_code'];

    // Update user's leader assignment
    $update_user = $mysqli->prepare("
        UPDATE users 
        SET leader_id = ?, last_unassigned_at = NULL, is_cell_member = 1 
        WHERE id = ?
    ");
    $update_user->bind_param("ii", $leader_id, $member_id);
    $update_user->execute();
    $update_user->close();

    // Insert into cell_group_members if not already there
    $check_member = $mysqli->prepare("
        SELECT id FROM cell_group_members 
        WHERE cell_group_id = ? AND user_code = ?
    ");
    $check_member->bind_param("is", $group_id, $user_code);
    $check_member->execute();
    $exists = $check_member->get_result()->num_rows > 0;
    $check_member->close();

    if (!$exists) {
        $insert_member = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, user_code)
            VALUES (?, ?)
        ");
        $insert_member->bind_param("is", $group_id, $user_code);
        $insert_member->execute();
        $insert_member->close();
    }

    $updated++;
}

echo json_encode(['success' => true, 'message' => "✅ Successfully reassigned $updated member(s) to the leader’s group."]);
exit;
?>
