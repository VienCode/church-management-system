<?php
session_start();

// 🚨 Redirect users to their proper login page if they are not logged in
if (!isset($_SESSION["role"])) {
    header("Location: login.php"); 
    exit();
}

/**
 * Restrict page access to specific roles
 *
 * Usage example at top of a PHP page:
 *     restrictAccess(['Admin', 'Leader']);
 *
 * @param array $allowed_roles List of roles allowed on the page
 */
function restrictAccess($allowed_roles = []) {
    if (!in_array($_SESSION["role"], $allowed_roles)) {
        header("Location: unauthorized.php");
        exit();
    }
}

/**
 * Optional: Simple helper to check current role
 * Example usage:
 *     if (isRole('Admin')) { ... }
 */
function isRole($role) {
    return isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}
?>
