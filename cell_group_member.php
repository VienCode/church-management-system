<?php
// cell_group_member.php
session_start();
include 'database.php';
if (!isset($_SESSION['role']) || !isset($_SESSION['id'])) { header('Location: login.php'); exit; }
$is_member = ($_SESSION['role'] === 'member' || $_SESSION['role'] === 3);
if (!$is_member && !($_SESSION['role']==='leader' || $_SESSION['role']==2)) {
    http_response_code(403); echo "Access denied."; exit;
}

$user_id = intval($_SESSION['id']);

// find the cell group this member belongs to
$stmt = $mysqli->prepare("
    SELECT cg.id, cg.group_name
    FROM cell_groups cg
    JOIN cell_group_members cm ON cm.cell_group_id = cg.id
    WHERE cm.member_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "You are not assigned to a cell group."; exit;
}

// fetch meetings with user's attendance
$stmt = $mysqli->prepare("
    SELECT m.id, m.title, m.description, m.meeting_date,
           a.status
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a ON a.meeting_id = m.id AND a.member_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY m.meeting_date DESC
");
$stmt->bind_param('ii', $user_id, $group['id']);
$stmt->execute();
$meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>My Cell Group — Meetings</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { max-width:1000px; margin:30px auto; background:#fff; padding:16px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.table { width:100%; border-collapse:collapse; margin-top:10px; }
.table th, .table td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
.badge { padding:6px 10px; border-radius:6px; color:#fff; }
.present { background:#28a745; } .absent { background:#dc3545; } .late { background:#f0ad4e; }
</style>
</head>
<body>
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
    <h2><?=htmlspecialchars($group['group_name'])?> — Meetings</h2>
    <?php if (empty($meetings)): ?>
        <p>No meetings recorded.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Your Status</th></tr></thead>
            <tbody>
            <?php foreach($meetings as $m): ?>
                <tr>
                    <td><?=htmlspecialchars(date('M d, Y', strtotime($m['meeting_date'])))?></td>
                    <td><?=htmlspecialchars($m['title'])?></td>
                    <td><?=htmlspecialchars($m['description'])?></td>
                    <td>
                        <?php
                        if ($m['status'] === 'Present') echo '<span class="badge present">Present</span>';
                        elseif ($m['status'] === 'Absent') echo '<span class="badge absent">Absent</span>';
                        elseif ($m['status'] === 'Late') echo '<span class="badge late">Late</span>';
                        else echo '<em>Not marked</em>';
                        ?>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</div>
</body>
</html>
