<?php
session_start();
require 'database.php';
require 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // Fetch user details
    $stmt = $mysqli->prepare("SELECT firstname, lastname, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=User not found.");
        exit;
    }

    // If user is already a leader
    if ($user['role_id'] == 2) {
        header("Location: admin_dashboard.php?msg=This user is already a Leader.");
        exit;
    }

    // Promote to leader (role_id = 2)
    $update = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();

    // Check if already exists in leaders table
    $checkLeader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE leader_name = ?");
    $leader_name = $user['firstname'] . ' ' . $user['lastname'];
    $checkLeader->bind_param("s", $leader_name);
    $checkLeader->execute();
    $exists = $checkLeader->get_result()->num_rows > 0;

    if (!$exists) {
        // Insert new leader into leaders table
        $insert = $mysqli->prepare("INSERT INTO leaders (leader_name, created_at) VALUES (?, NOW())");
        $insert->bind_param("s", $leader_name);
        $insert->execute();
    }

    header("Location: admin_dashboard.php?msg=✅ " . urlencode($user['firstname'] . " promoted to Leader successfully!"));
    exit;
}

header("Location: admin_dashboard.php?msg=Invalid request.");
exit;
?>
