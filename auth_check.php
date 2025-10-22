<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
define('ROLE_ADMIN', 1);
define('ROLE_LEADER', 2);
define('ROLE_MEMBER', 3);
define('ROLE_NON_MEMBER', 4);
define('ROLE_ATTENDANCE_MARKER', 5);
define('ROLE_EDITOR', 6);
define('ROLE_ACCOUNTANT', 7);
define('ROLE_PASTOR', 8);

function restrict_to_roles($allowed_roles = []) {
    if (!isset($_SESSION['role_id'])) {
        header("Location: login.php");
        exit();
    }

    // Allow admins full access by default
    if (!in_array($_SESSION['role_id'], $allowed_roles) && $_SESSION['role_id'] != ROLE_ADMIN) {
        header("Location: unauthorized.php");
        exit();
    }
}

function can_access($allowed_roles = []) {
    if (!isset($_SESSION['role_id'])) return false;
    return in_array($_SESSION['role_id'], $allowed_roles) || $_SESSION['role_id'] == ROLE_ADMIN;
}
function is_role($role_constant) {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $role_constant;
}

function get_role_name($role_constant) {
    return match($role_constant) {
        ROLE_ADMIN => 'Admin',
        ROLE_LEADER => 'Leader',
        ROLE_MEMBER => 'Member',
        ROLE_NON_MEMBER => 'Non-member',
        ROLE_ATTENDANCE_MARKER => 'Attendance Marker',
        ROLE_EDITOR => 'Editor',
        ROLE_ACCOUNTANT => 'Accountant',
        ROLE_PASTOR => 'Pastor',
        default => 'Unknown'
    };
}
