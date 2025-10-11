<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

header('Content-Type: application/json');

// ✅ Validate request data
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

// ✅ Step 1: Check if leader has an active cell group
$group_stmt = $mysqli->prepare("
    SELECT id 
    FROM cell_groups 
    WHERE leader_id = ? AND status = 'active' 
    LIMIT 1
");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group_result = $group_stmt->get_result();
$group = $group_result->fetch_assoc();
$group_stmt->close();

// ✅ Step 2: Auto-create a cell group if none exists
if (!$group) {
    $leader_data = $mysqli->prepare("
        SELECT leader_name 
        FROM leaders 
        WHERE leader_id = ? 
        LIMIT 1
    ");
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

// ✅ Step 3: Assign members and insert them into cell_group_members
$updated = 0;
foreach ($member_ids as $member_id) {
    // Skip invalid IDs
    if (!is_numeric($member_id)) continue;

    // Update leader_id in users table
    $update_user = $mysqli->prepare("UPDATE users SET leader_id = ?, last_unassigned_at = NULL WHERE id = ?");
    $update_user->bind_param("ii", $leader_id, $member_id);
    $update_user->execute();
    $update_user->close();

    // Check if member is already linked to this cell group
    $check_stmt = $mysqli->prepare("
        SELECT id 
        FROM cell_group_members 
        WHERE cell_group_id = ? AND member_id = ?
    ");
    $check_stmt->bind_param("ii", $group_id, $member_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if (!$exists) {
        $insert_stmt = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, member_id)
            VALUES (?, ?)
        ");
        $insert_stmt->bind_param("ii", $group_id, $member_id);
        $insert_stmt->execute();
        $insert_stmt->close();
        $updated++;
    }
}

// ✅ Step 4: Return success response
echo json_encode([
    'success' => true,
    'message' => "✅ Successfully reassigned $updated member(s) and added them to the leader’s cell group."
]);
exit;
?>
