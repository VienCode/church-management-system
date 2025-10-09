<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

// current marker (who records the attendance)
$current_user_code = $_SESSION['user_code'] ?? 'SYSTEM';

// Determine attendance date (prefer posted date)
$attendance_date = $_POST['attendance_date'] ?? ($_SESSION['attendance_date'] ?? date('Y-m-d'));

// Save attendance when submitted
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    // Use the attendance_date sent with the save form (safer)
    $attendance_date = $_POST['attendance_date'] ?? $attendance_date;

    if (!empty($_POST['attendance']) && is_array($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $user_code => $data) {
            $status = $data['status'] ?? null;
            // If time given use it, else use server current time for Present
            $time_in = ($status === 'Present') ? ($data['time_in'] ?? date('H:i')) : null;

            if ($status) {
                $stmt = $mysqli->prepare("
                    INSERT INTO attendance (user_code, attendance_date, status, time_in, recorded_by)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in), recorded_by = VALUES(recorded_by)
                ");
                $stmt->bind_param("sssss", $user_code, $attendance_date, $status, $time_in, $current_user_code);
                $stmt->execute();
            }
        }
        $success = "✅ Attendance saved for " . date("F j, Y", strtotime($attendance_date)) . "!";
    } else {
        $success = "⚠️ Nothing to save.";
    }
}

// Fetch users except non-members (role_id = 4)
$sql = "
SELECT u.user_code,
       CONCAT(u.firstname, ' ', u.lastname) AS fullname,
       r.role_name,
       a.status,
       a.time_in
FROM users u
JOIN roles r ON u.role_id = r.role_id
LEFT JOIN attendance a ON a.user_code = u.user_code AND a.attendance_date = ?
WHERE u.role_id != 4
ORDER BY u.lastname ASC, u.firstname ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$members_result = $stmt->get_result();

// Compute stats
$presentCount = 0;
$absentCount = 0;
$members_data = [];
while ($row = $members_result->fetch_assoc()) {
    // Normalize time_in to HH:MM for display (if present)
    if (!empty($row['time_in'])) {
        $row['time_in'] = date('H:i', strtotime($row['time_in']));
    }
    $members_data[] = $row;
    if ($row['status'] === 'Present') $presentCount++;
    if ($row['status'] === 'Absent') $absentCount++;
}
$totalMembers = count($members_data);
$notMarked = $totalMembers - ($presentCount + $absentCount);

