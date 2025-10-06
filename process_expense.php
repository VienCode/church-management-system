<?php
$mysqli = include 'database.php';
session_start();

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'pastor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expense_id'], $_POST['action'])) {
    $expenseId = intval($_POST['expense_id']);
    $action = $_POST['action'];

    if (!in_array($action, ['Approved', 'Declined'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }

    $stmt = $mysqli->prepare("UPDATE expenses SET status = ?, reviewed_by = ?, date_reviewed = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $action, $_SESSION['user_id'], $expenseId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
