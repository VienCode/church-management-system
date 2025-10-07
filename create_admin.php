<?php
// Run this file once to create an admin, then delete it for security.
//THIS FILE IS FOR INSERTING ADMIN ACCOUNTS TO THE DATABASE

require __DIR__ . '/database.php';

// --- Admin Account Details ---
$firstname     = 'UCF';
$lastname      = 'Administrator';
$suffix        = '';
$contact       = '09217295880';
$age           = 21;
$user_address  = 'Admin Address';
$email         = 'admin@ucf.com';
$password      = 'Admin@1234';  // You can change this
$role_id       = 1;             // 1 = Admin

// --- Hash the password ---
$pwd_hash = password_hash($password, PASSWORD_DEFAULT);

// --- Insert into users table ---
$stmt = $mysqli->prepare("
    INSERT INTO users 
        (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, cell_group_id, cell_leader, created_at, role_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NOW(), ?)
");

$stmt->bind_param(
    "sssissssi",
    $firstname,
    $lastname,
    $suffix,
    $contact,
    $age,
    $user_address,
    $email,
    $pwd_hash,
    $role_id
);

if ($stmt->execute()) {
    echo '<h2 style="color:green;font-family:monospace;"> Admin account created successfully!</h2>';
    echo '<p>Email: <strong>' . htmlspecialchars($email) . '</strong></p>';
    echo '<p>Password: <strong>' . htmlspecialchars($password) . '</strong></p>';
} else {
    echo '<h2 style="color:red;">❌ Error:</h2> ' . htmlspecialchars($stmt->error);
}

$stmt->close();
$mysqli->close();
?>
