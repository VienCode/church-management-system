<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
$errors = [];

// ✅ Validation
if (
    empty($_POST["firstname"]) || empty($_POST["lastname"]) ||
    empty($_POST["contact"]) || empty($_POST["age"]) ||
    empty($_POST["user_address"]) || empty($_POST["email"]) ||
    empty($_POST["pwd"]) || empty($_POST["confirm_pwd"]) ||
    empty($_POST["is_existing_member"])
) {
    $errors[] = "Please fill in all required fields.";
}

if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";
if (strlen($_POST["pwd"]) < 8) $errors[] = "Password must be at least 8 characters long.";
if (!preg_match("/[a-z]/i", $_POST["pwd"]) || !preg_match("/[0-9]/", $_POST["pwd"])) $errors[] = "Password must contain at least one letter and one number.";
if ($_POST["pwd"] !== $_POST["confirm_pwd"]) $errors[] = "Passwords do not match.";
if ($_POST["is_existing_member"] === "yes" && empty($_POST["leader_id"])) $errors[] = "Please select your leader.";

if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// ✅ Email Duplication Check
$check_sql = "SELECT email FROM users WHERE email = ? UNION SELECT email FROM non_members WHERE email = ?";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("ss", $_POST["email"], $_POST["email"]);
$check_stmt->execute();
$result = $check_stmt->get_result();
if ($result->num_rows > 0) {
    $_SESSION['register_errors'] = ["Email already exists."];
    header("Location: register.php");
    exit;
}

$is_existing_member = $_POST["is_existing_member"] === "yes";

if ($is_existing_member) {
    // ✅ Members go to users table
    $role_id = 3; // Member
    $leader_id = intval($_POST["leader_id"]);

    // Generate member code
    do {
        $user_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = $mysqli->query("SELECT 1 FROM users WHERE user_code = '$user_code'")->num_rows > 0;
    } while ($exists);

    // Insert new member
    $stmt = $mysqli->prepare("
        INSERT INTO users (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id, is_cell_member)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 1)
    ");
    $stmt->bind_param(
        "sssisssssii",
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
        $leader_id
    );
    $stmt->execute();
    $member_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Ensure leader has an active cell group
    $group_result = $mysqli->query("SELECT id FROM cell_groups WHERE leader_id = $leader_id AND status='active' LIMIT 1");
    if ($group_result->num_rows === 0) {
        $leader_name = $mysqli->query("SELECT leader_name FROM leaders WHERE leader_id=$leader_id")->fetch_assoc()['leader_name'] ?? "Unnamed Leader";
        $group_name = $leader_name . "'s Cell Group";
        $mysqli->query("INSERT INTO cell_groups (group_name, leader_id, status, created_at) VALUES ('$group_name', $leader_id, 'active', NOW())");
        $group_id = $mysqli->insert_id;
    } else {
        $group_id = $group_result->fetch_assoc()['id'];
    }

    // ✅ Insert member into cell_group_members
    $exists = $mysqli->query("SELECT id FROM cell_group_members WHERE cell_group_id=$group_id AND user_code='$user_code'")->num_rows > 0;
    if (!$exists) {
        $mysqli->query("INSERT INTO cell_group_members (cell_group_id, user_code) VALUES ($group_id, '$user_code')");
    }

    $_SESSION['register_success'] = "🎉 Registration successful! You’ve joined your cell group.";
    header("Location: login.php");
    exit;

} else {
    // ✅ New attendees (Non-members)
    $role_id = 4;
    do {
        $user_code = 'N' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = $mysqli->query("SELECT 1 FROM non_members WHERE user_code = '$user_code'")->num_rows > 0;
    } while ($exists);

    $stmt = $mysqli->prepare("
        INSERT INTO non_members (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, attendances_count, created_at, role_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)
    ");
    $stmt->bind_param("sssisssssi", $user_code, $_POST["firstname"], $_POST["lastname"], $_POST["suffix"], $_POST["contact"], $_POST["age"], $_POST["user_address"], $_POST["email"], $pwd_hash, $role_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['register_success'] = "🎉 Registered successfully as a new attendee!";
    header("Location: login.php");
    exit;
}
?>
