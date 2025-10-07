<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Role constants for readability
define('ROLE_ADMIN', 1);
define('ROLE_LEADER', 2);
define('ROLE_MEMBER', 3);
define('ROLE_NON_MEMBER', 4);
define('ROLE_ATTENDANCE_MARKER', 5);
define('ROLE_EDITOR', 6);
define('ROLE_ACCOUNTANT', 7);
define('ROLE_PASTOR', 8);

/**
 * Restrict specific pages to roles only
 */
function restrict_to_roles($allowed_roles = []) {
    if (!in_array($_SESSION['role_id'], $allowed_roles) && $_SESSION['role_id'] != ROLE_ADMIN) {
        header("Location: unauthorized.php");
        exit();
    }
}

/**
 * Helper for showing/hiding menu items
 */
function can_access($allowed_roles = []) {
    return in_array($_SESSION['role_id'], $allowed_roles) || $_SESSION['role_id'] == ROLE_ADMIN;
}
