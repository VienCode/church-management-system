<?php
session_start();
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize inputs
    $firstname = trim($_POST["firstname"]);
    $lastname  = trim($_POST["lastname"]);
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $contact   = trim($_POST["contact"] ?? '');
    $age       = intval($_POST["age"] ?? 0);
    $address   = trim($_POST["user_address"] ?? '');
    $role_id   = intval($_POST["role_id"]);
    $leader_id = !empty($_POST["leader_id"]) ? intval($_POST["leader_id"]) : null;

    // Validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($role_id)) {
        $_SESSION['msg'] = "❌ Please fill in all required fields.";
        header("Location: add_user_form.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['msg'] = "❌ Invalid email format.";
        header("Location: add_user_form.php");
        exit;
    }

    // Check duplicate email
    $check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $_SESSION['msg'] = "❌ Email already exists.";
        header("Location: add_user_form.php");
        exit;
    }

    // === Generate Unique User Code Based on Role ===
    $prefix = match($role_id) {
        1 => 'A', 2 => 'L', 3 => 'M', 4 => 'N', 5 => 'T', 6 => 'E', 7 => 'C', 8 => 'P', default => 'U'
    };

    do {
        $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check_code->bind_param("s", $user_code);
        $check_code->execute();
        $exists = $check_code->get_result()->num_rows > 0;
        $check_code->close();
    } while ($exists);

    // Hash password
    $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

    // === Insert user into users table ===
    $stmt = $mysqli->prepare("
        INSERT INTO users 
        (user_code, firstname, lastname, email, pwd_hash, role_id, contact, age, user_address, leader_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "sssssiisis",
        $user_code,
        $firstname,
        $lastname,
        $email,
        $pwd_hash,
        $role_id,
        $contact,
        $age,
        $address,
        $leader_id
    );

    if (!$stmt->execute()) {
        $_SESSION['msg'] = "❌ Database error: " . $stmt->error;
        header("Location: add_user_form.php");
        exit;
    }

    $new_user_id = $stmt->insert_id;
    $stmt->close();

    // ✅ If the user is a Leader, ensure a record exists in leaders table
    if ($role_id == 2) {
        $leader_name = "$firstname $lastname";

        $checkLeader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? LIMIT 1");
        $checkLeader->bind_param("s", $email);
        $checkLeader->execute();
        $leader_exists = $checkLeader->get_result()->fetch_assoc();
        $checkLeader->close();

        if (!$leader_exists) {
            $insertLeader = $mysqli->prepare("
                INSERT INTO leaders (leader_name, contact, email, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $insertLeader->bind_param("sss", $leader_name, $contact, $email);
            $insertLeader->execute();
            $insertLeader->close();
        }
    }

    // ✅ If the user is a Member and has a selected Leader, add to the leader’s cell group
    if ($role_id == 3 && !empty($leader_id)) {
        // Find leader’s active group
        $group_stmt = $mysqli->prepare("
            SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1
        ");
        $group_stmt->bind_param("i", $leader_id);
        $group_stmt->execute();
        $group = $group_stmt->get_result()->fetch_assoc();
        $group_stmt->close();

        if (!$group) {
            // Create a new group if leader doesn’t have one
            $leader_name_stmt = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ?");
            $leader_name_stmt->bind_param("i", $leader_id);
            $leader_name_stmt->execute();
            $leader_name = $leader_name_stmt->get_result()->fetch_assoc()['leader_name'] ?? 'Unnamed Leader';
            $leader_name_stmt->close();

            $group_name = "{$leader_name}'s Cell Group";
            $create_group = $mysqli->prepare("
                INSERT INTO cell_groups (group_name, leader_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");
            $create_group->bind_param("si", $group_name, $leader_id);
            $create_group->execute();
            $group_id = $create_group->insert_id;
            $create_group->close();
        } else {
            $group_id = $group['id'];
        }

        // Add member to group (by member_id, not user_code)
        $check_member = $mysqli->prepare("
            SELECT id FROM cell_group_members WHERE cell_group_id = ? AND member_id = ?
        ");
        $check_member->bind_param("ii", $group_id, $new_user_id);
        $check_member->execute();
        $exists = $check_member->get_result()->num_rows > 0;
        $check_member->close();

        if (!$exists) {
            $add_member = $mysqli->prepare("
                INSERT INTO cell_group_members (cell_group_id, member_id, is_active, joined_at)
                VALUES (?, ?, 1, NOW())
            ");
            $add_member->bind_param("ii", $group_id, $new_user_id);
            $add_member->execute();
            $add_member->close();
        }

        // Mark user as cell member
        $mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $new_user_id");
    }

    $_SESSION['msg'] = "✅ User successfully added and assigned to their leader’s cell group!";
    header("Location: admin_dashboard.php");
    exit;
}

header("Location: add_user_form.php");
exit;
?>
