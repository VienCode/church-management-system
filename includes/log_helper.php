<?php
/**
 * ============================================================================
 *  Unity Christian Fellowship - Centralized Logging System Helper
 *  File: functions/log_helper.php
 *  Purpose: Provides universal system logging for all modules
 * ============================================================================
 */

/**
 * Core Logging Function
 * ----------------------------------------------------------------------------
 * Records any major user action to `system_logs` for transparency and audit.
 *
 * @param mysqli $mysqli          Database connection
 * @param int|null $user_id       ID of acting user (nullable for system events)
 * @param string|null $user_role  Role of the user performing the action
 * @param string $action_type     Short identifier (ADD, EDIT, DELETE, LOGIN, etc.)
 * @param string $description     Detailed description of the action
 * @param string $importance      'Normal', 'High', or 'Critical'
 */
function log_action($mysqli, $user_id, $user_role, $action_type, $description, $importance = 'Normal')
{
    if (!$mysqli) {
        error_log("log_action(): No valid database connection provided.");
        return;
    }

    $user_id = $user_id ?? null;
    $user_role = htmlspecialchars($user_role ?? 'System', ENT_QUOTES, 'UTF-8');
    $action_type = strtoupper(trim($action_type));
    $description = htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8');
    $importance = ucfirst(strtolower($importance));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $stmt = $mysqli->prepare("
        INSERT INTO system_logs (user_id, user_role, action_type, action_description, ip_address, importance)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $user_role, $action_type, $description, $ip, $importance);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("log_action() failed to prepare statement: " . $mysqli->error);
    }
}

/**
 * Log Login Event
 * ----------------------------------------------------------------------------
 * Automatically called after a successful login or logout.
 *
 * @param mysqli $mysqli
 * @param int $user_id
 * @param string $role
 * @param bool $is_login   true = login, false = logout
 */
function log_login_event($mysqli, $user_id, $role, $is_login = true)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $action = $is_login ? 'LOGIN' : 'LOGOUT';
    $desc = $is_login
        ? "User (ID: $user_id) logged in from IP: $ip."
        : "User (ID: $user_id) logged out.";
    log_action($mysqli, $user_id, $role, $action, $desc, 'Normal');
}

/**
 * Log Failed Login Attempt
 * ----------------------------------------------------------------------------
 * Useful for security auditing. Does not require a session.
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
 * For automated tasks like backups, cron jobs, migrations, etc.
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
 * Specifically logs promotions, demotions, and reactivations.
 *
 * @param mysqli $mysqli
 * @param int $admin_id
 * @param string $admin_role
 * @param string $target_user
 * @param string $new_role
 * @param string $action
 */
function log_role_change($mysqli, $admin_id, $admin_role, $target_user, $new_role, $action = 'CHANGE')
{
    $desc = "$admin_role changed the role of $target_user to $new_role.";
    log_action($mysqli, $admin_id, $admin_role, strtoupper($action), $desc, 'High');
}
?>
