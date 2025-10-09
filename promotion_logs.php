<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$result = $mysqli->query("
    SELECT p.*, l.leader_name, a.firstname AS admin_first, a.lastname AS admin_last
    FROM promotion_logs p
    LEFT JOIN leaders l ON p.assigned_leader_id = l.leader_id
    LEFT JOIN users a ON p.promoted_by_admin_id = a.id
    ORDER BY p.promoted_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Promotion Logs</title>
<link rel="stylesheet" href="styles_system.css">
<style>
    .logs-table { background:white; padding:25px; border-radius:10px; margin-top:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { border-bottom:1px solid #ddd; padding:10px; text-align:center; }
    th { background:#007bff; color:white; }
</style>
</head>
<body>
<div class="main-layout">
    <nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder"><span><img src="images/ucf.png" alt="ucf_logo"></span></div>
        <div class="logo">Unity Christian Fellowship</div>
    </div>
    <ul class="nav-menu">
        <!-- GENERAL PAGES -->
        <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>

        <?php if (can_access([ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_MEMBER, ROLE_LEADER])): ?>
            <li><a href="members.php"><span>👤</span> Members</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
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
            <li><a href="promotion_logs.php" class="active"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>

    <div class="content-area">
        <h1>📜 Promotion Logs</h1>
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Leader</th>
                        <th>Promoted By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_code']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_name']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_user_email']) ?></td>
                            <td><?= htmlspecialchars($row['leader_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['admin_first'] . ' ' . $row['admin_last']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
