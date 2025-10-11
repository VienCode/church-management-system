<?php
// cell_group_attendance.php
session_start();
include 'database.php';
if (!isset($_SESSION['role']) || !isset($_SESSION['id'])) { header('Location: login.php'); exit; }
$is_leader = ($_SESSION['role'] === 'leader' || $_SESSION['role'] === 2);
if (!$is_leader) { http_response_code(403); echo "Access denied."; exit; }

$leader_id = intval($_SESSION['id']);
$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;
$view_only = isset($_GET['view']) && $_GET['view']=='1';

if (!$meeting_id) { echo "No meeting selected."; exit; }

// verify meeting belongs to leader's group
$stmt = $mysqli->prepare("
  SELECT m.id, m.cell_group_id, m.title, m.description, m.meeting_date, g.group_name
  FROM cell_group_meetings m
  JOIN cell_groups g ON g.id = m.cell_group_id
  WHERE m.id = ? AND g.leader_id = ?
");
$stmt->bind_param('ii', $meeting_id, $leader_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) { echo "Meeting not found or you do not have permissions."; exit; }

// handle save
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$view_only && isset($_POST['attendance']) && is_array($_POST['attendance'])) {
    foreach ($_POST['attendance'] as $member_id => $status) {
        $member_id = intval($member_id);
        $status = in_array($status, ['Present','Absent','Late']) ? $status : 'Absent';
        // check if record exists
        $chk = $mysqli->prepare("SELECT id FROM cell_group_attendance WHERE meeting_id = ? AND member_id = ?");
        $chk->bind_param('ii', $meeting_id, $member_id);
        $chk->execute();
        $r = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($r) {
            $upd = $mysqli->prepare("UPDATE cell_group_attendance SET status = ?, recorded_at = NOW() WHERE id = ?");
            $upd->bind_param('si', $status, $r['id']);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $mysqli->prepare("INSERT INTO cell_group_attendance (meeting_id, member_id, status) VALUES (?, ?, ?)");
            $ins->bind_param('iis', $meeting_id, $member_id, $status);
            $ins->execute();
            $ins->close();
        }
    }
    $success = "Attendance saved.";
}

// fetch group members
$stmt = $mysqli->prepare("
    SELECT u.id, u.user_code, u.firstname, u.lastname,
           IFNULL(a.status, '') AS status
    FROM users u
    JOIN cell_group_members cm ON cm.member_id = u.id
    LEFT JOIN cell_group_attendance a ON a.member_id = u.id AND a.meeting_id = ?
    WHERE cm.cell_group_id = ?
    ORDER BY u.lastname, u.firstname
");
$stmt->bind_param('ii', $meeting_id, $meeting['cell_group_id']); // note: meeting_id in left join param, then cell_group_id
// we need to pass meeting_id and group id in that order, but above SQL expects meeting_id then cell_group_id
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Mark Attendance</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { max-width:1000px; margin:30px auto; background:#fff; padding:16px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.table { width:100%; border-collapse:collapse; margin-top:10px; }
.table th, .table td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
.button { background:#0271c0; color:#fff; padding:8px 12px; border:none; border-radius:6px; cursor:pointer; }
.notice { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:6px; margin-bottom:10px; }
.readonly { opacity:.6; }
</style>
</head>
<body>
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
    <h2>Mark Attendance — <?=htmlspecialchars($meeting['title'])?> (<?=date('M d, Y', strtotime($meeting['meeting_date']))?>)</h2>
    <?php if ($success): ?><div class="notice"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <p><?=nl2br(htmlspecialchars($meeting['description']))?></p>

    <form method="POST">
        <table class="table">
            <thead><tr><th>#</th><th>User Code</th><th>Name</th><th>Status</th></tr></thead>
            <tbody>
            <?php $i=1; foreach($members as $m): ?>
                <tr class="<?= $view_only ? 'readonly' : '' ?>">
                    <td><?=$i++?></td>
                    <td><?=htmlspecialchars($m['user_code']??'')?></td>
                    <td><?=htmlspecialchars($m['firstname'].' '.$m['lastname'])?></td>
                    <td>
                        <?php if ($view_only): ?>
                            <?= $m['status'] ?: '<em>Not recorded</em>' ?>
                        <?php else: ?>
                            <label><input type="radio" name="attendance[<?=intval($m['id'])?>]" value="Present" <?= $m['status']=='Present' ? 'checked' : '' ?>> Present</label>
                            &nbsp;
                            <label><input type="radio" name="attendance[<?=intval($m['id'])?>]" value="Absent" <?= $m['status']=='Absent' ? 'checked' : '' ?>> Absent</label>
                            &nbsp;
                            <label><input type="radio" name="attendance[<?=intval($m['id'])?>]" value="Late" <?= $m['status']=='Late' ? 'checked' : '' ?>> Late</label>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$view_only): ?>
            <div style="margin-top:12px;">
                <button type="submit" class="button">Save Attendance</button>
                <a class="button" style="background:#6c757d" href="cell_group_leader.php">Back</a>
            </div>
        <?php else: ?>
            <div style="margin-top:12px;">
                <a class="button" href="cell_group_leader.php">Back</a>
            </div>
        <?php endif; ?>
    </form>
</div>
</div>
</body>
</html>
