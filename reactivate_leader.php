<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can reactivate

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["leader_id"])) {
    $leader_id = intval($_POST["leader_id"]);

    // ✅ Fetch leader record
    $stmt = $mysqli->prepare("SELECT leader_name, email FROM leaders WHERE leader_id = ? AND status = 'inactive'");
    $stmt->bind_param("i", $leader_id);
    $stmt->execute();
    $leader = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$leader) {
        header("Location: cell_groups_admin.php?msg=❌ Leader not found or already active.");
        exit();
    }

    $leader_name = $leader["leader_name"];
    $leader_email = $leader["email"];

    // ✅ Find user associated with leader (based on email)
    $user_stmt = $mysqli->prepare("SELECT id, role_id, user_code FROM users WHERE email = ?");
    $user_stmt->bind_param("s", $leader_email);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user) {
        header("Location: cell_groups_admin.php?msg=❌ User not found for leader $leader_name.");
        exit();
    }

    $user_id = $user['id'];
    $old_code = $user['user_code'];

    // ✅ Reactivate leader record
    $mysqli->query("UPDATE leaders SET status = 'active', deactivated_at = NULL WHERE leader_id = $leader_id");

    // ✅ Reactivate their cell group
    $mysqli->query("UPDATE cell_groups SET status = 'active', archived_at = NULL WHERE leader_id = $leader_id");

    // ✅ Update user role to Leader (role_id = 2)
    $update_role = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update_role->bind_param("i", $user_id);
    $update_role->execute();
    $update_role->close();

    // ✅ Fix user_code prefix if needed (M → L)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    if ($new_code !== $old_code) {
        $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
        $update_code->bind_param("si", $new_code, $user_id);
        $update_code->execute();
        $update_code->close();
    }

    // ✅ Log reactivation
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Member', 'Leader (Reactivated)', ?, NOW())
    ");
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: cell_groups_admin.php?msg=✅ $leader_name successfully reactivated and restored as Leader.");
    exit();
}

header("Location: cell_groups_admin.php?msg=❌ Invalid request.");
exit();
?>
