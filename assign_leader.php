<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // Centralized logging helper
restrict_to_roles([ROLE_ADMIN]); // Only Admins can promote

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch user info
    $stmt = $mysqli->prepare("
        SELECT id, firstname, lastname, email, contact, user_code, role_id 
        FROM users 
        WHERE id = ?
    ");
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

    // Prevent promoting if already Leader or Admin
    if ($old_role == ROLE_LEADER || $old_role == ROLE_ADMIN) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is already a leader or admin.");
        exit();
    }

    // Promote to Leader role and update user code
    $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");

    $update_role = $mysqli->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    $role_leader = ROLE_LEADER;
    $update_role->bind_param("ii", $role_leader, $user_id);
    $update_role->execute();
    $update_role->close();

    // Prefix code with "L" if not already
    $new_code = preg_replace('/^[MN]/', 'L', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1);
    }
    $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $update_code->bind_param("si", $new_code, $user_id);
    $update_code->execute();
    $update_code->close();

    $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");

    // Add or reactivate leader record
    $check_leader = $mysqli->prepare("SELECT leader_id, status FROM leaders WHERE email = ?");
    $check_leader->bind_param("s", $email);
    $check_leader->execute();
    $leader_result = $check_leader->get_result()->fetch_assoc();
    $check_leader->close();

    if (!$leader_result) {
        // Create new leader
        $insert = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $insert->bind_param("sss", $fullname, $contact, $email);
        $insert->execute();
        $leader_id = $insert->insert_id;
        $insert->close();
    } else {
        // Reactivate if necessary
        $leader_id = $leader_result['leader_id'];
        if ($leader_result['status'] !== 'active') {
            $mysqli->query("
                UPDATE leaders 
                SET status = 'active', deactivated_at = NULL 
                WHERE leader_id = $leader_id
            ");
        }
    }

    // Check for any existing group (active or archived)
    $check_group = $mysqli->prepare("
        SELECT id, status 
        FROM cell_groups 
        WHERE leader_id = ?
        ORDER BY 
            CASE 
                WHEN status = 'active' THEN 1
                WHEN status = 'inactive' THEN 2
                WHEN status = 'archived' THEN 3
            END
        LIMIT 1
    ");
    $check_group->bind_param("i", $leader_id);
    $check_group->execute();
    $group_result = $check_group->get_result()->fetch_assoc();
    $check_group->close();

    if ($group_result) {
        $group_id = $group_result['id'];

        // Reactivate if archived
        if ($group_result['status'] === 'archived') {
            $reactivate = $mysqli->prepare("
                UPDATE cell_groups 
                SET status = 'active', archived_at = NULL 
                WHERE id = ?
            ");
            $reactivate->bind_param("i", $group_id);
            $reactivate->execute();
            $reactivate->close();
            log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'RESTORE_GROUP',
                "Automatically reactivated archived group ID #$group_id for $fullname", 'Normal');
        }
    } else {
        // No existing group — create one
        $group_name = $fullname . "'s Cell Group";
        $insert_group = $mysqli->prepare("
            INSERT INTO cell_groups (group_name, leader_id, status, created_at)
            VALUES (?, ?, 'active', NOW())
        ");
        $insert_group->bind_param("si", $group_name, $leader_id);
        $insert_group->execute();
        $group_id = $insert_group->insert_id;
        $insert_group->close();

        log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'CREATE_GROUP',
            "Created new Cell Group '$group_name' (ID #$group_id) for $fullname", 'High');
    }

    // Log promotion
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, ?, 'Leader', ?, NOW())
    ");
    $log->bind_param("iii", $user_id, $old_role, $admin_id);
    $log->execute();
    $log->close();

    log_role_change(
        $mysqli,
        $_SESSION['user_id'],
        $_SESSION['role'],
        $fullname,
        'Leader',
        'PROMOTE'
    );

    log_action(
        $mysqli,
        $_SESSION['user_id'],
        $_SESSION['role'],
        'PROMOTE_LEADER',
        "Promoted $fullname to Leader and ensured active group association.",
        'High'
    );

    // Update membership flag
    $mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $user_id");

    // Redirect with success
    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader successfully");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
