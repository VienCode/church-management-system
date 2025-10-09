<?php
session_start();
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admin only

if (empty($_POST['promote_ids'])) {
    $_SESSION['promotion_result'] = "⚠️ No members selected for promotion.";
    header("Location: promotion_page.php");
    exit;
}

$promoted = 0;
$logs = [];

foreach ($_POST['promote_ids'] as $nonMemberId) {
    $leader_id = $_POST['leader_id'][$nonMemberId] ?? null;
    if (!$leader_id) continue;

    // Get Non-Member info
    $stmt = $mysqli->prepare("SELECT * FROM non_members WHERE id = ?");
    $stmt->bind_param("i", $nonMemberId);
    $stmt->execute();
    $nonMember = $stmt->get_result()->fetch_assoc();
    if (!$nonMember) continue;

    // === Generate unique user_code ===
    $prefix = 'M'; // for Members
    do {
        $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check->bind_param("s", $user_code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
    } while ($exists);

    // Insert into users table
    $insert = $mysqli->prepare("
        INSERT INTO users (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, ?)
    ");
    $insert->bind_param(
        "sssisssssii",
        $user_code,
        $nonMember['firstname'],
        $nonMember['lastname'],
        $nonMember['suffix'],
        $nonMember['contact'],
        $nonMember['age'],
        $nonMember['user_address'],
        $nonMember['email'],
        $nonMember['pwd_hash'],
        $leader_id
    );
    $insert->execute();

    // Delete from non_members
    $delete = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
    $delete->bind_param("i", $nonMemberId);
    $delete->execute();

    $promoted++;
    $logs[] = "{$nonMember['firstname']} {$nonMember['lastname']} promoted to Member (ID: $user_code)";
}

$_SESSION['promotion_result'] = $promoted > 0
    ? "✅ $promoted user(s) successfully promoted:<br>" . implode('<br>', $logs)
    : "⚠️ No users were promoted.";

header("Location: promotion_page.php");
exit;
?>
