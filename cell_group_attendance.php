<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$meeting_id = intval($_GET['meeting_id'] ?? 0);
if (!$meeting_id) {
    echo "<h2 style='text-align:center;color:red;'>Invalid meeting.</h2>";
    exit;
}

// ✅ Get meeting info
$meeting = $mysqli->query("
    SELECT m.*, g.group_name 
    FROM cell_group_meetings m
    JOIN cell_groups g ON m.cell_group_id = g.id
    WHERE m.id = $meeting_id
")->fetch_assoc();

if (!$meeting) {
    echo "<h2 style='text-align:center;color:red;'>Meeting not found.</h2>";
    exit;
}

$group_id = $meeting['cell_group_id'];

// ✅ Fetch members
$members = $mysqli->query("
    SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    (SELECT a.status FROM cell_group_attendance a WHERE a.meeting_id = $meeting_id AND a.member_id = u.id) AS status
    FROM cell_group_members m
    JOIN users u ON m.member_id = u.id
    WHERE m.cell_group_id = $group_id
");

// ✅ Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['attendance'] as $member_id => $status) {
        $stmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance (meeting_id, member_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("iis", $meeting_id, $member_id, $status);
        $stmt->execute();
        $stmt->close();
    }
    $msg = "✅ Attendance updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📝 Attendance | <?= htmlspecialchars($meeting['title']) ?></title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<h1>📝 Attendance for <?= htmlspecialchars($meeting['title']) ?></h1>
<p><?= htmlspecialchars($meeting['description']) ?></p>
<p><strong>Date:</strong> <?= htmlspecialchars($meeting['meeting_date']) ?></p>
<?php if(isset($msg)) echo "<div class='success'>$msg</div>"; ?>
<form method="POST">
<table>
<tr><th>Name</th><th>Status</th></tr>
<?php while($m=$members->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($m['fullname']) ?></td>
<td>
<select name="attendance[<?= $m['id'] ?>]">
<?php
$options = ['Present','Absent','Late'];
foreach($options as $opt) {
    $sel = ($m['status']==$opt)?'selected':'';
    echo "<option value='$opt' $sel>$opt</option>";
}
?>
</select>
</td>
</tr>
<?php endwhile; ?>
</table>
<br>
<button type="submit" class="save-btn">💾 Save Attendance</button>
</form>
</div></div></body></html>
