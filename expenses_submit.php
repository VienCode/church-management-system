<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $title = trim($_POST['title']);
    $reason = trim($_POST['reason']);
    $amount = floatval($_POST['amount']);
    $date_submitted = date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("INSERT INTO expenses (title, description, amount, submitted_by, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssds", $title, $reason, $amount, $date_submitted);

    if ($stmt->execute()) {
        $success = "✅ Expense submitted successfully and is awaiting approval.";
    } else {
        $error = "⚠️ Failed to submit expense.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Expense</title>
    <link rel="stylesheet" href="styles_system.css">
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
    <!-- Sidebar -->
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Content -->
    <div class="content-area">
        <h1>Expense Submission</h1>
        <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>
        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

       <form method="POST" class="form-layout">
        <label class="form-label">Title:</label>
        <input type="text" name="title" class="form-input" required>

        <label class="form-label">Reason:</label>
        <textarea name="reason" class="form-textarea" required></textarea>

        <label class="form-label">Amount:</label>
        <input type="number" step="0.01" name="amount" class="form-input" required>

        <button type="submit" name="submit_expense" class="primary-btn">Submit Expense</button>
    </form>

    </div>
</div>
<script src="expenses.js"></script>
</body>
</html>
