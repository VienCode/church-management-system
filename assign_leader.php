<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can promote

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🔹 Fetch user info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, contact, user_code, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = trim($user["firstname"] . " " . $user["lastname"]);
    $email = trim($user["email"]);
    $contact = trim($user["contact"]);
    $old_code = $user["user_code"];
    $old_role = $user["role_id"];

    // ✅ Prevent self-conflict in triggers
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");

    // 🔹 1. Promote to Leader role
    $update_role = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update_role->bind_param("i", $user_id);
    $update_role->execute();
    $update_role->close();

    // 🔹 2. Update user_code prefix (M → L)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1); // fallback if no M prefix
    }

    $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $update_code->bind_param("si", $new_code, $user_id);
    $update_code->execute();
    $update_code->close();

    // ✅ Re-enable FK checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");

    // 🔹 3. Add or Reactivate Leader record
    $check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $check_leader->bind_param("s", $email);
    $check_leader->execute();
    $leader_result = $check_leader->get_result()->fetch_assoc();
    $check_leader->close();

    if (!$leader_result) {
        // Create new leader entry
        $insert = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $insert->bind_param("sss", $fullname, $contact, $email);
        $insert->execute();
        $leader_id = $insert->insert_id;
        $insert->close();
    } else {
        // Reactivate existing leader
        $leader_id = $leader_result['leader_id'];
        $mysqli->query("UPDATE leaders SET status = 'active', deactivated_at = NULL WHERE leader_id = $leader_id");
    }

    // 🔹 4. Auto-create cell group if leader has none
    $check_group = $mysqli->prepare("SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active'");
    $check_group->bind_param("i", $leader_id);
    $check_group->execute();
    $group_result = $check_group->get_result()->fetch_assoc();
    $check_group->close();

    if (!$group_result) {
        $group_name = $fullname . "'s Cell Group";
        $insert_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $insert_group->bind_param("si", $group_name, $leader_id);
        $insert_group->execute();
        $insert_group->close();
    }

    // 🔹 5. Log promotion
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, ?, 'Leader', ?, NOW())
    ");
    $log->bind_param("iii", $user_id, $old_role, $admin_id);
    $log->execute();
    $log->close();

    // Maintain is_cell_member flag for new leader
$mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $user_id");

    // Ensure all members under this leader are also cell members
    $mysqli->query("
        UPDATE users 
        SET is_cell_member = 1 
        WHERE leader_id = (SELECT leader_id FROM leaders WHERE email = '$email' LIMIT 1)
    ");


    // 🔹 6. Redirect
    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader successfully and assigned to a new Cell Group!");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
