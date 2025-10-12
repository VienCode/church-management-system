<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = intval($_POST['member_id']);

    // Get user_code first
    $user = $mysqli->query("SELECT user_code FROM users WHERE id = $member_id")->fetch_assoc();
    if ($user) {
        $code = $user['user_code'];
        $mysqli->query("DELETE FROM cell_group_members WHERE user_code = '$code'");
        $mysqli->query("UPDATE users SET leader_id = NULL, last_unassigned_at = NOW() WHERE id = $member_id");
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
header("Location: cell_groups_admin.php?msg=❌ Invalid request.");
exit();
?>
