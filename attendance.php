<?php
include 'database.php';
include 'auth_check.php';
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
exit;


restrict_to_roles([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER]);

$current_user_code = $_SESSION['user_code'] ?? null;

// ✅ Ensure one unique attendance record per (user_code, attendance_date)
$mysqli->query("
    ALTER TABLE attendance 
    ADD UNIQUE KEY IF NOT EXISTS unique_user_date (user_code, attendance_date)
");

// ✅ Determine and store selected date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_date'])) {
    $attendance_date = $_POST['attendance_date'];
    $_SESSION['attendance_date'] = $attendance_date;
} else {
    $attendance_date = $_SESSION['attendance_date'] ?? date('Y-m-d');
}

// ✅ Handle saving attendance
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');

    if (!empty($_POST['attendance']) && is_array($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $user_code => $data) {
            $status = $data['status'] ?? null;
            $time_in = ($status === 'Present') ? ($data['time_in'] ?? date('H:i')) : null;

            // Ensure the user exists
            $verify = $mysqli->prepare("SELECT user_code FROM users WHERE user_code = ?");
            $verify->bind_param("s", $user_code);
            $verify->execute();
            $exists = $verify->get_result()->num_rows > 0;
            $verify->close();

            if ($exists && $status) {
                $stmt = $mysqli->prepare("
                    INSERT INTO attendance (user_code, attendance_date, status, time_in, recorded_by)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        status = VALUES(status),
                        time_in = VALUES(time_in),
                        recorded_by = IFNULL(VALUES(recorded_by), recorded_by)
                ");
                $stmt->bind_param("sssss", $user_code, $attendance_date, $status, $time_in, $current_user_code);
                $stmt->execute();
            }
        }
        $success = "✅ Attendance saved for " . date("F j, Y", strtotime($attendance_date)) . "!";
    } else {
        $success = "⚠️ No attendance data submitted.";
    }
}

// ✅ Fetch users except Non-Members (role_id = 4)
$sql = "
SELECT 
    u.user_code,
    CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    r.role_name,
    a.status,
    a.time_in
FROM users u
JOIN roles r ON u.role_id = r.role_id
LEFT JOIN attendance a 
    ON a.user_code = u.user_code 
    AND a.attendance_date = ?
WHERE u.role_id != 4
ORDER BY u.lastname ASC, u.firstname ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$members = $stmt->get_result();

$presentCount = $absentCount = 0;
$rows = [];
while ($row = $members->fetch_assoc()) {
    if ($row['status'] === 'Present') $presentCount++;
    if ($row['status'] === 'Absent')  $absentCount++;
    $rows[] = $row;
}
$totalMembers = count($rows);
$notMarked = $totalMembers - ($presentCount + $absentCount);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Attendance Management | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.attendance-container { background:#fff; padding:24px; border-radius:12px; max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08); }
table { width:100%; border-collapse:collapse; margin-top:14px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:#fff; }
.present-btn, .absent-btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
.present-btn { background:#28a745; color:#fff; }
.absent-btn { background:#dc3545; color:#fff; }
.present-btn.active { box-shadow:0 2px 6px rgba(40,167,69,0.28); transform:translateY(-1px); }
.absent-btn.active { box-shadow:0 2px 6px rgba(220,53,69,0.28); transform:translateY(-1px); }
.time-input { padding:6px; border-radius:6px; border:1px solid #ccc; width:110px; text-align:center; }
.save-btn { background:#0271c0; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; margin-top:16px; }
.save-btn:hover { background:#02589b; }
.success { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:700; }
.summary { margin-top:18px; display:flex; gap:10px; justify-content:center; }
.summary div { background:#f6f8fb; padding:10px 18px; border-radius:8px; font-weight:600; }
input[type="date"] { padding:8px; border-radius:6px; border:1px solid #ccc; }
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="attendance-container">
            <h1>👥 Attendance Management</h1>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Date Selection -->
            <form method="POST">
                <label><strong>Attendance Date:</strong></label>
                <input type="date" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>" max="<?= date('Y-m-d') ?>" required>
                <button type="submit" name="view_attendance" class="save-btn" style="padding:8px 12px;">View</button>
            </form>

            <!-- Attendance Table -->
            <form method="POST" style="margin-top:20px;">
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
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6">No members found.</td></tr>
                        <?php else: $i = 1; foreach ($rows as $row): ?>
                            <tr data-user="<?= htmlspecialchars($row['user_code']) ?>">
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($row['user_code']) ?></strong></td>
                                <td><?= htmlspecialchars($row['fullname']) ?></td>
                                <td><?= htmlspecialchars($row['role_name']) ?></td>
                                <td>
                                    <button type="button" class="present-btn <?= ($row['status'] === 'Present') ? 'active' : '' ?>" data-action="present">Present</button>
                                    <button type="button" class="absent-btn <?= ($row['status'] === 'Absent') ? 'active' : '' ?>" data-action="absent">Absent</button>
                                    <input type="hidden" name="attendance[<?= htmlspecialchars($row['user_code']) ?>][status]" value="<?= htmlspecialchars($row['status'] ?? '') ?>">
                                </td>
                                <td>
                                    <input type="time" name="attendance[<?= htmlspecialchars($row['user_code']) ?>][time_in]" class="time-input"
                                           value="<?= htmlspecialchars($row['time_in'] ?? '') ?>" <?= ($row['status'] === 'Present') ? '' : 'disabled' ?>>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>
            </form>

            <!-- Summary Counter -->
            <div class="summary" id="liveSummary">
                <div id="sumPresent">✅ Present: <?= $presentCount ?></div>
                <div id="sumAbsent">❌ Absent: <?= $absentCount ?></div>
                <div id="sumNot">⏳ Not Marked: <?= $notMarked ?></div>
                <div id="sumTotal">👥 Total: <?= $totalMembers ?></div>
            </div>
        </div>
    </div>
</div>

<script>
function pad(num){return num.toString().padStart(2,'0');}

// Update status buttons + time input
document.addEventListener('click', e => {
    if (e.target.matches('.present-btn, .absent-btn')) {
        const btn = e.target;
        const row = btn.closest('tr');
        const action = btn.dataset.action;
        const timeInput = row.querySelector('.time-input');
        const hiddenStatus = row.querySelector('input[type=hidden]');
        const presentBtn = row.querySelector('.present-btn');
        const absentBtn = row.querySelector('.absent-btn');

        if (action === 'present') {
            hiddenStatus.value = 'Present';
            timeInput.disabled = false;
            const now = new Date();
            timeInput.value = pad(now.getHours()) + ':' + pad(now.getMinutes());
            presentBtn.classList.add('active');
            absentBtn.classList.remove('active');
        } else {
            hiddenStatus.value = 'Absent';
            timeInput.value = '';
            timeInput.disabled = true;
            absentBtn.classList.add('active');
            presentBtn.classList.remove('active');
        }
        updateSummary();
    }
});

// Live counter
function updateSummary() {
    const rows = [...document.querySelectorAll('tbody tr[data-user]')];
    let present = 0, absent = 0, total = rows.length;
    rows.forEach(r => {
        const status = r.querySelector('input[type=hidden]').value;
        if (status === 'Present') present++;
        else if (status === 'Absent') absent++;
    });
    const not = total - (present + absent);
    document.getElementById('sumPresent').textContent = '✅ Present: ' + present;
    document.getElementById('sumAbsent').textContent = '❌ Absent: ' + absent;
    document.getElementById('sumNot').textContent = '⏳ Not Marked: ' + not;
    document.getElementById('sumTotal').textContent = '👥 Total: ' + total;
}
</script>
</body>
</html>
