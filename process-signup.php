<?php
session_start();
$errors = [];

// === FIELD VALIDATION ===
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
    $errors[] = "Invalid Email Address.";
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

// === PASSWORD HASHING ===
$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// === DATABASE CONNECTION ===
$mysqli = require __DIR__ . "/database.php";

// === CHECK FOR DUPLICATE EMAIL ===
$check_sql = "SELECT email FROM users WHERE email = ?";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("s", $_POST["email"]);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['register_errors'] = ["Email already exists! Please use a different one."];
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
}

// === DETERMINE ROLE ===
$is_existing_member = $_POST["is_existing_member"] === "yes";
$role_id = $is_existing_member ? 3 : 4; // 3 = Member, 4 = Non-Member
$leader_id = $is_existing_member ? $_POST["leader_id"] : NULL;

// === INSERT INTO users TABLE ===
$sql = "INSERT INTO users 
        (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param(
    "sssissssii",
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
        $_SESSION['register_success'] = "Welcome back! You've been successfully registered as a Member and assigned to your chosen leader.";
    } else {
        $_SESSION['register_success'] = "Registration successful! You are now registered as a Non-Member. Once you reach 10 attendances, you’ll automatically become a Member.";
    }
    header("Location: login.php");
    exit;
} else {
    $_SESSION['register_errors'] = ["Database Error: " . $mysqli->error];
    header("Location: register.php");
    exit;
}
?>
