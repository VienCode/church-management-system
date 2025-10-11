<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER]);

$user_id = $_SESSION['user_id'] ?? null;

// Find their cell group
$stmt = $mysqli->prepare("
    SELECT cg.id, cg.group_name, l.leader_name
    FROM cell_group_members cgm
    JOIN cell_groups cg ON cgm.cell_group_id = cg.id
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE cgm.member_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<div style='background:#ffe6e6;padding:12px;border-radius:6px;'>❌ You are not assigned to any cell group yet.</div>";
    exit();
}

// Fetch meetings
$meetings = $mysqli->prepare("
    SELECT m.id, m.title, m.description, m.meeting_date,
           a.status
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a 
        ON m.id = a.meeting_id AND a.member_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY m.meeting_date DESC
");
$meetings->bind_param("ii", $user_id, $group['id']);
$meetings->execute();
$meetings_result = $meetings->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Cell Group</title>
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
            <h2>📖 <?= htmlspecialchars($group['group_name']) ?></h2>
            <p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?></p>

            <h3>📅 Meetings & Attendance</h3>
            <table>
                <thead><tr><th>Title</th><th>Description</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if($meetings_result->num_rows == 0): ?>
                        <tr><td colspan="4">No meetings yet.</td></tr>
                    <?php else: while($m = $meetings_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['title']) ?></td>
                            <td><?= htmlspecialchars($m['description']) ?></td>
                            <td><?= htmlspecialchars($m['meeting_date']) ?></td>
                            <td><?= htmlspecialchars($m['status'] ?? 'Not Marked') ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
