<?php
$mysqli = require __DIR__ . '/database.php'; 

// Fetch all non-members who reached 10 or more attendances
$query = "SELECT * FROM non_members WHERE attendances_count >= 10";
$result = $mysqli->query($query);

if (!$result) {
    die("Query failed: " . $mysqli->error);
}

$promoted = 0;
$logs = [];

if ($result->num_rows > 0) {
    while ($nonMember = $result->fetch_assoc()) {

        // === Generate unique user_code for new Member ===
        $prefix = 'M';
        do {
            $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
            $check_code->bind_param("s", $user_code);
            $check_code->execute();
            $exists = $check_code->get_result()->num_rows > 0;
        } while ($exists);

        // Insert into users table (Member role_id = 3)
        $stmt = $mysqli->prepare("
            INSERT INTO users (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3)
        ");
        $stmt->bind_param(
            "sssisssss",
            $user_code,
            $nonMember['firstname'],
            $nonMember['lastname'],
            $nonMember['suffix'],
            $nonMember['contact'],
            $nonMember['age'],
            $nonMember['user_address'],
            $nonMember['email'],
            $nonMember['pwd_hash']
        );

        if ($stmt->execute()) {
            $newUserId = $stmt->insert_id;

            // Log role change
            $logStmt = $mysqli->prepare("
                INSERT INTO role_logs (user_id, old_role, new_role, changed_by)
                VALUES (?, 'Non-member', 'Member', NULL)
            ");
            $logStmt->bind_param("i", $newUserId);
            $logStmt->execute();

            // Delete from non_members
            $deleteStmt = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
            $deleteStmt->bind_param("i", $nonMember['id']);
            $deleteStmt->execute();

            $promoted++;
            $logs[] = "{$nonMember['firstname']} {$nonMember['lastname']} promoted (User Code: $user_code)";
        }
    }
}

$mysqli->close();

// Redirect back with status
session_start();
$_SESSION['promotion_result'] = $promoted > 0
    ? "✅ Successfully promoted $promoted non-member(s):<br>" . implode('<br>', $logs)
    : "No non-members reached 10 attendances yet.";

header("Location: ../admin_dashboard.php");
exit;
?>
