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
            // Insert or update attendance record
            $stmt = $mysqli->prepare("
                INSERT INTO evangelism_attendance (non_member_id, attendance_date, status, time_in, recorded_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    time_in = VALUES(time_in),
                    recorded_by = VALUES(recorded_by)
            ");
            $stmt->bind_param("issss", $id, $attendance_date, $status, $time_in, $current_user_code);
            $stmt->execute();

            // Update total attendance count for Present members
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

// Fetch all non-members
$result = $mysqli->query("
    SELECT id, firstname, lastname, contact, email, attendances_count
    FROM non_members
    ORDER BY lastname ASC
");

$non_members = $result->fetch_all(MYSQLI_ASSOC);
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
.promo-alert { background:#fff3cd; color:#856404; padding:12px; border-radius:8px; margin-bottom:20px; border:1px solid #ffeeba; display:none; }
.present-btn, .absent-btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
.present-btn { background:#28a745; color:white; }
.absent-btn { background:#dc3545; color:white; }
.active { box-shadow:0 2px 6px rgba(0,0,0,0.15); transform:translateY(-1px); }
.time-input { border:1px solid #ccc; padding:6px; border-radius:6px; width:110px; text-align:center; }
.save-btn { background:#0271c0; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; margin-top:16px; }
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="evangelism-container">
            <h1>🕊️ Evangelism Attendance</h1>
            <p>Track attendance for Non-Members (Guests).</p>

            <!-- ✅ Shared Promotion Alert -->
            <div id="promotionAlert" class="promo-alert">
                <strong>🎉 <span id="promoCount"></span> Non-Member(s) eligible for promotion!</strong><br>
                <a href="promotion_page.php" class="primary-btn" style="margin-top:10px; display:inline-block;">Review Now →</a>
            </div>

            <?php if (isset($_SESSION['evangelism_success'])): ?>
                <div class="success"><?= $_SESSION['evangelism_success'] ?></div>
                <?php unset($_SESSION['evangelism_success']); ?>
            <?php endif; ?>

            <form method="POST">
                <label><strong>Attendance Date:</strong></label>
                <input type="date" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>" max="<?= date('Y-m-d') ?>" required>
                <button type="submit" name="view_evangelism" class="save-btn" style="padding:8px 12px;">View</button>
            </form>

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
        </div>
    </div>
</div>

<script>
function pad(num){return num.toString().padStart(2,'0');}

// ✅ Handle marking attendance
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
    }
});

// ✅ Shared Promotion Alert (AJAX)
document.addEventListener('DOMContentLoaded', () => {
    fetch('check_promotions.php')
        .then(res => res.json())
        .then(data => {
            if (data.count > 0) {
                document.getElementById('promotionAlert').style.display = 'block';
                document.getElementById('promoCount').textContent = data.count;
            }
        })
        .catch(err => console.error('Promotion check failed:', err));
});
</script>

</body>
</html>
