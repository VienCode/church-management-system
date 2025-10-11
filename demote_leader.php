<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch leader info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, user_code, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = trim($user["firstname"] . ' ' . $user["lastname"]);
    $email = trim($user["email"]);

    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not currently a leader.");
        exit();
    }

    // Find the leader_id
    $leader_stmt = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? AND status = 'active'");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    $leader_id = $leader['leader_id'] ?? null;

    // ✅ Demote the user
    $update = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, user_code = CONCAT('M', SUBSTRING(user_code, 2)), leader_id = NULL
        WHERE id = ?
    ");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();

    // ✅ Unassign all members under this leader
    if ($leader_id) {
        $unassign = $mysqli->prepare("UPDATE users SET leader_id = NULL WHERE leader_id = ?");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();

        // Mark leader & group inactive
        $mysqli->query("UPDATE leaders SET status = 'inactive', deactivated_at = NOW() WHERE leader_id = $leader_id");
        $mysqli->query("UPDATE cell_groups SET status = 'inactive', archived_at = NOW() WHERE leader_id = $leader_id");
    }

    // ✅ Log demotion
    $admin_id = $_SESSION['user_id'];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted. Their group is now inactive and members unassigned.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
