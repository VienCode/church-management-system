<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can reactivate

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["leader_id"])) {
    $leader_id = intval($_POST["leader_id"]);

    // 🧩 1️⃣ Fetch inactive leader record
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

    // 🧩 2️⃣ Fetch corresponding user
    $user_stmt = $mysqli->prepare("
        SELECT id, role_id, user_code, is_cell_member 
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ");
    $user_stmt->bind_param("s", $leader_email);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user) {
        header("Location: cell_groups_admin.php?msg=❌ No user found for leader '$leader_name'.");
        exit();
    }

    $user_id = $user['id'];
    $old_code = $user['user_code'];

    // 🧩 3️⃣ Safely fix user code prefix (M → L)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1);
    }

    // 🧩 4️⃣ Reactivate leader record
    $reactivate_leader = $mysqli->prepare("
        UPDATE leaders 
        SET status = 'active', deactivated_at = NULL 
        WHERE leader_id = ?
    ");
    $reactivate_leader->bind_param("i", $leader_id);
    $reactivate_leader->execute();
    $reactivate_leader->close();

    // 🧩 5️⃣ Reactivate or create their cell group
    $check_group = $mysqli->prepare("
        SELECT id FROM cell_groups WHERE leader_id = ? LIMIT 1
    ");
    $check_group->bind_param("i", $leader_id);
    $check_group->execute();
    $group = $check_group->get_result()->fetch_assoc();
    $check_group->close();

    if ($group) {
        // Reactivate group if it exists
        $mysqli->query("
            UPDATE cell_groups 
            SET status = 'active', archived_at = NULL 
            WHERE leader_id = $leader_id
        ");
    } else {
        // Create a new cell group
        $group_name = $leader_name . "'s Cell Group";
        $create_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $create_group->bind_param("si", $group_name, $leader_id);
        $create_group->execute();
        $create_group->close();
    }

    // 🧩 6️⃣ Update user record
    $update_user = $mysqli->prepare("
        UPDATE users 
        SET role_id = 2, user_code = ?, is_cell_member = 1
        WHERE id = ?
    ");
    $update_user->bind_param("si", $new_code, $user_id);
    $update_user->execute();
    $update_user->close();

    // 🧩 7️⃣ Ensure user’s leader_id points to themselves (consistency fix)
    $update_self = $mysqli->prepare("
        UPDATE users 
        SET leader_id = NULL 
        WHERE id = ?
    ");
    $update_self->bind_param("i", $user_id);
    $update_self->execute();
    $update_self->close();

    // 🧩 8️⃣ Re-enable any missing cell group membership
    // (Optional: if leader’s user_code should also exist in cell_group_members as reference)
    $group_result = $mysqli->query("
        SELECT id FROM cell_groups WHERE leader_id = $leader_id AND status = 'active' LIMIT 1
    ");
    if ($group_result && $group_result->num_rows > 0) {
        $group_id = $group_result->fetch_assoc()['id'];

        // Prevent duplicate insertion
        $check_member = $mysqli->prepare("
            SELECT id FROM cell_group_members WHERE cell_group_id = ? AND user_code = ?
        ");
        $check_member->bind_param("is", $group_id, $new_code);
        $check_member->execute();
        $exists = $check_member->get_result()->num_rows > 0;
        $check_member->close();

        if (!$exists) {
            $insert_leader_member = $mysqli->prepare("
                INSERT INTO cell_group_members (cell_group_id, user_code)
                VALUES (?, ?)
            ");
            $insert_leader_member->bind_param("is", $group_id, $new_code);
            $insert_leader_member->execute();
            $insert_leader_member->close();
        }
    }

    // 🧩 9️⃣ Log reactivation
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Member', 'Leader (Reactivated)', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // ✅ Success Redirect
    header("Location: cell_groups_admin.php?msg=✅ $leader_name successfully reactivated and restored as Leader.");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid request.");
exit();
?>
