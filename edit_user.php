<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $age = intval($_POST['age']);
    $address = trim($_POST['user_address']);
    $role_id = intval($_POST['role_id']);
    $leader_id = !empty($_POST['leader_id']) ? intval($_POST['leader_id']) : null;
    $is_cell_member = isset($_POST['is_cell_member']) ? 1 : 0;

    // 🧠 Fetch current user info
    $stmt = $mysqli->prepare("SELECT user_code, role_id, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current_user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit;
    }

    $old_role_id = $current_user['role_id'];
    $old_code = $current_user['user_code'];
    $old_email = $current_user['email'];

    // 🧩 Step 1: Block demotion if leader still has members
    if ($old_role_id == ROLE_LEADER && $role_id != ROLE_LEADER) {
        $check = $mysqli->prepare("
            SELECT COUNT(m.member_id) AS total_members
            FROM cell_group_members m
            JOIN cell_groups cg ON cg.id = m.cell_group_id
            JOIN leaders l ON l.leader_id = cg.leader_id
            WHERE l.email = ?
        ");
        $check->bind_param("s", $old_email);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['total_members'] ?? 0;
        $check->close();

        if ($count > 0) {
            header("Location: admin_dashboard.php?msg=⚠️ Cannot change this leader’s role — $count member(s) still assigned. Reassign them first.");
            exit;
        }

        // 🧩 Step 2: Auto-archive their cell group (if exists)
        $archive_stmt = $mysqli->prepare("
            UPDATE cell_groups cg
            JOIN leaders l ON l.leader_id = cg.leader_id
            SET cg.status = 'archived', cg.archived_at = NOW()
            WHERE l.email = ?
        ");
        $archive_stmt->bind_param("s", $old_email);
        $archive_stmt->execute();
        $archive_stmt->close();

        // 🧩 Step 3: Set leader record to inactive
        $mysqli->query("
            UPDATE leaders SET status='inactive', deactivated_at=NOW() WHERE email='$old_email'
        ");
    }

    // 🧩 Step 4: Role Prefix Mapping
    $prefix = match($role_id) {
        1 => 'A', // Admin
        2 => 'L', // Leader
        3 => 'M', // Member
        4 => 'N', // Non-Member
        5 => 'T', // Attendance Marker
        6 => 'E', // Editor
        7 => 'C', // Accountant
        8 => 'P', // Pastor
        default => 'U'
    };

    // 🪄 Step 5: Update user_code prefix
    $new_code = preg_replace('/^[A-Z]/', $prefix, $old_code);
    if ($new_code === $old_code) {
        $new_code = $prefix . substr($old_code, 1);
    }

    // 🧩 Step 6: Update user record
    $update = $mysqli->prepare("
        UPDATE users 
        SET firstname=?, lastname=?, email=?, contact=?, age=?, user_address=?, role_id=?, leader_id=?, is_cell_member=?, user_code=?
        WHERE id=?
    ");
    $update->bind_param(
        "ssssiisissi",
        $firstname,
        $lastname,
        $email,
        $contact,
        $age,
        $address,
        $role_id,
        $leader_id,
        $is_cell_member,
        $new_code,
        $user_id
    );
    $update->execute();
    $update->close();

    // 🧩 Step 7: Handle Leader Promotion (same as before)
    if ($role_id == ROLE_LEADER) {
        $fullname = "$firstname $lastname";

        $leader_stmt = $mysqli->prepare("SELECT leader_id, status FROM leaders WHERE email = ?");
        $leader_stmt->bind_param("s", $email);
        $leader_stmt->execute();
        $leader_data = $leader_stmt->get_result()->fetch_assoc();
        $leader_stmt->close();

        if (!$leader_data) {
            $insert = $mysqli->prepare("
                INSERT INTO leaders (leader_name, contact, email, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $insert->bind_param("sss", $fullname, $contact, $email);
            $insert->execute();
            $leader_id = $insert->insert_id;
            $insert->close();

            $group_name = "$fullname's Cell Group";
            $create_group = $mysqli->prepare("
                INSERT INTO cell_groups (group_name, leader_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");
            $create_group->bind_param("si", $group_name, $leader_id);
            $create_group->execute();
            $create_group->close();
        } else {
            if ($leader_data['status'] !== 'active') {
                $mysqli->query("UPDATE leaders SET status='active', deactivated_at=NULL WHERE email='$email'");
            }

            $check_group = $mysqli->prepare("SELECT id FROM cell_groups WHERE leader_id=? AND status='active' LIMIT 1");
            $check_group->bind_param("i", $leader_data['leader_id']);
            $check_group->execute();
            $has_group = $check_group->get_result()->fetch_assoc();
            $check_group->close();

            if (!$has_group) {
                $group_name = "$fullname's Cell Group";
                $create_group = $mysqli->prepare("
                    INSERT INTO cell_groups (group_name, leader_id, status, created_at)
                    VALUES (?, ?, 'active', NOW())
                ");
                $create_group->bind_param("si", $group_name, $leader_data['leader_id']);
                $create_group->execute();
                $create_group->close();
            }
        }
    }

    // 🧩 Step 8: Log Action
    log_action(
        $mysqli,
        $_SESSION['user_id'],
        'Admin',
        'EDIT_USER',
        "Edited user $firstname $lastname — Role updated, new code: $new_code",
        'Normal'
    );

    header("Location: admin_dashboard.php?msg=✅ User updated successfully. Code updated to $new_code");
    exit;
}
?>