$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Management | UCF</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        /* Inline styles for attendance module (keeps design consistent) */
        .attendance-container { background:#fff; padding:24px; border-radius:12px; max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08); }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
        th { background:#0271c0; color:#fff; }
        .present-btn, .absent-btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
        .present-btn { background:#28a745; color:#fff; }
        .absent-btn { background:#dc3545; color:#fff; }
        .present-btn.selected { box-shadow:0 2px 6px rgba(40,167,69,0.28); transform:translateY(-1px); }
        .absent-btn.selected { box-shadow:0 2px 6px rgba(220,53,69,0.28); transform:translateY(-1px); }
        .time-input { padding:6px; border-radius:6px; border:1px solid #ccc; width:110px; text-align:center; }
        .save-btn { background:#0271c0; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; margin-top:16px; }
        .save-btn:hover { background:#02589b; }
        .stats { display:flex; gap:12px; margin-top:18px; }
        .stat-card { flex:1; background:#f6f8fb; padding:10px; border-radius:10px; text-align:center; }
        .success { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:700; }
        .date-selector { margin-bottom:14px; display:flex; gap:10px; align-items:center; }
        input[type="date"] { padding:8px; border-radius:6px; border:1px solid #ccc; }
    </style>
</head>
<body>
<div class="main-layout">
    <!-- Sidebar (same as other pages) -->
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


    <div class="content-area">
        <div class="attendance-container">
            <h1>👥 Attendance Management</h1>
            <p>Choose a date (past or today), mark Present or Absent. Present will auto-fill arrival time.</p>

            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Date selector form (view attendance for selected date) -->
            <form method="POST" id="dateForm" class="date-selector" style="margin:0;">
                <label><strong>Attendance Date:</strong></label>
                <input type="date" name="attendance_date" id="attendance_date_input" value="<?= htmlspecialchars($attendance_date) ?>" max="<?= date('Y-m-d') ?>" required>
                <button type="submit" name="view_attendance" class="save-btn" style="padding:8px 12px;">View</button>
            </form>

            <!-- Stats -->
            <div class="stats" aria-hidden="true">
                <div class="stat-card"><strong>Present</strong><div style="font-size:18px; margin-top:6px; color:#28a745;"><?= $presentCount ?></div></div>
                <div class="stat-card"><strong>Absent</strong><div style="font-size:18px; margin-top:6px; color:#dc3545;"><?= $absentCount ?></div></div>
                <div class="stat-card"><strong>Total</strong><div style="font-size:18px; margin-top:6px; color:#0271c0;"><?= $totalMembers ?></div></div>
                <div class="stat-card"><strong>Not Marked</strong><div style="font-size:18px; margin-top:6px;"><?= $notMarked ?></div></div>
            </div>

            <!-- Attendance table & save form -->
            <form method="POST" id="attendanceForm" style="margin-top:14px;">
                <!-- include the attendance_date so save knows which date -->
                <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>">

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
                        <?php if (empty($members_data)): ?>
                            <tr><td colspan="6">No users found for this filter.</td></tr>
                        <?php else: $i = 1; ?>
                            <?php foreach ($members_data as $m): 
                                // Create a safe id suffix from user_code (replace non-alnum)
                                $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $m['user_code']);
                                $isPresent = ($m['status'] === 'Present');
                                $isAbsent  = ($m['status'] === 'Absent');
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><strong><?= htmlspecialchars($m['user_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($m['fullname']) ?></td>
                                    <td><?= htmlspecialchars($m['role_name']) ?></td>
                                    <td>
                                        <button type="button" id="present_btn_<?= $safe ?>" class="present-btn <?= $isPresent ? 'selected' : '' ?>"
                                            onclick="markPresent('<?= $safe ?>')">Present</button>

                                        <button type="button" id="absent_btn_<?= $safe ?>" class="absent-btn <?= $isAbsent ? 'selected' : '' ?>"
                                            onclick="markAbsent('<?= $safe ?>')">Absent</button>

                                        <!-- Hidden status field (will be either Present/Absent) -->
                                        <input type="hidden" id="status_<?= $safe ?>" name="attendance[<?= htmlspecialchars($m['user_code']) ?>][status]" value="<?= htmlspecialchars($m['status'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <input type="time" id="time_<?= $safe ?>" name="attendance[<?= htmlspecialchars($m['user_code']) ?>][time_in]" class="time-input" 
                                            value="<?= htmlspecialchars($m['time_in'] ?? '') ?>" <?= $isPresent ? '' : 'disabled' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Utility: safe ID suffix => same transformation we used server-side:
 * (only alnum, underscore, hyphen are kept; others replaced by underscore)
 * We pass the safe suffix strings from server, so JS doesn't have to transform.
 */

// markPresent / markAbsent toggle and set time in HH:MM
function pad(n){ return n.toString().padStart(2,'0'); }

function markPresent(safe) {
    try {
        // toggle classes
        const presentBtn = document.getElementById('present_btn_' + safe);
        const absentBtn  = document.getElementById('absent_btn_' + safe);
        const statusInp  = document.getElementById('status_' + safe);
        const timeInp    = document.getElementById('time_' + safe);

        if (!statusInp || !timeInp || !presentBtn) return;

        // Set status
        statusInp.value = 'Present';

        // Set time to current time (HH:MM 24h)
        const now = new Date();
        const hh = pad(now.getHours());
        const mm = pad(now.getMinutes());
        timeInp.value = hh + ':' + mm;
        timeInp.disabled = false;

        // Visual toggle
        presentBtn.classList.add('selected');
        absentBtn.classList.remove('selected');
    } catch (e) {
        console.error(e);
    }
}

function markAbsent(safe) {
    try {
        const presentBtn = document.getElementById('present_btn_' + safe);
        const absentBtn  = document.getElementById('absent_btn_' + safe);
        const statusInp  = document.getElementById('status_' + safe);
        const timeInp    = document.getElementById('time_' + safe);

        if (!statusInp || !timeInp || !absentBtn) return;

        statusInp.value = 'Absent';
        timeInp.value = '';
        timeInp.disabled = true;

        absentBtn.classList.add('selected');
        presentBtn.classList.remove('selected');
    } catch (e) {
        console.error(e);
    }
}

// Auto-submit date change to view that date's attendance
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('attendance_date_input');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            // Ensure no future date
            const selected = this.value;
            const today = new Date().toISOString().slice(0,10);
            if (selected > today) {
                alert('You cannot select a future date.');
                this.value = today;
                return;
            }
            document.getElementById('dateForm').submit();
        });
    }
});
</script>
</body>
</html>
