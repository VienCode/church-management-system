<?php
include 'db.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

$id = $_POST['user_id'];
$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];
$email = $_POST['email'];
$role_id = $_POST['role_id'];

$stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, email=?, role_id=? WHERE id=?");
$stmt->bind_param("sssii", $firstname, $lastname, $email, $role_id, $id);
$stmt->execute();

header("Location: admin_dashboard.php");
exit();
