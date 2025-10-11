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

    // 🔹 Validate if user is currently a leader
    if ($user["role_id"] != ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not a leader.");
        exit();
    }

    // ✅ Step 1: Demote to member (role_id = 3)
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET role_id = 3, 
            user_code = CONCAT('M', SUBSTRING(user_code, 2))
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Step 2: Unassign all members under this leader (if any)
    $unassign = $mysqli->prepare("
        UPDATE users 
        SET leader_id = NULL 
        WHERE leader_id IN (
            SELECT leader_id FROM leaders WHERE LOWER(email) = LOWER(?)
        )
    ");
    $unassign->bind_param("s", $email);
    $unassign->execute();
    $unassign->close();

    // ✅ Step 3: Delete from leaders table using email
    $delete = $mysqli->prepare("DELETE FROM leaders WHERE LOWER(email) = LOWER(?)");
    $delete->bind_param("s", $email);
    $delete->execute();
    $deleted_rows = $delete->affected_rows;
    $delete->close();

    // ✅ Step 4: Log the role change
    $admin_id = $_SESSION['user_id'];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // ✅ Step 5: Redirect with result
    if ($deleted_rows > 0) {
        header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted and removed from Leaders table.");
    } else {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname demoted to Member, but no matching record found in Leaders table.");
    }
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
