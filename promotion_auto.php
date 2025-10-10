<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$current_admin = $_SESSION['user_code'] ?? 'SYSTEM';

// Fetch all eligible non-members (10+ attendances)
$sql = "SELECT * FROM non_members WHERE attendances_count >= 10";
$result = $mysqli->query($sql);

if ($result->num_rows === 0) {
    $_SESSION['promotion_result'] = "✅ No eligible non-members found for promotion.";
    header("Location: promotion_page.php");
    exit();
}

$promotedCount = 0;

while ($nm = $result->fetch_assoc()) {
    // Promote to users table
    $stmt = $mysqli->prepare("
        INSERT INTO users 
        (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3)
    ");
    $stmt->bind_param(
        "ssssissss",
        $nm['user_code'],
        $nm['firstname'],
        $nm['lastname'],
        $nm['suffix'],
        $nm['contact'],
        $nm['age'],
        $nm['user_address'],
        $nm['email'],
        $nm['pwd_hash']
    );

    if ($stmt->execute()) {
        $promotedCount++;

        // Log the promotion
        $log = $mysqli->prepare("
            INSERT INTO promotion_logs (user_code, full_name, promoted_by)
            VALUES (?, ?, ?)
        ");
        $full_name = $nm['firstname'] . ' ' . $nm['lastname'];
        $log->bind_param("sss", $nm['user_code'], $full_name, $current_admin);
        $log->execute();

        // Remove from non_members table
        $delete = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
        $delete->bind_param("i", $nm['id']);
        $delete->execute();
    }
}

$_SESSION['promotion_result'] = "🎉 Successfully promoted {$promotedCount} non-member(s) to Members!";
header("Location: promotion_page.php");
exit();
?>
