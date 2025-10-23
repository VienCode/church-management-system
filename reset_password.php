<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
global $mysqli;

// Admin-only access
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    header("Location: admin_dashboard.php?msg=❌ Invalid request.");
    exit;
}

$user_id = intval($_POST['user_id']);

// Fetch user info for logging
$stmt = $mysqli->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: admin_dashboard.php?msg=❌ User not found.");
    exit;
}

// Reset to default password
$new_password = password_hash('ucf12345', PASSWORD_DEFAULT);
$update = $mysqli->prepare("UPDATE users SET pwd_hash=? WHERE id=?");
$update->bind_param("si", $new_password, $user_id);
$update->execute();
$update->close();

// Log the reset
log_action(
    $mysqli,
    $_SESSION['user_id'],
    'Admin',
    'RESET_PASSWORD',
    "Password reset for user {$user['firstname']} {$user['lastname']} ({$user['email']})",
    'High'
);

// Redirect
header("Location: admin_dashboard.php?msg=✅ Password reset to default (ucf12345) for {$user['firstname']} {$user['lastname']}");
exit;
?>
