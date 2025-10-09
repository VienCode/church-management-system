<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER]);

// Handle date selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_date'])) {
    if (!empty($_POST['attendance_date']) && $_POST['attendance_date'] <= date('Y-m-d')) {
        $_SESSION['attendance_date'] = $_POST['attendance_date'];
    }
    header("Location: attendance.php");
    exit();
}

$attendance_date = $_SESSION['attendance_date'] ?? date('Y-m-d');

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!empty($_POST['attendance']) && is_array($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $user_code => $data) {
            $status = $data['status'] ?? null;
            $time_in = ($status === "Present") ? ($data['time_in'] ?? date('H:i:s')) : null;

            if ($status) {
                $stmt = $mysqli->prepare("
                    INSERT INTO attendance (user_code, attendance_date, status, time_in)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in)
                ");
                $stmt->bind_param("ssss", $user_code, $attendance_date, $status, $time_in);
                $stmt->execute();
            }
        }
        $success = "✅ Attendance successfully recorded for $attendance_date!";
    }
}

// Fetch members (excluding non-members)
$sql = "
SELECT 
    users.user_code,
    CONCAT(users.firstname, ' ', users.lastname) AS name,
    roles.role_name,
    attendance.status,
    attendance.time_in
FROM users
LEFT JOIN attendance 
    ON attendance.user_code = users.user_code 
    AND attendance.attendance_date = ?
JOIN roles ON users.role_id = roles.role_id
WHERE users.role_id != 4
ORDER BY users.lastname ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$members_result = $stmt->get_result();

// Calculate stats
$presentCount = 0;
$absentCount = 0;
$totalMembers = $members_result->num_rows;

$members_data = [];
while ($row = $members_result->fetch_assoc()) {
    $members_data[] = $row;
    if ($row['status'] === "Present") $presentCount++;
    if ($row['status'] === "Absent") $absentCount++;
}
$notMarked = $totalMembers - ($presentCount + $absentCount);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Management | UCF</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        .attendance-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: 30px auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #007bff;
            color: white;
        }

        .present-label, .absent-label {
            margin: 0 10px;
            cursor: pointer;
        }

        .radio-group input[type="radio"] {
            margin-right: 6px;
        }

        .time-input {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .stats-container {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }

        .stat-card {
            background: #f4f7fa;
            border-radius: 10px;
            text-align: center;
            padding: 10px;
            width: 22%;
        }

        .present h3 { color: #28a745; }
        .absent h3 { color: #dc3545; }
        .total h3 { color: #007bff; }

        .save-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            margin-top: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .save-btn:hover {
            background: #0056b3;
        }

        .success-message {
            background: #e6ffed;
            color: #256029;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .date-selector {
            margin-bottom: 15px;
        }

        .date-selector input[type="date"] {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
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
                <h1>👥 Attendance Management</h1>
                <p>Mark attendance for all roles except Non-Members.</p>

                <?php if (isset($success)): ?>
                    <div class="success-message"><?= $success ?></div>
                <?php endif; ?>

                <!-- Date Selector -->
                <div class="date-selector">
                    <form method="POST" id="dateForm">
                        <label><strong>Attendance Date:</strong></label>
                        <input type="date" 
                               name="attendance_date" 
                               value="<?= $attendance_date ?>" 
                               max="<?= date('Y-m-d'); ?>" required>
                    </form>
                    <p style="color:#666;">Currently viewing: 
                        <strong><?= date("l, F j, Y", strtotime($attendance_date)) ?></strong>
                    </p>
                </div>

                <!-- Stats -->
                <div class="stats-container">
                    <div class="stat-card present"><h3>Present</h3><div class="number"><?= $presentCount ?></div></div>
                    <div class="stat-card absent"><h3>Absent</h3><div class="number"><?= $absentCount ?></div></div>
                    <div class="stat-card total"><h3>Total Members</h3><div class="number"><?= $totalMembers ?></div></div>
                    <div class="stat-card"><h3>Not Marked</h3><div class="number"><?= $notMarked ?></div></div>
                </div>

                <!-- Attendance Table -->
                <form method="POST" id="attendanceForm">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User Code</th>
                                <th>Member Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Time In</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($members_data) > 0): ?>
                                <?php foreach ($members_data as $index => $m): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($m['user_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($m['name']) ?></td>
                                        <td><?= htmlspecialchars($m['role_name']) ?></td>
                                        <td>
                                            <div class="radio-group">
                                                <label class="present-label">
                                                    <input type="radio" name="attendance[<?= $m['user_code'] ?>][status]" 
                                                           value="Present" 
                                                           <?= ($m['status'] === "Present") ? "checked" : "" ?>
                                                           onchange="toggleTimeInput('<?= $m['user_code'] ?>', 'Present')">
                                                    Present
                                                </label>
                                                <label class="absent-label">
                                                    <input type="radio" name="attendance[<?= $m['user_code'] ?>][status]" 
                                                           value="Absent" 
                                                           <?= ($m['status'] === "Absent") ? "checked" : "" ?>
                                                           onchange="toggleTimeInput('<?= $m['user_code'] ?>', 'Absent')">
                                                    Absent
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="time" class="time-input" 
                                                   id="time_<?= $m['user_code'] ?>" 
                                                   name="attendance[<?= $m['user_code'] ?>][time_in]" 
                                                   value="<?= $m['time_in'] ?? '' ?>" 
                                                   <?= ($m['status'] === "Present") ? "" : "disabled" ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>
                </form>
            </div>
        </div>
    </div>

<script>
function toggleTimeInput(userCode, status) {
    const timeInput = document.getElementById('time_' + userCode);
    if (status === 'Present') {
        timeInput.disabled = false;
        timeInput.value = timeInput.value || new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    } else {
        timeInput.disabled = true;
        timeInput.value = '';
    }
}

// Auto-submit when date changes
document.addEventListener("DOMContentLoaded", () => {
    const dateInput = document.querySelector('#dateForm input[type="date"]');
    if (dateInput) {
        dateInput.addEventListener("change", () => {
            document.getElementById("dateForm").submit();
        });
    }
});
</script>
</body>
</html>
