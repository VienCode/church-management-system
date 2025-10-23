<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
include 'includes/log_helper.php'; // Centralized logging helper

$errors = [];

// === VALIDATION ===
if (
    empty($_POST["firstname"]) ||
    empty($_POST["lastname"]) ||
    empty($_POST["contact"]) ||
    empty($_POST["age"]) ||
    empty($_POST["user_address"]) ||
    empty($_POST["email"]) ||
    empty($_POST["pwd"]) ||
    empty($_POST["confirm_pwd"]) ||
    empty($_POST["is_existing_member"])
) {
    $errors[] = "Please fill in all required fields.";
}

if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
}

if (strlen($_POST["pwd"]) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
}

if (!preg_match("/[a-z]/i", $_POST["pwd"]) || !preg_match("/[0-9]/", $_POST["pwd"])) {
    $errors[] = "Password must contain at least one letter and one number.";
}

if (!preg_match("/^09\d{9}$/", $_POST["contact"])) {
    $errors[] = "Invalid contact number! Format: 09XXXXXXXXX.";
}

if ($_POST["pwd"] !== $_POST["confirm_pwd"]) {
    $errors[] = "Passwords do not match.";
}

if ($_POST["is_existing_member"] === "yes" && empty($_POST["leader_id"])) {
    $errors[] = "Please select your leader if you are an existing member.";
}

// Redirect if validation fails
if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

// === PASSWORD HASH ===
$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// === EMAIL DUPLICATE CHECK ===
$check_sql = "
    SELECT email FROM users WHERE email = ? 
    UNION 
    SELECT email FROM non_members WHERE email = ?
