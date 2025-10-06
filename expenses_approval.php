<?php
$mysqli = include 'database.php';
session_start();

// Fetch pending expenses
$result = $mysqli->query("SELECT * FROM expenses WHERE status = 'Pending' ORDER BY date_submitted DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Expense Approvals</title>
    <link rel="stylesheet" href="style.css">
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
        <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
        <li><a href="expenses_approval.php" class="active"><span>✅</span> Approvals</a></li>
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
        <h1>Approve or Decline Expenses</h1>
        <div class="attendance-table">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Reason</th>
                        <th>Amount</th>
                        <th>Date Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['reason']); ?></td>
                        <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo $row['date_submitted']; ?></td>
                        <td>
                            <form method="POST" action="process_expense.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="approve" class="primary-btn">Approve</button>
                                <button type="submit" name="decline" class="secondary-btn">Decline</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="expenses.js"></script>
</body>
</html>
