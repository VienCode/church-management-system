<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admin-only access

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🔹 1. Fetch leader info
    $stmt = $mysqli->prepare("
        SELECT id, firstname, lastname, email, contact, user_code, role_id 
        FROM users 
        WHERE id = ?
        LIMIT 1
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
    $old_code = $user["user_code"];

    // 🔹 2. Verify current role is LEADER
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not currently a leader.");
        exit();
    }

    // 🔹 3. Find the leader_id in leaders table
    $leader_stmt = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? AND status = 'active' LIMIT 1");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    $leader_id = $leader['leader_id'] ?? null;

    // 🔹 4. Demote user: change role, prefix, and leader_id
    $new_code = preg_replace('/^L/', 'M', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'M' . substr($old_code, 1);
    }

    $update = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, user_code = ?, leader_id = NULL 
        WHERE id = ?
    ");
    $update->bind_param("si", $new_code, $user_id);
    $update->execute();
    $update->close();

    // 🔹 5. Unassign all members from this leader
    if ($leader_id) {
        // Unassign members but retain cell membership
        $unassign = $mysqli->prepare("
            UPDATE users 
            SET leader_id = NULL 
            WHERE leader_id = ?
        ");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();

        // Soft-deactivate the leader and cell group
        $mysqli->query("
            UPDATE leaders 
            SET status = 'inactive', deactivated_at = NOW() 
            WHERE leader_id = $leader_id
        ");

        $mysqli->query("
            UPDATE cell_groups 
            SET status = 'inactive', archived_at = NOW() 
            WHERE leader_id = $leader_id
        ");
    }

    // 🔹 6. Update is_cell_member field (still part of a cell group system)
    $mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $user_id");

    // 🔹 7. Log demotion
    $admin_id = $_SESSION['user_id'];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // 🔹 8. Redirect
    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted. Their group was deactivated and members unassigned.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
