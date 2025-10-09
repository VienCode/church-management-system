<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER, ROLE_PASTOR]); // Only these can record attendance

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_code'])) {
    $user_code = $_POST['user_code'];
    $recorded_by = $_SESSION['user_code'];
    $status = 'Present';

    // Check if already marked today
    $check = $mysqli->prepare("SELECT 1 FROM attendance WHERE user_code = ? AND attendance_date = CURDATE()");
    $check->bind_param("s", $user_code);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 0) {
        $stmt = $mysqli->prepare("
            INSERT INTO attendance (user_code, attendance_date, status, time_in, recorded_by)
            VALUES (?, CURDATE(), ?, CURTIME(), ?)
        ");
        $stmt->bind_param("sss", $user_code, $status, $recorded_by);
        $stmt->execute();

        $successMessage = "✅ Attendance marked for $user_code.";
    } else {
        $errorMessage = "⚠️ Attendance already recorded for $user_code today.";
    }
}

// Fetch all active users except Non-Members
$sql = "
    SELECT u.user_code, u.firstname, u.lastname, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.role_id != 4
    ORDER BY r.role_name, u.firstname
";
$users = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance | Unity Christian Fellowship</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        .attendance-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: 30px auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            text-align: center;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
        }
        .primary-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .primary-btn:hover {
            background: #0056b3;
        }
        .success-message, .error-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .success-message { background: #e6ffed; color: #256029; }
        .error-message { background: #ffe6e6; color: #8b0000; }
    </style>
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
            <li><a href="attendance.php" class="active"><span>👥</span> Attendance</a></li>
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
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>


    <!-- Content -->
    <div class="content-area">
        <div class="attendance-container">
            <h1>👥 Attendance Tracker</h1>
            <p>Mark attendance for all members, leaders, and other roles (excluding Non-Members).</p>

            <?php if (isset($successMessage)): ?>
                <div class="success-message"><?= $successMessage ?></div>
            <?php elseif (isset($errorMessage)): ?>
                <div class="error-message"><?= $errorMessage ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users->num_rows === 0): ?>
                    <tr><td colspan="4">No users available for attendance.</td></tr>
                <?php else: ?>
                    <?php while ($row = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_code']) ?></td>
                            <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                            <td><?= htmlspecialchars($row['role_name']) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_code" value="<?= htmlspecialchars($row['user_code']) ?>">
                                    <button type="submit" class="primary-btn">✅ Mark Present</button>
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
