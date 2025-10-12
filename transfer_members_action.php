<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_group_id'], $_POST['member_ids'])) {
    $to_group = intval($_POST['to_group_id']);
    $member_codes = $_POST['member_ids'];
    $updated = 0;

    foreach ($member_codes as $code) {
        // Remove from old group
        $mysqli->query("DELETE FROM cell_group_members WHERE user_code = '$code'");
        // Insert into new group
        $stmt = $mysqli->prepare("INSERT INTO cell_group_members (cell_group_id, user_code) VALUES (?, ?)");
        $stmt->bind_param("is", $to_group, $code);
        $stmt->execute();
        $stmt->close();
        $updated++;
    }

    header("Location: cell_groups_admin.php?msg=✅ $updated member(s) transferred successfully!");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid transfer request.");
exit();
?>
