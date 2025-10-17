<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; //  Include centralized logging helper
restrict_to_roles([ROLE_ADMIN]); // Only Admins can demote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🧩 1️⃣ Fetch the user's info
    $stmt = $mysqli->prepare("
        SELECT id, firstname, lastname, email, user_code, role_id 
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
    $old_code = $user["user_code"];

    //  Verify the user is currently a leader
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not currently a leader.");
        exit();
    }

    //  Get leader_id from leaders table
    $leader_stmt = $mysqli->prepare("
        SELECT leader_id 
        FROM leaders 
        WHERE email = ? AND status = 'active' 
        LIMIT 1
    ");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader_data = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    $leader_id = $leader_data['leader_id'] ?? null;

    //  Convert their user code from L → M (if needed)
    $new_code = preg_replace('/^L/', 'M', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'M' . substr($old_code, 1);
    }

    //  Temporarily disable FK checks (avoid trigger issue)
    $mysqli->query("SET FOREIGN_KEY_CHECKS=0");

    //  Update the user's role, code, and clear leader link
    $update = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, user_code = ?, leader_id = NULL, is_cell_member = 0
        WHERE id = ?
    ");
    $update->bind_param("si", $new_code, $user_id);
    $update->execute();
    $update->close();

    //  Unassign all members linked to this leader (safe bulk update)
    if ($leader_id) {
        // Unassign users under this leader
        $unassign = $mysqli->prepare("
            UPDATE users 
            SET leader_id = NULL, last_unassigned_at = NOW()
            WHERE leader_id = ?
        ");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();

        // Remove from cell_group_members (but don't drop users)
        $delete_members = $mysqli->prepare("
            DELETE cgm 
            FROM cell_group_members cgm
            INNER JOIN cell_groups cg ON cgm.cell_group_id = cg.id
            WHERE cg.leader_id = ?
        ");
        $delete_members->bind_param("i", $leader_id);
        $delete_members->execute();
        $delete_members->close();

        // Mark leader record inactive (supports reactivation)
        $mysqli->query("
            UPDATE leaders 
            SET status = 'inactive', deactivated_at = NOW()
            WHERE leader_id = $leader_id
        ");

        // Mark their cell group inactive (archivable)
        $mysqli->query("
            UPDATE cell_groups 
            SET status = 'inactive', archived_at = NOW()
            WHERE leader_id = $leader_id
        ");
    }

    //  Re-enable FK checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1");

    //  Log the role change (local + centralized)
    $admin_id = $_SESSION['user_id'];

    // Local role log
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    //  Centralized role change log
    log_role_change(
        $mysqli,
        $_SESSION['user_id'],   // Who performed the action
        $_SESSION['role'],      // Role of the one performing (Admin)
        $fullname,              // Whose role was changed
        'Member',               // New role after demotion
        'DEMOTE'                // Action type
    );

    // Centralized action log for unassigning members
    log_action(
        $mysqli,
        $_SESSION['user_id'],
        $_SESSION['role'],
        'UNASSIGN',
        "Unassigned members previously under leader {$fullname}",
        'High'
    );

    // Done
    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted to Member. Group archived and members unassigned safely.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
