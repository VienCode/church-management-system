<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_group_id'], $_POST['member_ids'])) {
    $to_group = intval($_POST['to_group_id']);
    $member_codes = $_POST['member_ids'];
    $updated = 0;

    foreach ($member_codes as $code) {
        // Validate that the user exists before inserting
        $check_user = $mysqli->prepare("SELECT COUNT(*) FROM users WHERE user_code = ?");
        $check_user->bind_param("s", $code);
        $check_user->execute();
        $check_user->bind_result($exists);
        $check_user->fetch();
        $check_user->close();

        if ($exists) {
            // Remove from old group
            $del = $mysqli->prepare("DELETE FROM cell_group_members WHERE user_code = ?");
            $del->bind_param("s", $code);
            $del->execute();
            $del->close();

            // Add to new group
            $insert = $mysqli->prepare("INSERT INTO cell_group_members (cell_group_id, user_code) VALUES (?, ?)");
            $insert->bind_param("is", $to_group, $code);
            $insert->execute();
            $insert->close();

            // Update leader_id in users table to match the new group’s leader
            $update = $mysqli->prepare("
                UPDATE users 
                SET leader_id = (SELECT leader_id FROM cell_groups WHERE id = ? LIMIT 1)
                WHERE user_code = ?
            ");
            $update->bind_param("is", $to_group, $code);
            $update->execute();
            $update->close();

            $updated++;
        }
    }

    header("Location: cell_groups_admin.php?msg=✅ $updated member(s) transferred successfully!");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid transfer request.");
exit();
?>
