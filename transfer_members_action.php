<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_leader_id'], $_POST['member_id'])) {
    $new_leader_id = intval($_POST['new_leader_id']);
    $member_id = intval($_POST['member_id']);
    $updated = 0;

    // ✅ Fetch new leader info
    $leader_stmt = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE leader_id = ? AND status = 'active'");
    $leader_stmt->bind_param("i", $new_leader_id);
    $leader_stmt->execute();
    $leader = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    if (!$leader) {
        header("Location: cell_groups_admin.php?msg=❌ Invalid or inactive leader selected.");
        exit();
    }

    $leader_name = $leader['leader_name'];

    // ✅ Fetch or create leader’s active cell group
    $group_stmt = $mysqli->prepare("
        SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1
    ");
    $group_stmt->bind_param("i", $new_leader_id);
    $group_stmt->execute();
    $group = $group_stmt->get_result()->fetch_assoc();
    $group_stmt->close();

    if (!$group) {
        $group_name = $leader_name . "'s Cell Group";
        $create_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $create_group->bind_param("si", $group_name, $new_leader_id);
        $create_group->execute();
        $group_id = $create_group->insert_id;
        $create_group->close();
    } else {
        $group_id = $group['id'];
    }

    // ✅ Fetch member user_code
    $user_stmt = $mysqli->prepare("SELECT user_code FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $member_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user_data) {
        header("Location: cell_groups_admin.php?msg=❌ Member not found.");
        exit();
    }

    $user_code = $user_data['user_code'];

    // ✅ Remove member from any old group to avoid duplicates
    $del_stmt = $mysqli->prepare("DELETE FROM cell_group_members WHERE user_code = ?");
    $del_stmt->bind_param("s", $user_code);
    $del_stmt->execute();
    $del_stmt->close();

    // ✅ Add member to the new group
    $insert_stmt = $mysqli->prepare("
        INSERT INTO cell_group_members (cell_group_id, user_code)
        VALUES (?, ?)
    ");
    $insert_stmt->bind_param("is", $group_id, $user_code);
    $insert_stmt->execute();
    $insert_stmt->close();

    // ✅ Update leader reference in users table
    $update_stmt = $mysqli->prepare("
        UPDATE users 
        SET leader_id = ?, last_unassigned_at = NULL 
        WHERE id = ?
    ");
    $update_stmt->bind_param("ii", $new_leader_id, $member_id);
    $update_stmt->execute();
    $update_stmt->close();

    // ✅ Optional logging
    $admin_id = $_SESSION['user_id'];
    $log = $mysqli->prepare("
        INSERT INTO transfer_logs (member_id, user_code, from_group_id, to_group_id, transferred_by, transferred_at)
        VALUES (?, ?, NULL, ?, ?, NOW())
    ");
    $log->bind_param("isii", $member_id, $user_code, $group_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: cell_groups_admin.php?msg=✅ Member successfully transferred to $leader_name’s group!");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid transfer request.");
exit();
?>