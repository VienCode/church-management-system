<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admin can promote users

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🔹 Fetch user info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, contact, user_code, role_id FROM users WHERE id = ?");
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

    // ✅ Step 1: Update Role to Leader
    $leader_role_id = ROLE_LEADER;
    $update_role = $mysqli->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    $update_role->bind_param("ii", $leader_role_id, $user_id);
    $update_role->execute();
    $update_role->close();

    // ✅ Step 2: Update User Code (M→L or create new prefix)
    if (preg_match('/^M/', $old_code)) {
        $new_code = preg_replace('/^M/', 'L', $old_code);
    } else {
        $new_code = 'L' . substr($old_code, 1);
    }

    $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $update_code->bind_param("si", $new_code, $user_id);
    $update_code->execute();
    $update_code->close();

    // ✅ Step 3: Add to Leaders Table if not already there
    $check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $check_leader->bind_param("s", $email);
    $check_leader->execute();
    $leader_result = $check_leader->get_result();
    $leader = $leader_result->fetch_assoc();
    $check_leader->close();

    if (!$leader) {
        $insertLeader = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insertLeader->bind_param("sss", $fullname, $contact, $email);
        $insertLeader->execute();
        $leader_id = $insertLeader->insert_id; // Capture new leader_id
        $insertLeader->close();
    } else {
        $leader_id = $leader["leader_id"];
        // Update leader info if changed
        $updateLeader = $mysqli->prepare("
            UPDATE leaders 
            SET leader_name = ?, contact = ?, email = ?
            WHERE leader_id = ?
        ");
        $updateLeader->bind_param("sssi", $fullname, $contact, $email, $leader_id);
        $updateLeader->execute();
        $updateLeader->close();
    }

    // ✅ Step 4: Automatically create a Cell Group for this leader (if not exists)
    $check_group = $mysqli->prepare("SELECT id FROM cell_groups WHERE leader_id = ?");
    $check_group->bind_param("i", $leader_id);
    $check_group->execute();
    $group_exists = $check_group->get_result()->num_rows > 0;
    $check_group->close();

    if (!$group_exists) {
        $group_name = $fullname . "'s Cell Group";
        $create_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $create_group->bind_param("si", $group_name, $leader_id);
        $create_group->execute();
        $create_group->close();
    }

    // ✅ Step 5: Log the Role Change
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, ?, 'Leader', ?, NOW())
    ");
    $log->bind_param("iii", $user_id, $old_role, $admin_id);
    $log->execute();
    $log->close();

    // ✅ Step 6: Redirect
    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader and assigned a new Cell Group!");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
