<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // Centralized logging
restrict_to_roles([ROLE_ADMIN]); // Only Admins can demote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🧩 1️⃣ Fetch user's info
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
        header("Location: manage_users.php?msg=❌ User not found.");
        exit();
    }

    $fullname = trim($user["firstname"] . " " . $user["lastname"]);
    $email = trim($user["email"]);
    $old_code = $user["user_code"];

    // 🧠 Verify current role is Leader
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: manage_users.php?msg=⚠️ $fullname is not currently a leader.");
        exit();
    }

    // 🧩 2️⃣ Get leader_id and group info
    $leader_stmt = $mysqli->prepare("
        SELECT l.leader_id, cg.id AS group_id, cg.group_name
        FROM leaders l
        LEFT JOIN cell_groups cg ON cg.leader_id = l.leader_id
        WHERE l.email = ? AND l.status = 'active'
        LIMIT 1
    ");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader_data = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    $leader_id = $leader_data['leader_id'] ?? null;
    $group_id  = $leader_data['group_id'] ?? null;

    // 🧩 3️⃣ Check for members before allowing demotion
    if ($leader_id && $group_id) {
        $member_check = $mysqli->prepare("
            SELECT COUNT(*) AS total_members 
            FROM cell_group_members 
            WHERE cell_group_id = ?
        ");
        $member_check->bind_param("i", $group_id);
        $member_check->execute();
        $member_count = $member_check->get_result()->fetch_assoc()['total_members'];
        $member_check->close();

        if ($member_count > 0) {
            // 🚫 Stop demotion if members still exist
            $msg = "⚠️ Cannot demote $fullname yet. There are still $member_count members assigned. 
                    Please reassign them first.";
            header("Location: manage_users.php?msg=" . urlencode($msg));
            exit();
        }
    }

    // 🧩 4️⃣ Proceed safely with demotion (no members)
    $new_code = preg_replace('/^L/', 'M', $old_code);
    if ($new_code === $old_code) {
        $new_code = 'M' . substr($old_code, 1);
    }

    $mysqli->query("SET FOREIGN_KEY_CHECKS=0");

    // Update user role and unlink leader references
    $update = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, user_code = ?, leader_id = NULL, is_cell_member = 0
        WHERE id = ?
    ");
    $update->bind_param("si", $new_code, $user_id);
    $update->execute();
    $update->close();

    // ✅ Clean up leader & cell group records
    if ($leader_id) {
        $mysqli->query("
            UPDATE leaders 
            SET status = 'inactive', deactivated_at = NOW()
            WHERE leader_id = $leader_id
        ");

        $mysqli->query("
            UPDATE cell_groups 
            SET status = 'archived', archived_at = NOW()
            WHERE leader_id = $leader_id
        ");
    }

    $mysqli->query("SET FOREIGN_KEY_CHECKS=1");

    // 🧾 Logging
    $admin_id = $_SESSION['user_id'];

    // Role log
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // Centralized logs
    log_role_change(
        $mysqli,
        $_SESSION['user_id'],
        $_SESSION['role'],
        $fullname,
        'Member',
        'DEMOTE'
    );

    log_action(
        $mysqli,
        $_SESSION['user_id'],
        $_SESSION['role'],
        'DEMOTE_LEADER',
        "Demoted leader $fullname to member. Group archived.",
        'High'
    );

    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted to Member. Group archived successfully.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
