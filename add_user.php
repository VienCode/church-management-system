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

    // Validate required fields
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
        1 => 'A', // Admin
        2 => 'L', // Leader
        3 => 'M', // Member
        4 => 'N', // Non-Member
        5 => 'T', // Attendance Marker
        6 => 'E', // Editor
        7 => 'C', // Accountant
        8 => 'P', // Pastor
        default => 'U' // Unknown
    };

    do {
        $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check_code->bind_param("s", $user_code);
        $check_code->execute();
        $exists = $check_code->get_result()->num_rows > 0;
    } while ($exists);

    // Hash password
    $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into `users` table
    $sql = "INSERT INTO users 
            (user_code, firstname, lastname, email, pwd_hash, role_id, contact, age, user_address, leader_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $mysqli->prepare($sql);
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

    if ($stmt->execute()) {
        // ✅ If the user is a Leader, insert into leaders table
        if ($role_id == 2) {
            $leader_name = $firstname . ' ' . $lastname;
            $checkLeader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE leader_name = ?");
            $checkLeader->bind_param("s", $leader_name);
            $checkLeader->execute();
            $existsLeader = $checkLeader->get_result()->num_rows > 0;

            if (!$existsLeader) {
                $insertLeader = $mysqli->prepare("INSERT INTO leaders (leader_name, created_at) VALUES (?, NOW())");
                $insertLeader->bind_param("s", $leader_name);
                $insertLeader->execute();
            }
        }

        $_SESSION['msg'] = "✅ User added successfully with ID: $user_code";
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $_SESSION['msg'] = "❌ Database error: " . $mysqli->error;
        header("Location: add_user_form.php");
        exit;
    }

    if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;

    // ✅ Auto-link to cell group if user is assigned to a leader
    if (!empty($leader_id)) {
        // Find or create leader’s active cell group
        $group_stmt = $mysqli->prepare("
            SELECT id FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1
        ");
        $group_stmt->bind_param("i", $leader_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result()->fetch_assoc();
        $group_stmt->close();

        if (!$group_result) {
            // Create cell group if not found
            $leader_name_res = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ?");
            $leader_name_res->bind_param("i", $leader_id);
            $leader_name_res->execute();
            $leader_data = $leader_name_res->get_result()->fetch_assoc();
            $leader_name_res->close();

            $group_name = $leader_data ? $leader_data['leader_name'] . "'s Cell Group" : "Unnamed Group";

            $create_group = $mysqli->prepare("
                INSERT INTO cell_groups (group_name, leader_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");
            $create_group->bind_param("si", $group_name, $leader_id);
            $create_group->execute();
            $group_id = $create_group->insert_id;
            $create_group->close();
        } else {
            $group_id = $group_result['id'];
        }

        // Get new user's code
        $code_stmt = $mysqli->prepare("SELECT user_code FROM users WHERE id = ?");
        $code_stmt->bind_param("i", $new_user_id);
        $code_stmt->execute();
        $code_data = $code_stmt->get_result()->fetch_assoc();
        $code_stmt->close();

        if ($code_data) {
            $user_code = $code_data['user_code'];

            // Add member to cell_group_members table
            $add_member = $mysqli->prepare("
                INSERT IGNORE INTO cell_group_members (cell_group_id, user_code)
                VALUES (?, ?)
            ");
            $add_member->bind_param("is", $group_id, $user_code);
            $add_member->execute();
            $add_member->close();

            // Mark user as cell group member
            $mysqli->query("UPDATE users SET is_cell_member = 1 WHERE id = $new_user_id");
        }
    }

    $_SESSION['register_success'] = "✅ User successfully added and assigned to a cell group!";
    header("Location: admin_dashboard.php");
    exit;
}

}

header("Location: add_user_form.php");
exit;
?>
