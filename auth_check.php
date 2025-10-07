<?php
session_start();

// If not logged in at all
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit();
}

/**
 * Restricts access to only specific roles.
 * Admins (role_id = 1) always have full access.
 * @param array $allowed_roles An array of role_id values that can access the page
 */
function restrict_to_roles(array $allowed_roles) {
    $role_id = $_SESSION['role_id'];

    // Admins bypass restrictions
    if ($role_id == 1) {
        return;
    }

    // If the current role is not in the allowed list
    if (!in_array($role_id, $allowed_roles)) {
        header("Location: unauthorized.php");
        exit();
    }
}
?>
