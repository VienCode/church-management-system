<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$id = $_POST['user_id'];
$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];
$email = $_POST['email'];
$role_id = $_POST['role_id'];

$stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, email=?, role_id=? WHERE id=?");
$stmt->bind_param("sssii", $firstname, $lastname, $email, $role_id, $id);
if ($role_id < 1 || $role_id > 8) {
    die("Invalid role_id value ($role_id). Must be between 1 and 8.");
}

$stmt->execute();

header("Location: admin_dashboard.php");
exit();
