<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can demote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch leader info
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

    $fullname = $user["firstname"] . ' ' . $user["lastname"];
    $email = $user["email"];

    // Check if currently a leader
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not a leader.");
        exit();
    }

    // ✅ Find their leader record
    $leader_stmt = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $leader_stmt->bind_param("s", $email);
    $leader_stmt->execute();
    $leader_result = $leader_stmt->get_result();
    $leader = $leader_result->fetch_assoc();
    $leader_stmt->close();

    // ✅ Demote to member (role_id = 3)
    $stmt = $mysqli->prepare("UPDATE users SET role_id = 3, user_code = CONCAT('M', SUBSTRING(user_code, 2)) WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    if ($leader) {
        $leader_id = $leader["leader_id"];

        // Unassign their members
        $unassign = $mysqli->prepare("UPDATE users SET leader_id = NULL WHERE leader_id = ?");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();

        // ✅ Remove from leaders table
        $delete = $mysqli->prepare("DELETE FROM leaders WHERE leader_id = ?");
        $delete->bind_param("i", $leader_id);
        $delete->execute();
        $delete->close();
    }

    // ✅ Log the role change (optional)
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $admin_id = $_SESSION['user_id'];
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted to Member and removed from leaders table.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
