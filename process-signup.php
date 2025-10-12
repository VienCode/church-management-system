<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
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
    // ✅ Existing members → users table
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

    // ✅ Insert member into users
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
        // ✅ Auto-add to cell group if a leader was selected
        if (!empty($leader_id)) {
            $group_result = $mysqli->query("
                SELECT id FROM cell_groups 
                WHERE leader_id = $leader_id AND status = 'active' 
                LIMIT 1
            ");
            if ($group_result && $group_result->num_rows > 0) {
                $group = $group_result->fetch_assoc();
                $group_id = $group['id'];

                // Prevent duplicates
                $check = $mysqli->prepare("SELECT id FROM cell_group_members WHERE cell_group_id = ? AND user_code = ?");
                $check->bind_param("is", $group_id, $user_code);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$exists) {
                    $insert = $mysqli->prepare("
                        INSERT INTO cell_group_members (cell_group_id, user_code)
                        VALUES (?, ?)
                    ");
                    $insert->bind_param("is", $group_id, $user_code);
                    $insert->execute();
                    $insert->close();
                }
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
    // ✅ New attendees → non_members table
    $role_id = 4; // Non-member

    // Generate unique guest code
    do {
        $user_code = 'N' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check_code = $mysqli->prepare("SELECT 1 FROM non_members WHERE user_code = ?");
        $check_code->bind_param("s", $user_code);
        $check_code->execute();
        $exists = $check_code->get_result()->num_rows > 0;
        $check_code->close();
    } while ($exists);

    $sql = "INSERT INTO non_members 
            (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, attendances_count, last_attended, created_at, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NOW(), ?)";

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
