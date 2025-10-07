<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admins only
session_start();

$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role_id = $_POST['role_id'];

$stmt = $mysqli->prepare("INSERT INTO users (firstname, lastname, email, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $firstname, $lastname, $email, $password, $role_id);
$stmt->execute();

header("Location: admin_dashboard.php");
exit();
