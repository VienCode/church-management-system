<?php
session_start();
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // ✅ Include logging helper
restrict_to_roles([ROLE_ADMIN]); // Admins only

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_ids']) && is_array($_POST['promote_ids'])) {
    $promoted = 0;
    $skipped = 0;
    $logs = [];

    foreach ($_POST['promote_ids'] as $non_member_id) {
        $leader_id = $_POST['leader_id'][$non_member_id] ?? null;

        // Fetch non-member info
        $query = $mysqli->prepare("SELECT * FROM non_members WHERE id = ?");
        $query->bind_param("i", $non_member_id);
        $query->execute();
        $non_member = $query->get_result()->fetch_assoc();
        $query->close();

        if (!$non_member) continue;

        // ✅ Check if email already exists in users table
        $check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $non_member['email']);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $skipped++;
            $logs[] = "⚠️ Skipped {$non_member['firstname']} {$non_member['lastname']} — email already exists.";
            continue;
        }

        // ✅ Generate new member user_code (starts with M)
        do {
            $new_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
            $check_code->bind_param("s", $new_code);
            $check_code->execute();
            $exists_code = $check_code->get_result()->num_rows > 0;
            $check_code->close();
        } while ($exists_code);

        // ✅ Insert into users table
        $stmt = $mysqli->prepare("
            INSERT INTO users 
            (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, ?)
        ");
        $stmt->bind_param(
            "sssisssssi",
            $new_code,
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

        if ($stmt->execute()) {
            // ✅ Delete from non_members
            $delete = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
            $delete->bind_param("i", $non_member_id);
            $delete->execute();
            $delete->close();

            // ✅ Log to session feedback
            $logs[] = "✅ Promoted {$non_member['firstname']} {$non_member['lastname']} → Member (Code: $new_code)";
            $promoted++;

            // ✅ Centralized system log
            $fullname = "{$non_member['firstname']} {$non_member['lastname']}";
            log_role_change(
                $mysqli,
                $_SESSION['user_id'],     // Admin performing action
                $_SESSION['role'],        // Admin role (usually 'Admin')
                $fullname,                // Target name
                'Member',                 // New role
                'PROMOTE'                 // Action type
            );

        } else {
            $logs[] = "❌ Failed to promote {$non_member['firstname']} {$non_member['lastname']}.";
        }

        $stmt->close();
    }

    $_SESSION['promotion_result'] = "
        ✅ $promoted promoted<br>
        ⚠️ $skipped skipped<br><br>" . implode("<br>", $logs);

    header("Location: promotion_page.php");
    exit;
}

// Fallback if no users selected
$_SESSION['promotion_result'] = "⚠️ No users selected for promotion.";
header("Location: promotion_page.php");
exit;
?>
