<?php
include 'db.php';

$id = $_GET['id'];
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result);
