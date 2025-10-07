<?php
session_start();
$errors = [];

// FIELD VALIDATION
if (
    empty($_POST["firstname"]) ||
    empty($_POST["lastname"]) ||
    empty($_POST["contact"]) ||
    empty($_POST["age"]) ||
    empty($_POST["user_address"]) ||
    empty($_POST["email"]) ||
    empty($_POST["pwd"]) ||
    empty($_POST["confirm_pwd"])
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

// If validation failed, redirect back to register.php
if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['old_data'] = $_POST; // keep form data filled in
    header("Location: register.php");
    exit;
}

// PASSWORD HASHING
$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// DATABASE CONNECTION
$mysqli = require __DIR__ . "/database.php";

// CHECK FOR DUPLICATE EMAIL
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

// INSERT INTO users TABLE (Non-Member role_id = 4)
$sql = "INSERT INTO users 
        (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 4)";

$stmt = $mysqli->stmt_init();
if (!$stmt->prepare($sql)) {
    die('SQL error: ' . $mysqli->error);
}

$stmt->bind_param("sssissss",
    $_POST["firstname"],
    $_POST["lastname"],
    $_POST["suffix"],
    $_POST["contact"],
    $_POST["age"],
    $_POST["user_address"],
    $_POST["email"],
    $pwd_hash
);

if ($stmt->execute()) {
    // Success → redirect to login
    $_SESSION['register_success'] = "Registration successful! You are now registered as a Non-Member. Once you reach 10 attendances, you’ll automatically become a Member.";
    header("Location: login.php");
    exit;
} else {
    die('Error: ' . $mysqli->error);
}
?>
