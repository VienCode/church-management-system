<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_email = $_SESSION['email'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

// ✅ Ensure leader exists or auto-register
$check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? LIMIT 1");
$check_leader->bind_param("s", $user_email);
$check_leader->execute();
$leader_data = $check_leader->get_result()->fetch_assoc();
$check_leader->close();

if (!$leader_data && !empty($user_email)) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, status, created_at)
        VALUES (?, ?, (SELECT contact FROM users WHERE email = ? LIMIT 1), 'active', NOW())
    ");
    $insert->bind_param("sss", $fullname, $user_email, $user_email);
    $insert->execute();
    $insert->close();
    header("Location: cell_group_attendance.php");
    exit;
}

$leader_id = $leader_data['leader_id'];

// ✅ Get active cell group
$group_stmt = $mysqli->prepare("
    SELECT id, group_name
    FROM cell_groups
    WHERE leader_id = ? AND status = 'active'
    LIMIT 1
");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not yet assigned to any Cell Group.</h2>";
    exit;
}

$group_id = $group['id'];
$group_name = $group['group_name'];

// ✅ Get selected meeting
$meeting_id = $_GET['meeting_id'] ?? null;

// ✅ Save attendance when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    foreach ($_POST['attendance'] as $user_code => $status) {
        $stmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance (meeting_id, user_code, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("iss", $meeting_id, $user_code, $status);
        $stmt->execute();
        $stmt->close();
    }
    $success = "✅ Attendance updated successfully for this meeting.";
}

// ✅ Fetch members under this group
$members_stmt = $mysqli->prepare("
    SELECT 
        u.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        COALESCE(a.status, 'Not Marked') AS attendance_status
    FROM cell_group_members m
    JOIN users u ON m.user_code = u.user_code
    LEFT JOIN cell_group_attendance a ON a.user_code = u.user_code AND a.meeting_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("ii", $meeting_id, $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();

// ✅ Fetch all meetings
$meetings_stmt = $mysqli->prepare("
    SELECT id, title, description, meeting_date
    FROM cell_group_meetings
    WHERE cell_group_id = ?
    ORDER BY meeting_date DESC
");
$meetings_stmt->bind_param("i", $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📝 Cell Group Attendance | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1100px;
    margin: 30px auto;
}
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; padding-bottom:5px; margin-bottom:15px; }
.save-btn { background:#0271c0; color:white; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
.save-btn:hover { background:#02589b; }
.status-btn { padding:6px 10px; border-radius:6px; cursor:pointer; font-weight:600; border:none; }
.present { background:#28a745; color:white; }
.absent { background:#dc3545; color:white; }
.late { background:#ffc107; color:black; }
.active { transform:scale(1.05); box-shadow:0 0 5px rgba(0,0,0,0.2); }
.success { background:#e6ffed; color:#256029; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:600; }
.summary { margin-top:15px; display:flex; gap:10px; justify-content:center; }
.summary div { background:#f4f7fa; padding:10px 18px; border-radius:8px; font-weight:600; }
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="cell-container">
<h1>📝 Cell Group Attendance</h1>
<p><strong><?= htmlspecialchars($group_name) ?></strong></p>

<?php if (!empty($success)): ?>
<div class="success"><?= $success ?></div>
<?php endif; ?>

<!-- Select Meeting -->
<section>
<h2 class="section-title">📅 Select Meeting</h2>
<form method="GET">
<select name="meeting_id" required>
<option value="">Select Meeting</option>
<?php while ($m = $meetings->fetch_assoc()): ?>
<option value="<?= $m['id'] ?>" <?= $meeting_id == $m['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($m['title'] . ' - ' . date('F j, Y', strtotime($m['meeting_date']))) ?>
</option>
<?php endwhile; ?>
</select>
<button type="submit" class="save-btn">View Attendance</button>
</form>
</section>

<!-- Attendance Table -->
<?php if ($meeting_id): ?>
<section style="margin-top:30px;">
<h2 class="section-title">👥 Mark Attendance</h2>
<form method="POST">
<input type="hidden" name="meeting_id" value="<?= $meeting_id ?>">
<table>
<thead><tr><th>Code</th><th>Name</th><th>Status</th></tr></thead>
<tbody>
<?php
$present = $absent = $late = 0;
while ($m = $members->fetch_assoc()):
    if ($m['attendance_status'] === 'Present') $present++;
    elseif ($m['attendance_status'] === 'Absent') $absent++;
    elseif ($m['attendance_status'] === 'Late') $late++;
?>
<tr>
<td><?= htmlspecialchars($m['user_code']) ?></td>
<td><?= htmlspecialchars($m['fullname']) ?></td>
<td>
    <input type="hidden" name="attendance[<?= htmlspecialchars($m['user_code']) ?>]" value="<?= htmlspecialchars($m['attendance_status']) ?>">
    <button type="button" class="status-btn present <?= $m['attendance_status']=='Present'?'active':'' ?>" onclick="setStatus(this,'Present')">Present</button>
    <button type="button" class="status-btn absent <?= $m['attendance_status']=='Absent'?'active':'' ?>" onclick="setStatus(this,'Absent')">Absent</button>
    <button type="button" class="status-btn late <?= $m['attendance_status']=='Late'?'active':'' ?>" onclick="setStatus(this,'Late')">Late</button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>

<!-- Summary -->
<div class="summary">
<div>✅ Present: <?= $present ?></div>
<div>❌ Absent: <?= $absent ?></div>
<div>⏰ Late: <?= $late ?></div>
</div>
</form>
</section>
<?php endif; ?>
</div></div></div>

<script>
function setStatus(btn, value) {
    const row = btn.closest('tr');
    const hidden = row.querySelector('input[type=hidden]');
    hidden.value = value;
    row.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>
</body>
</html>
