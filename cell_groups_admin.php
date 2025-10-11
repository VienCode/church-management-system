<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

// Fetch all cell groups with leaders
$sql = "
    SELECT cg.id AS group_id, cg.group_name, 
           l.leader_name, l.email AS leader_email, l.contact AS leader_contact,
           COUNT(cgm.member_id) AS total_members
    FROM cell_groups cg
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    LEFT JOIN cell_group_members cgm ON cg.id = cgm.cell_group_id
    GROUP BY cg.id
    ORDER BY cg.group_name ASC
";
$result = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Management | Admin</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:24px; border-radius:12px; margin:30px auto; max-width:1150px; box-shadow:0 2px 10px rgba(0,0,0,.08); }
h1 { color:#0271c0; margin-bottom:10px; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.view-btn { background:#0271c0; color:white; padding:6px 12px; border:none; border-radius:6px; text-decoration:none; }
.view-btn:hover { background:#02589b; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="cell-container">
            <h1>🧩 Cell Group Management</h1>
            <p>View all cell groups, their leaders, and number of members.</p>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Group Name</th>
                        <th>Leader</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Total Members</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7">No cell groups found.</td></tr>
                    <?php else: $i=1; while($row=$result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['group_name']) ?></td>
                            <td><?= htmlspecialchars($row['leader_name'] ?? 'Unassigned') ?></td>
                            <td><?= htmlspecialchars($row['leader_email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['leader_contact'] ?? '-') ?></td>
                            <td><?= $row['total_members'] ?></td>
                            <td><a href="cell_group_view.php?group_id=<?= $row['group_id'] ?>" class="view-btn">View</a></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
