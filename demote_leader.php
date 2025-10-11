<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can demote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 🔹 Fetch leader info from users table
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, user_code, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = trim($user["firstname"] . ' ' . $user["lastname"]);
    $email = trim($user["email"]);
    $old_code = $user["user_code"];

    // 🔸 Ensure this user is a Leader
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not currently a leader.");
        exit();
    }

    // ✅ Step 1: Retrieve corresponding leader record
    $leader_stmt = $mysqli->prepare("SELECT leader_id FROM leaders WHERE LOWER(email) = LOWER(?)");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader_result = $leader_stmt->get_result();
    $leader = $leader_result->fetch_assoc();
    $leader_stmt->close();

    // If leader record doesn't exist, still proceed safely
    $leader_id = $leader['leader_id'] ?? null;

    // ✅ Step 2: Demote to Member (role_id = 3) and update user_code prefix (L → M)
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, 
            user_code = CONCAT('M', SUBSTRING(user_code, 2)),
            leader_id = NULL
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Step 3: Unassign members under this leader (if any)
    if ($leader_id) {
        $unassign = $mysqli->prepare("UPDATE users SET leader_id = NULL WHERE leader_id = ?");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();
    }

    // ✅ Step 4: Delete cell group and leader record (cascade)
    if ($leader_id) {
        // Delete the leader’s cell group first
        $delete_group = $mysqli->prepare("DELETE FROM cell_groups WHERE leader_id = ?");
        $delete_group->bind_param("i", $leader_id);
        $delete_group->execute();
        $delete_group->close();

        // Delete the leader record itself
        $delete_leader = $mysqli->prepare("DELETE FROM leaders WHERE leader_id = ?");
        $delete_leader->bind_param("i", $leader_id);
        $delete_leader->execute();
        $delete_leader->close();
    }

    // ✅ Step 5: Log the role change
    $admin_id = $_SESSION['user_id'];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // ✅ Step 6: Success redirect
    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted to Member, removed from Leaders and their Cell Group deleted.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
