<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = intval($_POST['member_id']);

    // ✅ Get user_code first
    $stmt = $mysqli->prepare("SELECT user_code, leader_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $code = $user['user_code'];

        // ✅ Remove from cell_group_members
        $del_stmt = $mysqli->prepare("DELETE FROM cell_group_members WHERE user_code = ?");
        $del_stmt->bind_param("s", $code);
        $del_stmt->execute();
        $del_stmt->close();

        // ✅ Update user leader_id and mark unassigned
        $update_stmt = $mysqli->prepare("
            UPDATE users 
            SET leader_id = NULL, last_unassigned_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->bind_param("i", $member_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid request.");
exit();
?>
