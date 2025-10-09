<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admin-only access

// Fetch eligible non-members (10+ attendances)
$eligible = $mysqli->query("
    SELECT id, user_code, firstname, lastname, email, contact, attendances_count
    FROM non_members
    WHERE attendances_count >= 10
");

// Fetch all leaders
$leaders = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promotion Panel | Unity Christian Fellowship</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        .promotion-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: 40px auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        .primary-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .primary-btn:hover {
            background-color: #0056b3;
        }

        .secondary-btn {
            background-color: #ccc;
            color: #333;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .secondary-btn:hover {
            background-color: #aaa;
        }

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        h1 {
            margin-bottom: 20px;
            color: #222;
        }
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

        <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="attendance_records.php"><span>📋</span> Attendance Records</a></li>
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
            <li><a href="promotion_page.php" class="active"><span>🕊️</span> Promotion Panel</a></li>
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>


    <!-- Content Area -->
    <div class="content-area">
        <div class="promotion-container">
            <h1>🕊️ Promotion Panel</h1>
            <p>These Non-Members have reached 10 or more attendances. Assign a leader and promote them to Members.</p>

            <?php if (isset($_SESSION['promotion_result'])): ?>
                <div class="success-message" style="margin:15px 0;">
                    <?= $_SESSION['promotion_result'] ?>
                </div>
                <?php unset($_SESSION['promotion_result']); ?>
            <?php endif; ?>

            <form action="promote_nonmembers.php" method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>User Code</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Attendances</th>
                            <th>Assign Leader</th>
                            <th>Promote</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($eligible->num_rows === 0): ?>
                            <tr><td colspan="7">No non-members eligible for promotion.</td></tr>
                        <?php else: ?>
                            <?php while ($nm = $eligible->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($nm['user_code']) ?></td>
                                    <td><?= htmlspecialchars($nm['firstname'] . ' ' . $nm['lastname']) ?></td>
                                    <td><?= htmlspecialchars($nm['email']) ?></td>
                                    <td><?= htmlspecialchars($nm['contact']) ?></td>
                                    <td><?= $nm['attendances_count'] ?></td>
                                    <td>
                                        <select name="leader_id[<?= $nm['id'] ?>]" required>
                                            <option value="" disabled selected>Select Leader</option>
                                            <?php
                                            $leaders->data_seek(0);
                                            while ($leader = $leaders->fetch_assoc()): ?>
                                                <option value="<?= $leader['leader_id'] ?>">
                                                    <?= htmlspecialchars($leader['leader_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="promote_ids[]" value="<?= $nm['id'] ?>">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px;">
                    <button type="submit" class="primary-btn">🚀 Promote Selected</button>
                    <a href="admin_dashboard.php" class="secondary-btn">⬅ Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
