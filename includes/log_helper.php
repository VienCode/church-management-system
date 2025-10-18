<?php
/**
 * ============================================================================
 *  Unity Christian Fellowship - Centralized Logging System Helper
 *  File: includes/log_helper.php
 *  Purpose: Provides universal system logging for all modules and user types
 * ============================================================================
 */

/**
 * Core Logging Function
 * ----------------------------------------------------------------------------
 * Records any significant user/system action into `system_logs`
 * for audit, transparency, and accountability.
 *
 * Table Requirement:
 *   - system_logs.user_id (nullable)
 *   - system_logs.account_type ENUM('User','Non-member','System')
 *
 * @param mysqli $mysqli          Active database connection
 * @param int|null $user_id       User or Non-member ID (nullable for system)
 * @param string|null $user_role  The role or type of actor (Admin, Non-member, etc.)
 * @param string $action_type     Short action keyword (ADD, EDIT, LOGIN, etc.)
 * @param string $description     Details of the action performed
 * @param string $importance      One of: 'Low', 'Normal', 'High', 'Critical'
 */
function log_action($mysqli, $user_id, $user_role, $action_type, $description, $importance = 'Normal')
{
    if (!$mysqli) {
        error_log("log_action(): No valid database connection provided.");
        return;
    }

    // --- Auto-detect account type based on role ---
    $account_type = 'User';
    if (strtolower($user_role) === 'non-member') {
        $account_type = 'Non-member';
    } elseif (strtolower($user_role) === 'system') {
        $account_type = 'System';
    }

    // --- Sanitize Inputs ---
    $user_id = $user_id ?? null;
    $user_role = htmlspecialchars($user_role ?? 'System', ENT_QUOTES, 'UTF-8');
    $action_type = strtoupper(trim($action_type));
    $description = htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8');
    $importance = ucfirst(strtolower($importance));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // --- Insert Log ---
    $stmt = $mysqli->prepare("
        INSERT INTO system_logs (user_id, account_type, user_role, action_type, action_description, ip_address, importance)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param("issssss", $user_id, $account_type, $user_role, $action_type, $description, $ip, $importance);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("log_action() failed to prepare statement: " . $mysqli->error);
    }
}

/**
 * Log Login Event
 * ----------------------------------------------------------------------------
 * Called after a successful login or logout attempt.
 *
 * @param mysqli $mysqli
 * @param int|null $user_id   User or Non-member ID
 * @param string $role        Role of the user (Admin, Non-member, etc.)
 * @param bool $is_login      true = login, false = logout
 */
function log_login_event($mysqli, $user_id, $role, $is_login = true)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $action = $is_login ? 'LOGIN' : 'LOGOUT';

    // Flexible description for both Users and Non-members
    if ($is_login) {
        $desc = ($role === 'Non-member')
            ? "Non-member (ID: $user_id) logged in from IP: $ip."
            : "User (ID: $user_id) logged in from IP: $ip.";
    } else {
        $desc = ($role === 'Non-member')
            ? "Non-member (ID: $user_id) logged out."
            : "User (ID: $user_id) logged out.";
    }

    log_action($mysqli, $user_id, $role, $action, $desc, 'Normal');
}

/**
 * Log Failed Login Attempt
 * ----------------------------------------------------------------------------
 * For authentication errors or brute-force detection.
 *
 * @param mysqli $mysqli
 * @param string $email
 */
function log_failed_login($mysqli, $email)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $desc = "Failed login attempt detected for email: $email from IP: $ip.";
    log_action($mysqli, null, 'System', 'FAILED_LOGIN', $desc, 'High');
}

/**
 * Log System Event
 * ----------------------------------------------------------------------------
 * For automation, backups, cron jobs, and other background tasks.
 *
 * @param mysqli $mysqli
 * @param string $description
 * @param string $importance
 */
function log_system_event($mysqli, $description, $importance = 'Normal')
{
    log_action($mysqli, null, 'System', 'SYSTEM', $description, $importance);
}

/**
 * Log Role Change
 * ----------------------------------------------------------------------------
 * Specifically for promotions, demotions, and leader assignments.
 *
 * @param mysqli $mysqli
 * @param int $admin_id          Acting admin ID
 * @param string $admin_role     Acting admin role (Admin, Pastor, etc.)
 * @param string $target_user    Full name of the user affected
 * @param string $new_role       New assigned role
 * @param string $action         Action label (PROMOTE, DEMOTE, etc.)
 */
function log_role_change($mysqli, $admin_id, $admin_role, $target_user, $new_role, $action = 'CHANGE')
{
    $desc = "$admin_role performed $action: $target_user → $new_role.";
    log_action($mysqli, $admin_id, $admin_role, 'ROLE_CHANGE', $desc, 'High');
}
?>
