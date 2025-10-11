<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER, ROLE_EDITOR, ROLE_ACCOUNTANT, ROLE_ATTENDANCE_MARKER]);

$user_id = $_SESSION['user_id'];

// ✅ Fetch assigned cell group
$stmt = $mysqli->prepare("
    SELECT g.id AS group_id, g.group_name, l.leader_name
    FROM cell_group_members m
    JOIN cell_groups g ON m.cell_group_id = g.id
    JOIN leaders l ON g.leader_id = l.leader_id
    WHERE m.member_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not assigned to any Cell Group.</h2>";
    exit;
}

// ✅ Fetch meetings
$meetings = $mysqli->query("
    SELECT id, title, description, meeting_date
    FROM cell_group_meetings
    WHERE cell_group_id = {$group['group_id']}
    ORDER BY meeting_date DESC
");

// ✅ Fetch attendance records for this member
$attendance = [];
$res = $mysqli->query("
    SELECT meeting_id, status FROM cell_group_attendance WHERE member_id = $user_id
");
while ($row = $res->fetch_assoc()) $attendance[$row['meeting_id']] = $row['status'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 My Cell Group | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:25px; border-radius:12px; max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.1); }
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; margin-bottom:15px; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th,td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>
   <div class="content-area">
      <div class="cell-container">
         <h1>👥 My Cell Group</h1>
         <p>Leader: <strong><?= htmlspecialchars($group['leader_name']) ?></strong><br>
         Group: <strong><?= htmlspecialchars($group['group_name']) ?></strong></p>

         <h2 class="section-title">📅 Meetings</h2>
         <?php if ($meetings->num_rows === 0): ?>
            <p>No meetings found.</p>
         <?php else: ?>
         <table>
            <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Status</th></tr></thead>
            <tbody>
               <?php while($m = $meetings->fetch_assoc()): ?>
               <tr>
                  <td><?= date('F j, Y', strtotime($m['meeting_date'])) ?></td>
                  <td><?= htmlspecialchars($m['title']) ?></td>
                  <td><?= htmlspecialchars($m['description']) ?></td>
                  <td><?= $attendance[$m['id']] ?? 'Not Marked' ?></td>
               </tr>
               <?php endwhile; ?>
            </tbody>
         </table>
         <?php endif; ?>
      </div>
   </div>
</div>
</body>
</html>
