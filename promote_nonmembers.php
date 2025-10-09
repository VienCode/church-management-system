<?php
session_start();
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

        // === Step 1: Prepare unique user_code ===
        if (!empty($nonMember['user_code'])) {
            // Convert prefix N → M
            $user_code = preg_replace('/^N/', 'M', $nonMember['user_code']);
        } else {
            // Generate new user_code if missing
            do {
                $user_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
                $check_code->bind_param("s", $user_code);
                $check_code->execute();
                $exists = $check_code->get_result()->num_rows > 0;
            } while ($exists);
        }

        // === Step 2: Insert into users table ===
        $stmt = $mysqli->prepare("
            INSERT INTO users 
            (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, 'Member')
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

            // === Step 3: Log role change ===
            $logStmt = $mysqli->prepare("
                INSERT INTO role_logs (user_id, old_role, new_role, changed_by)
                VALUES (?, 'Non-member', 'Member', NULL)
            ");
            $logStmt->bind_param("i", $newUserId);
            $logStmt->execute();

            // === Step 4: Delete from non_members table ===
            $deleteStmt = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
            $deleteStmt->bind_param("i", $nonMember['id']);
            $deleteStmt->execute();

            $promoted++;
            $logs[] = "{$nonMember['firstname']} {$nonMember['lastname']} promoted (New Code: $user_code)";
        }
    }
}

$mysqli->close();

// === Step 5: Redirect back with promotion log ===
$_SESSION['promotion_result'] = $promoted > 0
    ? "✅ Successfully promoted $promoted non-member(s):<br>" . implode('<br>', $logs)
    : "No non-members reached 10 attendances yet.";

header("Location: ../admin_dashboard.php");
exit;
?>
