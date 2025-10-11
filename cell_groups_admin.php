<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$sql = "
SELECT 
    cg.id AS group_id,
    cg.group_name,
    cg.status AS group_status,
    l.leader_name,
    COUNT(cgm.member_id) AS member_count
FROM cell_groups cg
LEFT JOIN leaders l ON cg.leader_id = l.leader_id
LEFT JOIN cell_group_members cgm ON cgm.cell_group_id = cg.id
GROUP BY cg.id
ORDER BY cg.group_name ASC
";

$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📜 Cell Group Management</title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<h1>📜 Cell Group Management</h1>
<table>
<tr><th>Group</th><th>Leader</th><th>Status</th><th>Members</th></tr>
<?php while($r=$result->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['group_name']) ?></td>
<td><?= htmlspecialchars($r['leader_name']) ?></td>
<td><?= htmlspecialchars($r['group_status']) ?></td>
<td><?= $r['member_count'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div></div></body></html>
