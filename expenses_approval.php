<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_PASTOR]);

// Fetch all expenses, newest first
$results = $mysqli->query("SELECT * FROM expenses ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Expense Approvals</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <!-- Sidebar -->
   <nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder"><span><img src="images/ucf.png" alt="ucf_logo"></span></div>
        <div class="logo">Unity Christian Fellowship</div>
    </div>
    <ul class="nav-menu">
        <!-- GENERAL PAGES -->
        <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>

        <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="attendance_records.php" class="active"><span>📋</span> Attendance Records</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ADMIN, ROLE_MEMBER, ROLE_LEADER])): ?>
            <li><a href="members.php"><span>👤</span> Members</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ADMIN, ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
        <!-- Divider -->
        <li class="nav-divider"></li>
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ADMIN])): ?>
        <li class="nav-divider"></li>
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
            <li><a href="admin_dashboard.php"><span>⚙️</span> Manage Users</a></li>
            <li><a href="promotion_page.php"><span>🕊️</span> Promotion Panel</a></li>
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
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
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($results && $results->num_rows > 0):
                    while ($row = $results->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td>
                            <form method="POST" action="process_expense.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="approve" class="primary-btn">Approve</button>
                                <button type="submit" name="decline" class="secondary-btn">Decline</button>
                            </form>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr><td colspan="5">No expenses found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="expenses.js"></script>
</body>
</html>
