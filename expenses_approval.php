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

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
    <!-- Sidebar -->
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

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
