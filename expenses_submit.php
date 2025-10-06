<?php
$mysqli = include 'database.php';
session_start();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $title = trim($_POST['title']);
    $reason = trim($_POST['reason']);
    $amount = floatval($_POST['amount']);
    $date_submitted = date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("INSERT INTO expenses (title, reason, amount, status, date_submitted) VALUES (?, ?, ?, 'Pending', ?)");
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
    <link rel="stylesheet" href="style_system.css">
</head>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="logo-section">
            <div class="logo-placeholder"><span>⛪</span></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <ul class="nav-menu">
            <!-- General Pages -->
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- Expenses Section -->
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php" class="active"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- System Section -->
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
        </ul>
    </nav>

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
