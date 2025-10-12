<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER, ROLE_EDITOR, ROLE_ACCOUNTANT, ROLE_ATTENDANCE_MARKER, ROLE_ADMIN]);

$user_id = $_SESSION['user_id'] ?? null;

// ✅ Find which cell group the member belongs to
$stmt = $mysqli->prepare("
    SELECT cg.id AS group_id, cg.group_name, l.leader_name
    FROM cell_group_members m
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE m.member_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not assigned to any Cell Group.</h2>";
    exit;
}

$group_id = $group['group_id'];

// ✅ Fetch meetings & attendance
$meetings_stmt = $mysqli->prepare("
    SELECT m.id, m.title, m.description, m.meeting_date, 
           COALESCE(a.status, 'Not Marked') AS status
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a ON m.id = a.meeting_id AND a.member_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY m.meeting_date DESC
");
$meetings_stmt->bind_param("ii", $user_id, $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
$meetings_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 My Cell Group | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.1); max-width:1000px; margin:30px auto; }
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; padding-bottom:5px; margin-bottom:15px; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.status-Present { color:#28a745; font-weight:600; }
.status-Absent { color:#dc3545; font-weight:600; }
.status-Late { color:#ffc107; font-weight:600; }
</style>
</head>

<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>
   <div class="content-area">
      <div class="cell-container">
         <h1>👥 My Cell Group</h1>
         <p>You belong to <strong><?= htmlspecialchars($group['group_name']) ?></strong> under Leader <strong><?= htmlspecialchars($group['leader_name']) ?></strong>.</p>

         <section>
            <h2 class="section-title">📅 Meetings & Attendance</h2>
            <?php if ($meetings->num_rows === 0): ?>
               <p>No meetings have been recorded yet.</p>
            <?php else: ?>
               <table>
                  <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Status</th></tr></thead>
                  <tbody>
                     <?php while ($meeting = $meetings->fetch_assoc()): ?>
                        <tr>
                           <td><?= htmlspecialchars(date('F j, Y', strtotime($meeting['meeting_date']))) ?></td>
                           <td><?= htmlspecialchars($meeting['title']) ?></td>
                           <td><?= htmlspecialchars($meeting['description']) ?></td>
                           <td class="status-<?= htmlspecialchars($meeting['status']) ?>">
                              <?= htmlspecialchars($meeting['status']) ?>
                           </td>
                        </tr>
                     <?php endwhile; ?>
                  </tbody>
               </table>
            <?php endif; ?>
         </section>
      </div>
   </div>
</div>
</body>
</html>
