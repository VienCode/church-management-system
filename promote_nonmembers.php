<?php
$mysqli = require __DIR__ . '/../database.php'; 

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

        // Insert into users table
        $stmt = $mysqli->prepare("
            INSERT INTO users (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, 'Member')
        ");
        $stmt->bind_param(
            "ssssisss",
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
            $logs[] = "{$nonMember['firstname']} {$nonMember['lastname']} promoted.";
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
