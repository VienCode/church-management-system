<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

$current_user_code = $_SESSION['user_code'] ?? null;
$today = date('Y-m-d');

// Handle date selection
$attendance_date = $_POST['attendance_date'] ?? ($_SESSION['evangelism_date'] ?? $today);
$_SESSION['evangelism_date'] = $attendance_date;

// Handle save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evangelism'])) {
    foreach ($_POST['attendance'] as $id => $data) {
        $status = $data['status'] ?? null;
        $time_in = ($status === 'Present') ? ($data['time_in'] ?? date('H:i')) : null;

        if ($status) {
            $stmt = $mysqli->prepare("
                INSERT INTO evangelism_attendance (non_member_id, attendance_date, status, time_in, recorded_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status=VALUES(status),
                    time_in=VALUES(time_in),
                    recorded_by=VALUES(recorded_by)
            ");
            $stmt->bind_param("issss", $id, $attendance_date, $status, $time_in, $current_user_code);
            $stmt->execute();

            if ($status === 'Present') {
                $mysqli->query("
                    UPDATE non_members 
                    SET attendances_count = attendances_count + 1,
                        last_attended = '$attendance_date'
                    WHERE id = $id
                ");
            }
        }
    }

    $_SESSION['evangelism_success'] = "✅ Evangelism attendance saved for " . date("F j, Y", strtotime($attendance_date)) . "!";
    header("Location: evangelism.php");
    exit();
}

// Fetch non-members
$result = $mysqli->query("
    SELECT id, firstname, lastname, contact, email, attendances_count
    FROM non_members
    ORDER BY lastname ASC
");

$non_members = $result->fetch_all(MYSQLI_ASSOC);

// Check for promotion eligibility
$eligible = array_filter($non_members, fn($m) => $m['attendances_count'] >= 10);
$eligibleCount = count($eligible);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Evangelism Attendance | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.evangelism-container {
    background:#fff; padding:24px; border-radius:12px;
    max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08);
}
.success { background:#e6ffed; color:#256029; padding:10px; border-radius:8px; margin-bottom:12px; font-weight:600; }
.promo-alert { background:#fff3cd; color:#856404; padding:12px; border-radius:8px; margin-bottom:20px; border:1px solid #ffeeba; }
.present-btn, .absent-btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
.present-btn { background:#28a745; color:white; }
.absent-btn { background:#dc3545; color:white; }
.active { box-shadow:0 2px 6px rgba(0,0,0,0.15); transform:translateY(-1px); }
.time-input { border:1px solid #ccc; padding:6px; border-radius:6px; width:110px; text-align:center; }
.save-btn { background:#0271c0; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; margin-top:16px; }
.save-btn:hover { background:#02589b; }
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
        <div class="evangelism-container">
            <h1>🌱 Evangelism Attendance</h1>
            <p>Track attendance for all <strong>Non-Members</strong> (Guests).</p>

            <?php if (isset($_SESSION['evangelism_success'])): ?>
                <div class="success"><?= $_SESSION['evangelism_success'] ?></div>
                <?php unset($_SESSION['evangelism_success']); ?>
            <?php endif; ?>

            <?php if ($eligibleCount > 0): ?>
                <div class="promo-alert">
                    <strong>🎉 <?= $eligibleCount ?> Non-Member<?= $eligibleCount > 1 ? 's' : '' ?> eligible for promotion!</strong><br>
                    <a href="promotion_page.php" class="save-btn" style="margin-top:10px; display:inline-block;">Review Now →</a>
                </div>
            <?php endif; ?>

            <!-- Date Selection -->
            <form method="POST">
                <label><strong>Attendance Date:</strong></label>
                <input type="date" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>" max="<?= date('Y-m-d') ?>" required>
                <button type="submit" name="view_evangelism" class="save-btn" style="padding:8px 12px;">View</button>
            </form>

            <!-- Attendance Table -->
            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Attendances</th>
                            <th>Status</th>
                            <th>Time In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($non_members)): ?>
                            <tr><td colspan="7">No Non-Members found.</td></tr>
                        <?php else: $i=1; foreach ($non_members as $m): ?>
                            <tr data-id="<?= $m['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?></td>
                                <td><?= htmlspecialchars($m['contact']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= $m['attendances_count'] ?></td>
                                <td>
                                    <button type="button" class="present-btn" data-action="present">Present</button>
                                    <button type="button" class="absent-btn" data-action="absent">Absent</button>
                                    <input type="hidden" name="attendance[<?= $m['id'] ?>][status]" value="">
                                </td>
                                <td><input type="time" name="attendance[<?= $m['id'] ?>][time_in]" class="time-input" disabled></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <center><button type="submit" name="save_evangelism" class="save-btn">💾 Save Evangelism Attendance</button></center>
            </form>

            <!-- Summary Section -->
            <div class="summary" id="liveSummary">
                <div id="sumPresent">✅ Present: 0</div>
                <div id="sumAbsent">❌ Absent: 0</div>
                <div id="sumNot">⏳ Not Marked: <?= count($non_members) ?></div>
                <div id="sumTotal">👥 Total: <?= count($non_members) ?></div>
            </div>
        </div>
    </div>
</div>

<script>
function pad(num){return num.toString().padStart(2,'0');}

document.addEventListener('click', e => {
    if (e.target.matches('.present-btn, .absent-btn')) {
        const row = e.target.closest('tr');
        const action = e.target.dataset.action;
        const hidden = row.querySelector('input[type=hidden]');
        const timeInput = row.querySelector('.time-input');
        const presentBtn = row.querySelector('.present-btn');
        const absentBtn = row.querySelector('.absent-btn');

        if (action === 'present') {
            hidden.value = 'Present';
            timeInput.disabled = false;
            const now = new Date();
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
        updateSummary();
    }
});

function updateSummary() {
    const rows = [...document.querySelectorAll('tbody tr[data-id]')];
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
