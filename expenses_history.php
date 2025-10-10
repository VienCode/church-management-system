<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// Fetch all approved or declined expenses (latest first)
$results = $mysqli->query("SELECT * FROM expenses ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Expenses History</title>
    <link rel="stylesheet" href="styles_system.css">
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Content -->
    <div class="content-area">
        <h1>Expenses History</h1>
        <div class="attendance-table">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th>Date Reviewed</th>
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
                        <td>
                            <?php if (strtolower($row['status']) === 'approved'): ?>
                                <span class="status-badge present">Approved</span>
                            <?php elseif (strtolower($row['status']) === 'declined'): ?>
                                <span class="status-badge absent">Declined</span>
                            <?php else: ?>
                                <span class="status-badge pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['updated_at'] ?? '—'); ?></td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr><td colspan="6">No expense history found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="expenses.js"></script>
</body>
</html>
