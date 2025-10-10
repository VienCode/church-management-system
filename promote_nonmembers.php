<?php
session_start();
include 'database.php';
include 'auth_check.php';
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

        if (!$non_member) continue;

        // ✅ Check if email already exists in users table
        $check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $non_member['email']);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $skipped++;
            $logs[] = "⚠️ Skipped {$non_member['firstname']} {$non_member['lastname']} — email already exists.";
            continue;
        }

        // Generate new user_code
        do {
            $new_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
            $check_code->bind_param("s", $new_code);
            $check_code->execute();
            $exists_code = $check_code->get_result()->num_rows > 0;
        } while ($exists_code);

        // Insert into users table
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
            // Delete from non_members
            $delete = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
            $delete->bind_param("i", $non_member_id);
            $delete->execute();

            $logs[] = "✅ Promoted {$non_member['firstname']} {$non_member['lastname']} → Member (Code: $new_code)";
            $promoted++;
        }
    }

    $_SESSION['promotion_result'] = "
        ✅ $promoted promoted<br>
        ⚠️ $skipped skipped<br><br>" . implode("<br>", $logs);

    header("Location: promotion_page.php");
    exit;
}

$_SESSION['promotion_result'] = "⚠️ No users selected for promotion.";
header("Location: promotion_page.php");
exit;
?>
