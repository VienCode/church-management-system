<?php
// Run this file once to create an admin, then delete it for security.
//THIS FILE IS FOR INSERTING ACCOUNTS WITH SPECIFIC ROLES FOR TESTING PURPOSES

require __DIR__ . '/database.php';

// --- Admin Account Details ---
$firstname     = 'UCF';
$lastname      = 'Editor';
$suffix        = '';
$contact       = '0931000000';
$age           = 21;
$user_address  = 'Editor Address';
$email         = 'Editor@ucf.com';
$password      = 'Editor@1234';  // You can change this
$role_id       = 6;             //

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
