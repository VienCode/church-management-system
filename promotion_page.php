<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only admins can access

// Fetch eligible non-members (10 or more attendances)
$sql = "
    SELECT n.*, COUNT(a.attendance_id) AS total_attendance
    FROM non_members n
    LEFT JOIN attendance a ON n.id = a.user_id
    GROUP BY n.id
    HAVING total_attendance >= 10
";
$eligibleResult = $mysqli->query($sql);

// Fetch leaders (for assigning)
$leadersResult = $mysqli->query("
    SELECT id, firstname, lastname, user_code
    FROM users
    WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'leader' LIMIT 1)
");

$leaders = [];
while ($leader = $leadersResult->fetch_assoc()) {
    $leaders[] = $leader;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promotion Panel - Unity Christian Fellowship</title>
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
            <li><a href="promotion_page.php" class="active"><span>🕊️</span> Promotion Panel</a></li>
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>
    <!-- Content Area -->
    <div class="content-area">
        <div class="content-header">
            <div class="header-left">
                <h1 class="page-title">🕊️ Promotion Panel</h1>
                <p>Review eligible non-members (10+ attendances) and assign them a leader before promotion.</p>
            </div>
        </div>

        <div class="attendance-table" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Total Attendance</th>
                        <th>Assign Leader</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($eligibleResult->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No eligible non-members for promotion.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $eligibleResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['user_code']) ?></td>
                                <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                                <td><?= $row['total_attendance'] ?></td>
                                <td>
                                    <form method="POST" action="promote_nonmembers.php" style="display:flex; align-items:center; gap:8px;">
                                        <select name="leader_id" required style="padding:5px; border-radius:5px;">
                                            <option value="">Select Leader</option>
                                            <?php foreach ($leaders as $leader): ?>
                                                <option value="<?= $leader['id'] ?>">
                                                    <?= htmlspecialchars($leader['firstname'] . ' ' . $leader['lastname']) ?> 
                                                    (<?= htmlspecialchars($leader['user_code']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                        <input type="hidden" name="non_member_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="primary-btn" onclick="return confirm('Promote this user to member?')">Promote</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
