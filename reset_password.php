<?php
include 'database.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

$user_id = $_POST['user_id'];
$new_password = password_hash('ucf12345', PASSWORD_DEFAULT); // default reset password

$stmt = $mysqli->prepare("UPDATE users SET password_hash=? WHERE id=?");
$stmt->bind_param("si", $new_password, $user_id);
$stmt->execute();

header("Location: admin_dashboard.php");
exit();
