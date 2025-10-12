<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can reactivate

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["leader_id"])) {
    $leader_id = intval($_POST["leader_id"]);

    // 1️⃣ Fetch inactive leader record
    $stmt = $mysqli->prepare("
        SELECT leader_name, email, contact 
        FROM leaders 
        WHERE leader_id = ? AND status = 'inactive'
    ");
    $stmt->bind_param("i", $leader_id);
    $stmt->execute();
    $leader = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$leader) {
        header("Location: cell_groups_admin.php?msg=❌ Leader not found or already active.");
        exit();
    }

    $leader_name = trim($leader["leader_name"]);
    $leader_email = trim($leader["email"]);
    $leader_contact = trim($leader["contact"]);

    // 2️⃣ Fetch associated user
    $user_stmt = $mysqli->prepare("
        SELECT id, user_code, role_id, is_cell_member 
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ");
    $user_stmt->bind_param("s", $leader_email);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user) {
        header("Location: cell_groups_admin.php?msg=❌ No user found linked to leader '$leader_name'.");
        exit();
    }

    $user_id = $user['id'];
    $old_code = $user['user_code'];

    // 3️⃣ Ensure user_code starts with L (Leader)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1);
    }

    // 4️⃣ Reactivate leader record
    $update_leader = $mysqli->prepare("
        UPDATE leaders 
        SET status = 'active', deactivated_at = NULL 
        WHERE leader_id = ?
    ");
    $update_leader->bind_param("i", $leader_id);
    $update_leader->execute();
    $update_leader->close();

    // 5️⃣ Ensure the leader has an active cell group
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
        $group_name = $leader_name . "'s Cell Group";
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
        $mysqli->query("
            UPDATE cell_groups 
            SET status = 'active', archived_at = NULL 
            WHERE id = $group_id
        ");
    }

    // 6️⃣ Update user record to Leader
    $update_user = $mysqli->prepare("
        UPDATE users 
        SET role_id = 2, user_code = ?, is_cell_member = 1 
        WHERE id = ?
    ");
    $update_user->bind_param("si", $new_code, $user_id);
    $update_user->execute();
    $update_user->close();

    // 7️⃣ Re-link user to their leader record if needed
    $leader_fix = $mysqli->prepare("
        UPDATE users 
        SET leader_id = NULL 
        WHERE id = ?
    ");
    $leader_fix->bind_param("i", $user_id);
    $leader_fix->execute();
    $leader_fix->close();

    // 8️⃣ Ensure leader appears in their own group (for visibility)
    $check_member = $mysqli->prepare("
        SELECT id FROM cell_group_members 
        WHERE cell_group_id = ? AND user_code = ?
    ");
    $check_member->bind_param("is", $group_id, $new_code);
    $check_member->execute();
    $exists = $check_member->get_result()->num_rows > 0;
    $check_member->close();

    if (!$exists) {
        $insert_leader = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, user_code)
            VALUES (?, ?)
        ");
        $insert_leader->bind_param("is", $group_id, $new_code);
        $insert_leader->execute();
        $insert_leader->close();
    }

    // 9️⃣ Optional: Reattach members that still reference this leader_id
    $reattach = $mysqli->prepare("
        SELECT id, user_code FROM users 
        WHERE leader_id = ? AND role_id = 3
    ");
    $reattach->bind_param("i", $leader_id);
    $reattach->execute();
    $members = $reattach->get_result();
    $reattach->close();

    while ($m = $members->fetch_assoc()) {
        $code = $m['user_code'];

        // Add back to cell group if missing
        $check_link = $mysqli->prepare("
            SELECT id FROM cell_group_members 
            WHERE cell_group_id = ? AND user_code = ?
        ");
        $check_link->bind_param("is", $group_id, $code);
        $check_link->execute();
        $exists = $check_link->get_result()->num_rows > 0;
        $check_link->close();

        if (!$exists) {
            $add_member = $mysqli->prepare("
                INSERT INTO cell_group_members (cell_group_id, user_code)
                VALUES (?, ?)
            ");
            $add_member->bind_param("is", $group_id, $code);
            $add_member->execute();
            $add_member->close();
        }
    }

    // 🔟 Log reactivation
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Member', 'Leader (Reactivated)', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: cell_groups_admin.php?msg=✅ $leader_name successfully reactivated and restored as Leader.");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid request.");
exit();
?>
