<?php
session_start();
require 'database.php';
require 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // 🔐 Prevent deleting yourself (optional)
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        header("Location: admin_dashboard.php?msg=⚠️ You cannot delete your own account.");
        exit;
    }

    // 🧠 Fetch user info before deleting (for logs/confirmation)
    $stmt = $mysqli->prepare("SELECT firstname, lastname, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit;
    }

    // 🚫 Optional safeguard: prevent deletion of Admin accounts
    if ($user['role_id'] == 1) {
        header("Location: admin_dashboard.php?msg=🚫 You cannot delete another Admin account.");
        exit;
    }

    // 🗑️ Proceed to delete
    $delete_stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();

    // If user is a leader, also remove from `leaders` table
    if ($user['role_id'] == 2) {
        $leader_name = $user['firstname'] . ' ' . $user['lastname'];
        $delete_leader = $mysqli->prepare("DELETE FROM leaders WHERE leader_name = ?");
        $delete_leader->bind_param("s", $leader_name);
        $delete_leader->execute();
    }

    header("Location: admin_dashboard.php?msg=✅ " . urlencode($user['firstname'] . ' ' . $user['lastname'] . " deleted successfully."));
    exit;
}

header("Location: admin_dashboard.php?msg=❌ Invalid request method.");
exit;
?>
