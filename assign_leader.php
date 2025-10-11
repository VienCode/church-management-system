<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🔹 1. Fetch user info
    $stmt = $mysqli->prepare("
        SELECT id, firstname, lastname, email, contact, user_code, role_id
        FROM users 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
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

    // 🔹 2. Update role to LEADER (role_id = 2)
    $update_role = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update_role->bind_param("i", $user_id);
    $update_role->execute();
    $update_role->close();

    // 🔹 3. Update user_code prefix (M → L)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1);
    }

    $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $update_code->bind_param("si", $new_code, $user_id);
    $update_code->execute();
    $update_code->close();

    // 🔹 4. Add or reactivate in leaders table
    $check_leader = $mysqli->prepare("SELECT leader_id, status FROM leaders WHERE email = ? LIMIT 1");
    $check_leader->bind_param("s", $email);
    $check_leader->execute();
    $leader_result = $check_leader->get_result();
    $leader = $leader_result->fetch_assoc();
    $check_leader->close();

    if (!$leader) {
        // ✅ New leader entry
        $insertLeader = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $insertLeader->bind_param("sss", $fullname, $contact, $email);
        $insertLeader->execute();
        $leader_id = $insertLeader->insert_id;
        $insertLeader->close();
    } else {
        // ✅ Reactivate existing leader
        $leader_id = $leader['leader_id'];
        $mysqli->query("
            UPDATE leaders 
            SET status = 'active', deactivated_at = NULL, leader_name = '$fullname', contact = '$contact'
            WHERE leader_id = $leader_id
        ");
    }

    // 🔹 5. Ensure cell group exists for this leader
    $check_group = $mysqli->prepare("
        SELECT id 
        FROM cell_groups 
        WHERE leader_id = ? AND status = 'active' 
        LIMIT 1
    ");
    $check_group->bind_param("i", $leader_id);
    $check_group->execute();
    $group_exists = $check_group->get_result()->fetch_assoc();
    $check_group->close();

    if (!$group_exists) {
        $group_name = $fullname . "'s Cell Group";
        $insert_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $insert_group->bind_param("si", $group_name, $leader_id);
        $insert_group->execute();
        $insert_group->close();
    }

    // 🔹 6. Log promotion in role_logs
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, ?, 'Leader', ?, NOW())
    ");
    $log->bind_param("iii", $user_id, $old_role, $admin_id);
    $log->execute();
    $log->close();

    // 🔹 7. Ensure user marked as cell group member
    $mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $user_id");

    // 🔹 8. Redirect back
    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader and assigned an active Cell Group!");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
