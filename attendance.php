<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

// Define current user for "recorded_by"
$current_user = $_SESSION['user_code'] ?? 'SYSTEM';

// Handle date selection
$attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');

// Save attendance when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!empty($_POST['attendance']) && is_array($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $user_code => $data) {
            $status = $data['status'] ?? null;
            $time_in = ($status === 'Present') ? ($data['time_in'] ?? date('H:i:s')) : null;

            if ($status) {
                $stmt = $mysqli->prepare("
                    INSERT INTO attendance (user_code, attendance_date, status, time_in, recorded_by)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status), time_in=VALUES(time_in)
                ");
                $stmt->bind_param("sssss", $user_code, $attendance_date, $status, $time_in, $current_user);
                $stmt->execute();
            }
        }
        $success_message = "✅ Attendance saved for " . date("F j, Y", strtotime($attendance_date));
    }
}

// Fetch members (excluding Non-Members)
$stmt = $mysqli->prepare("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, r.role_name,
           a.status, a.time_in
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN attendance a
           ON u.user_code = a.user_code AND a.attendance_date = ?
    WHERE u.role_id != 4
    ORDER BY u.lastname ASC
");
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$members = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance | UCF</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        body { font-family: 'Lexend Deca', sans-serif; }
        .attendance-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin: 30px auto;
            max-width: 1000px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: center; }
        th { background: #0271c0; color: white; }
        .present-btn, .absent-btn {
            border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer;
            font-weight: 600; transition: 0.2s;
        }
        .present-btn { background: #28a745; color: white; }
        .absent-btn { background: #dc3545; color: white; }
        .present-btn:hover { background: #1f8a3d; }
        .absent-btn:hover { background: #b52b38; }
        .time-input {
            border: 1px solid #ccc; border-radius: 6px;
            padding: 5px 8px; width: 100px; text-align: center;
        }
        .save-btn {
            background: #0271c0; color: white; border: none;
            padding: 10px 18px; border-radius: 8px; margin-top: 15px; cursor: pointer;
            font-weight: 600;
        }
        .save-btn:hover { background: #02589b; }
        .success-message {
            background: #d4edda; color: #155724; padding: 10px; border-radius: 8px;
            margin-bottom: 10px; font-weight: 600;
        }
        input[type="date"] {
            padding: 8px; border-radius: 6px; border: 1px solid #ccc;
        }
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
        <li><a href="dashboard.php" class="active"><span>🏠</span> Dashboard</a></li>

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
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>


    <div class="content-area">
        <div class="attendance-container">
            <h1>👥 Attendance Module</h1>
            <p>Select a date and mark members as present or absent.</p>

            <?php if (isset($success_message)): ?>
                <div class="success-message"><?= $success_message ?></div>
            <?php endif; ?>

            <form method="POST">
                <label><strong>Attendance Date:</strong></label>
                <input type="date" name="attendance_date" value="<?= $attendance_date ?>" max="<?= date('Y-m-d') ?>" required>
                <button type="submit" class="save-btn" name="view_attendance">View</button>
            </form>

            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="attendance_date" value="<?= $attendance_date ?>">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User Code</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Time In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($m = $members->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($m['user_code']) ?></td>
                                <td><?= htmlspecialchars($m['fullname']) ?></td>
                                <td><?= htmlspecialchars($m['role_name']) ?></td>
                                <td>
                                    <button type="button" class="present-btn" onclick="markPresent('<?= $m['user_code'] ?>')">Present</button>
                                    <button type="button" class="absent-btn" onclick="markAbsent('<?= $m['user_code'] ?>')">Absent</button>
                                    <input type="hidden" name="attendance[<?= $m['user_code'] ?>][status]" id="status_<?= $m['user_code'] ?>" value="<?= $m['status'] ?? '' ?>">
                                </td>
                                <td>
                                    <input type="time" name="attendance[<?= $m['user_code'] ?>][time_in]" id="time_<?= $m['user_code'] ?>" class="time-input" value="<?= $m['time_in'] ?? '' ?>" <?= ($m['status'] === 'Present') ? '' : 'disabled' ?>>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>
            </form>
        </div>
    </div>
</div>

<script>
function markPresent(code) {
    document.getElementById('status_' + code).value = 'Present';
    const timeField = document.getElementById('time_' + code);
    timeField.disabled = false;
    const now = new Date();
    timeField.value = now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
}

function markAbsent(code) {
    document.getElementById('status_' + code).value = 'Absent';
    const timeField = document.getElementById('time_' + code);
    timeField.value = '';
    timeField.disabled = true;
}
</script>
</body>
</html>
