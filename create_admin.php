<?php
include 'database.php';

// === CONFIGURATION ===
// You can edit these values safely
$firstname = "System";
$lastname = "Administrator";
$email = "admin@ucf.com";
$contact = "09123456789";
$age = 35;
$user_address = "Church HQ, Unity City";
$password_plain = "admin123"; // You can change this
$role_id = 1; // Admin Role ID
$role_prefix = "A"; // Prefix for admin user_code

// === CHECK IF ADMIN ALREADY EXISTS ===
$check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();
if ($result->num_rows > 0) {
    echo "⚠️ Admin already exists with email: $email";
    exit;
}
$check->close();

// === CREATE PASSWORD HASH ===
$pwd_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// === GENERATE UNIQUE USER CODE ===
$get_last = $mysqli->query("SELECT id FROM users ORDER BY id DESC LIMIT 1");
$last_id = $get_last->num_rows > 0 ? $get_last->fetch_assoc()['id'] + 1 : 1;
$user_code = $role_prefix . str_pad($last_id, 4, "0", STR_PAD_LEFT); // Example: A0001

// === INSERT ADMIN USER ===
$stmt = $mysqli->prepare("
    INSERT INTO users (user_code, firstname, lastname, contact, age, user_address, email, pwd_hash, role_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssssisssi", $user_code, $firstname, $lastname, $contact, $age, $user_address, $email, $pwd_hash, $role_id);

if ($stmt->execute()) {
    echo "✅ Admin account created successfully!<br>";
    echo "📧 Email: <b>$email</b><br>";
    echo "🔑 Password: <b>$password_plain</b><br>";
    echo "🆔 User Code: <b>$user_code</b><br>";
    echo "<br>👉 You can now log in with this account to access all admin pages.";
} else {
    echo "❌ Error creating admin: " . $stmt->error;
}

$stmt->close();
?>