";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("ss", $_POST["email"], $_POST["email"]);
$check_stmt->execute();
$result = $check_stmt->get_result();
if ($result->num_rows > 0) {
    $_SESSION['register_errors'] = ["Email already exists! Please use a different one."];
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

// === ROLE LOGIC ===
$is_existing_member = $_POST["is_existing_member"] === "yes";

if ($is_existing_member) {
    // EXISTING MEMBER → users table
    $role_id = 3; // Member
    $leader_id = $_POST["leader_id"] ?? null;
    $is_cell_member = !empty($leader_id) ? 1 : 0;

    // Generate unique member code
    do {
        $user_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check_code->bind_param("s", $user_code);
        $check_code->execute();
        $exists = $check_code->get_result()->num_rows > 0;
        $check_code->close();
    } while ($exists);

    // Insert member record
    $sql = "INSERT INTO users 
            (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id, is_cell_member)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "sssisssssiii",
        $user_code,
        $_POST["firstname"],
        $_POST["lastname"],
        $_POST["suffix"],
        $_POST["contact"],
        $_POST["age"],
        $_POST["user_address"],
        $_POST["email"],
        $pwd_hash,
        $role_id,
        $leader_id,
        $is_cell_member
    );

    if ($stmt->execute()) {
        $new_member_id = $stmt->insert_id;
        $stmt->close();

        // ✅ Central log
        log_action(
            $mysqli,
            null,
            'System',
            'REGISTER',
            "New member registered: {$_POST['firstname']} {$_POST['lastname']} ({$_POST['email']})",
            'Normal'
        );

        // ✅ Find or reuse leader’s cell group
        if (!empty($leader_id)) {
            $check_group = $mysqli->prepare("
                SELECT id, status 
                FROM cell_groups 
                WHERE leader_id = ?
                ORDER BY 
                    CASE 
                        WHEN status = 'active' THEN 1
                        WHEN status = 'inactive' THEN 2
                        WHEN status = 'archived' THEN 3
                    END
                LIMIT 1
            ");
            $check_group->bind_param("i", $leader_id);
            $check_group->execute();
            $group = $check_group->get_result()->fetch_assoc();
            $check_group->close();

            if ($group) {
                $group_id = $group['id'];

                // Reactivate if archived
                if ($group['status'] === 'archived') {
                    $reactivate = $mysqli->prepare("
                        UPDATE cell_groups 
                        SET status='active', archived_at=NULL 
                        WHERE id=?
                    ");
                    $reactivate->bind_param("i", $group_id);
                    $reactivate->execute();
                    $reactivate->close();

                    log_action(
                        $mysqli,
                        null,
                        'System',
                        'RESTORE_GROUP',
                        "Automatically reactivated archived cell group ID #$group_id for Leader ID $leader_id",
                        'Normal'
                    );
                }
            } else {
                // Create new group if none exists at all
                $leader_name_query = $mysqli->prepare("SELECT leader_name FROM leaders WHERE leader_id = ?");
                $leader_name_query->bind_param("i", $leader_id);
                $leader_name_query->execute();
                $leader_name = $leader_name_query->get_result()->fetch_assoc()['leader_name'];
                $leader_name_query->close();

                $group_name = "{$leader_name}'s Cell Group";
                $create_group = $mysqli->prepare("
                    INSERT INTO cell_groups (group_name, leader_id, status, created_at) 
                    VALUES (?, ?, 'active', NOW())
                ");
                $create_group->bind_param("si", $group_name, $leader_id);
                $create_group->execute();
                $group_id = $create_group->insert_id;
                $create_group->close();

                log_action(
                    $mysqli,
                    null,
                    'System',
                    'CREATE_GROUP',
                    "New cell group '$group_name' (ID #$group_id) auto-created for Leader ID $leader_id during member signup",
                    'Normal'
                );
            }

            // ✅ Add member to leader’s group
            $check_member = $mysqli->prepare("
                SELECT id FROM cell_group_members 
                WHERE cell_group_id = ? AND member_id = ?
            ");
            $check_member->bind_param("ii", $group_id, $new_member_id);
            $check_member->execute();
            $exists = $check_member->get_result()->num_rows > 0;
            $check_member->close();

            if (!$exists) {
                $insert_member = $mysqli->prepare("
                    INSERT INTO cell_group_members (cell_group_id, member_id, is_active)
                    VALUES (?, ?, 1)
                ");
                $insert_member->bind_param("ii", $group_id, $new_member_id);
                $insert_member->execute();
                $insert_member->close();

                log_action(
                    $mysqli,
                    null,
                    'System',
                    'ADD_MEMBER',
                    "Added new member ID #$new_member_id to Cell Group #$group_id",
                    'Normal'
                );
            }
        }

        $_SESSION['register_success'] = "🎉 You have been successfully registered as a <b>Member</b>!";
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['register_errors'] = ["Database error: " . $stmt->error];
        header("Location: register.php");
        exit;
    }

} else {
    // === NON-MEMBER REGISTRATION ===
    $role_id = 4;

    do {
        $user_code = 'N' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check_code = $mysqli->prepare("SELECT 1 FROM non_members WHERE user_code = ?");
        $check_code->bind_param("s", $user_code);
        $check_code->execute();
        $exists = $check_code->get_result()->num_rows > 0;
        $check_code->close();
    } while ($exists);

    $sql = "INSERT INTO non_members 
            (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, attendances_count, created_at, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "sssisssssi",
        $user_code,
        $_POST["firstname"],
        $_POST["lastname"],
        $_POST["suffix"],
        $_POST["contact"],
        $_POST["age"],
        $_POST["user_address"],
        $_POST["email"],
        $pwd_hash,
        $role_id
    );

    if ($stmt->execute()) {
        log_action(
            $mysqli,
            null,
            'System',
            'REGISTER',
            "New non-member registered: {$_POST['firstname']} {$_POST['lastname']} ({$_POST['email']})",
            'Normal'
        );

        $_SESSION['registered_user'] = [
            'firstname' => $_POST["firstname"],
            'lastname' => $_POST["lastname"],
            'user_code' => $user_code,
            'role' => 'Non-Member'
        ];
        header("Location: register_success.php");
        exit;
    } else {
        $_SESSION['register_errors'] = ["Database error: " . $stmt->error];
        header("Location: register.php");
        exit;
    }
}
?>
