<?php
session_start();
$errors = [];

// === FIELD VALIDATION ===
$required_fields = ["firstname", "lastname", "contact", "age", "user_address", "email", "pwd", "confirm_pwd", "is_existing_member"];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "Please fill in all required fields.";
        break;
    }
}

if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
}

if (strlen($_POST["pwd"]) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
}

if (!preg_match("/[a-z]/i", $_POST["pwd"])) {
    $errors[] = "Password must contain at least one letter.";
}

if (!preg_match("/[0-9]/", $_POST["pwd"])) {
    $errors[] = "Password must contain at least one number.";
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

// Redirect back if there are validation errors
if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

// === DATABASE CONNECTION ===
$mysqli = require __DIR__ . "/database.php";

// === CHECK FOR DUPLICATE EMAIL ===
$check_stmt = $mysqli->prepare("SELECT email FROM users WHERE email = ?");
$check_stmt->bind_param("s", $_POST["email"]);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['register_errors'] = ["Email already exists! Please use a different one."];
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

// === DETERMINE ROLE ===
$is_existing_member = $_POST["is_existing_member"] === "yes";
$role_id = $is_existing_member ? 3 : 4; // 3 = Member, 4 = Non-Member
$leader_id = $is_existing_member ? $_POST["leader_id"] : NULL;

// === GENERATE UNIQUE USER CODE ===
$prefix = match($role_id) {
    1 => 'A', // Admin
    2 => 'L', // Leader
    3 => 'M', // Member
    4 => 'N', // Non-member
    5 => 'T', // Attendance Marker
    6 => 'E', // Editor
    7 => 'C', // Accountant
    8 => 'P', // Pastor
    default => 'U'
};

do {
    $user_code = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $check_code = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
    $check_code->bind_param("s", $user_code);
    $check_code->execute();
    $exists = $check_code->get_result()->num_rows > 0;
} while ($exists);

// === PASSWORD HASHING ===
$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// === INSERT INTO users TABLE ===
$sql = "INSERT INTO users 
        (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
$stmt = $mysqli->prepare($sql);
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

// === EXECUTE AND REDIRECT ===
if ($stmt->execute()) {
    if ($is_existing_member) {
        $_SESSION['register_success'] =
            "🎉 Welcome back! You've been registered as a Member under your leader.<br>
            Your unique ID is <b>{$user_code}</b>.";
    } else {
        $_SESSION['register_success'] =
            "✅ Registration successful! You are now a Non-Member.<br>
            Your unique ID is <b>{$user_code}</b>.<br>
            Once you reach 10 attendances, you’ll automatically become a Member.";
    }

    header("Location: login.php");
    exit;
} else {
    $_SESSION['register_errors'] = ["Database Error: " . htmlspecialchars($mysqli->error)];
    header("Location: register.php");
    exit;
}
?>
