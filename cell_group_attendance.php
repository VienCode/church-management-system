<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER]);

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) {
    echo "<div style='background:#ffe6e6;padding:12px;border-radius:6px;'>❌ No meeting selected.</div>";
    exit();
}

// Fetch meeting info
$stmt = $mysqli->prepare("
    SELECT m.id, m.title, m.meeting_date, cg.group_name
    FROM cell_group_meetings m
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    echo "<div style='background:#ffe6e6;padding:12px;border-radius:6px;'>❌ Meeting not found.</div>";
    exit();
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    foreach ($_POST['attendance'] as $member_id => $status) {
        $stmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance (meeting_id, member_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("iis", $meeting_id, $member_id, $status);
        $stmt->execute();
    }
    $success = "✅ Attendance saved!";
}

// Fetch members of the same group
$members = $mysqli->prepare("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname
    FROM cell_group_members cgm
    JOIN users u ON cgm.member_id = u.id
    WHERE cgm.cell_group_id = (SELECT cell_group_id FROM cell_group_meetings WHERE id = ?)
");
$members->bind_param("i", $meeting_id);
$members->execute();
$members_result = $members->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mark Attendance</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff;padding:20px;border-radius:10px;margin:30px auto;max-width:1000px;box-shadow:0 2px 10px rgba(0,0,0,.08); }
table { width:100%;border-collapse:collapse;margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__.'/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h2>🗓️ <?= htmlspecialchars($meeting['title']) ?> - <?= htmlspecialchars($meeting['meeting_date']) ?></h2>
            <p>Group: <strong><?= htmlspecialchars($meeting['group_name']) ?></strong></p>
            <?php if(isset($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>

            <form method="POST">
                <table>
                    <thead><tr><th>User Code</th><th>Name</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while($m = $members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['user_code']) ?></td>
                            <td><?= htmlspecialchars($m['fullname']) ?></td>
                            <td>
                                <select name="attendance[<?= $m['id'] ?>]">
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Late">Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <center><button type="submit" class="btn">💾 Save Attendance</button></center>
            </form>
        </div>
    </div>
</div>
</body>
</html>
