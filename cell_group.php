<?php
include 'database.php';
include 'auth_check.php';

// Ensure user is logged in and has a role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit;
}

// Allow only Admin, Leader, or Member roles
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_MEMBER]);

$user_role = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

if ($user_role == ROLE_LEADER) {
    // ✅ Get leader info based on logged-in user’s email (not user_id)
    $leaderQuery = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE email = (SELECT email FROM users WHERE id = ?)");
    $leaderQuery->bind_param("i", $user_id);
    $leaderQuery->execute();
    $leader = $leaderQuery->get_result()->fetch_assoc();

    if (!$leader) {
        die("❌ This leader is not registered in the leaders table.");
    }

    $leader_id = $leader['leader_id'];
    $leader_name = $leader['leader_name'];

    // ✅ Fetch members assigned to this leader
    $members = $mysqli->query("
        SELECT id, firstname, lastname, user_code
        FROM users 
        WHERE leader_id = $leader_id AND role_id IN (3,5,6,7,8)
        ORDER BY lastname ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

if ($user_role == ROLE_MEMBER) {
    // ✅ Fetch member’s assigned leader_id
    $memberLeaderQuery = $mysqli->prepare("SELECT leader_id FROM users WHERE id = ?");
    $memberLeaderQuery->bind_param("i", $user_id);
    $memberLeaderQuery->execute();
    $leader = $memberLeaderQuery->get_result()->fetch_assoc();
    $leader_id = $leader['leader_id'] ?? null;

    // ✅ Get leader info and group members
    $leaderInfo = $mysqli->query("SELECT leader_name FROM leaders WHERE leader_id = $leader_id")->fetch_assoc();
    $members = $mysqli->query("
        SELECT firstname, lastname, user_code 
        FROM users 
        WHERE leader_id = $leader_id
        ORDER BY lastname ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

// ✅ Handle attendance saving (Leader only)
if ($user_role == ROLE_LEADER && isset($_POST['save_cell_attendance'])) {
    $activity_name = trim($_POST['activity_name']);
    $attendance_date = $_POST['attendance_date'];

    // ✅ Insert new cell group activity
    $stmt = $mysqli->prepare("
        INSERT INTO cell_group_attendance (leader_id, activity_name, attendance_date)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE activity_name = VALUES(activity_name)
    ");
    $stmt->bind_param("iss", $leader_id, $activity_name, $attendance_date);
    $stmt->execute();
    $attendance_id = $stmt->insert_id ?: $mysqli->insert_id;

    // ✅ Save member attendance records
    foreach ($_POST['attendance'] as $member_id => $data) {
        $status = $data['status'] ?? 'Absent';
        $time_in = ($status === 'Present') ? ($data['time_in'] ?? date('H:i')) : null;

        $recordStmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance_records (attendance_id, member_id, status, time_in)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), time_in=VALUES(time_in)
        ");
        $recordStmt->bind_param("iiss", $attendance_id, $member_id, $status, $time_in);
        $recordStmt->execute();
    }

    $_SESSION['cell_success'] = "✅ Cell group attendance saved for " . date("F j, Y", strtotime($attendance_date));
    header("Location: cell_group.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Attendance | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:24px; border-radius:12px; margin:30px auto; max-width:1100px; box-shadow:0 2px 10px rgba(0,0,0,0.08);}
.success { background:#e6ffed; color:#256029; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:600; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:#fff; }
.present-btn, .absent-btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
.present-btn { background:#28a745; color:white; }
.absent-btn { background:#dc3545; color:white; }
.active { box-shadow:0 2px 6px rgba(0,0,0,0.15); transform:translateY(-1px); }
.time-input { border:1px solid #ccc; padding:6px; border-radius:6px; width:110px; text-align:center; }
.save-btn { background:#0271c0; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; margin-top:16px; }
.save-btn:hover { background:#02589b; }
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-area">
<div class="cell-container">

<?php if (isset($_SESSION['cell_success'])): ?>
    <div class="success"><?= $_SESSION['cell_success'] ?></div>
    <?php unset($_SESSION['cell_success']); ?>
<?php endif; ?>

<?php if ($user_role == ROLE_LEADER): ?>
    <h1>📋 <?= htmlspecialchars($leader_name) ?>’s Cell Group</h1>
    <form method="POST">
        <label><strong>Activity Name:</strong></label>
        <input type="text" name="activity_name" placeholder="e.g., Foundation Day" required>
        <br><br>
        <label><strong>Date:</strong></label>
        <input type="date" name="attendance_date" value="<?= $today ?>" max="<?= $today ?>" required>
        <br><br>
        <table>
            <thead>
                <tr><th>#</th><th>User Code</th><th>Name</th><th>Status</th><th>Time In</th></tr>
            </thead>
            <tbody>
                <?php $i=1; foreach($members as $m): ?>
                <tr data-id="<?= $m['id'] ?>">
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($m['user_code']) ?></td>
                    <td><?= htmlspecialchars($m['firstname'].' '.$m['lastname']) ?></td>
                    <td>
                        <button type="button" class="present-btn" data-action="present">Present</button>
                        <button type="button" class="absent-btn" data-action="absent">Absent</button>
                        <input type="hidden" name="attendance[<?= $m['id'] ?>][status]" value="">
                    </td>
                    <td><input type="time" name="attendance[<?= $m['id'] ?>][time_in]" class="time-input" disabled></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <center><button type="submit" name="save_cell_attendance" class="save-btn">💾 Save Attendance</button></center>
    </form>

<?php elseif ($user_role == ROLE_MEMBER): ?>
    <h1>👥 My Cell Group</h1>
    <h3>Leader: <?= htmlspecialchars($leaderInfo['leader_name']) ?></h3>
    <table>
        <thead><tr><th>#</th><th>User Code</th><th>Name</th></tr></thead>
        <tbody>
            <?php $i=1; foreach($members as $m): ?>
            <tr><td><?= $i++ ?></td><td><?= htmlspecialchars($m['user_code']) ?></td><td><?= htmlspecialchars($m['firstname'].' '.$m['lastname']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
</div>
</div>

<script>
function pad(num){return num.toString().padStart(2,'0');}
document.addEventListener('click', e => {
    if (e.target.matches('.present-btn, .absent-btn')) {
        const row = e.target.closest('tr');
        const hidden = row.querySelector('input[type=hidden]');
        const timeInput = row.querySelector('.time-input');
        const presentBtn = row.querySelector('.present-btn');
        const absentBtn = row.querySelector('.absent-btn');
        const now = new Date();

        if (e.target.dataset.action === 'present') {
            hidden.value = 'Present';
            timeInput.disabled = false;
            timeInput.value = pad(now.getHours()) + ':' + pad(now.getMinutes());
            presentBtn.classList.add('active');
            absentBtn.classList.remove('active');
        } else {
            hidden.value = 'Absent';
            timeInput.value = '';
            timeInput.disabled = true;
            absentBtn.classList.add('active');
            presentBtn.classList.remove('active');
        }
    }
});
</script>
</body>
</html>
