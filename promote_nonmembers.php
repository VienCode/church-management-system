<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

// Ensure form was submitted
if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_POST["promote_ids"])) {
    $_SESSION['promotion_result'] = "⚠️ No users selected for promotion.";
    header("Location: promotion_page.php");
    exit;
}

$promote_ids = $_POST["promote_ids"];
$leader_ids = $_POST["leader_id"];
$promoted_count = 0;
$log_details = [];

// Fetch role prefix for Member role
$prefix = 'M'; // For Member
foreach ($promote_ids as $non_member_id) {
    $non_member_id = intval($non_member_id);
    $leader_id = isset($leader_ids[$non_member_id]) ? intval($leader_ids[$non_member_id]) : null;

    // Fetch non-member data
    $stmt = $mysqli->prepare("SELECT * FROM non_members WHERE id = ?");
    $stmt->bind_param("i", $non_member_id);
    $stmt->execute();
    $non_member = $stmt->get_result()->fetch_assoc();

    if (!$non_member) continue;

    // Generate unique user_code
    do {
        $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check->bind_param("s", $user_code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
    } while ($exists);

    // Insert promoted user into users table
    $insert = $mysqli->prepare("
        INSERT INTO users (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, ?)
    ");
    $insert->bind_param(
        "sssisssssi",
        $user_code,
        $non_member['firstname'],
        $non_member['lastname'],
        $non_member['suffix'],
        $non_member['contact'],
        $non_member['age'],
        $non_member['user_address'],
        $non_member['email'],
        $non_member['pwd_hash'],
        $leader_id
    );

    if ($insert->execute()) {
        // Delete non-member record
        $del = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
        $del->bind_param("i", $non_member_id);
        $del->execute();

        // Log promotion
        $logStmt = $mysqli->prepare("
            INSERT INTO role_logs (user_id, old_role, new_role, changed_by)
            VALUES (?, 'Non-member', 'Member', ?)
        ");
        $newUserId = $insert->insert_id;
        $changed_by = $_SESSION['user_id'] ?? null;
        $logStmt->bind_param("ii", $newUserId, $changed_by);
        $logStmt->execute();

        $promoted_count++;
        $log_details[] = "{$non_member['firstname']} {$non_member['lastname']} → $user_code";
    }
}

$_SESSION['promotion_result'] = $promoted_count > 0
    ? "✅ Successfully promoted $promoted_count user(s):<br>" . implode('<br>', $log_details)
    : "⚠️ No users were promoted.";

header("Location: promotion_page.php");
exit;
?>
