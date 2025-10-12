<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = intval($_GET['group_id'] ?? 0);
if ($group_id <= 0) {
    die("<h2>❌ Invalid group ID.</h2>");
}

// Fetch group info
$group = $mysqli->query("
    SELECT cg.group_name, l.leader_name 
    FROM cell_groups cg 
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id 
    WHERE cg.id = $group_id
")->fetch_assoc();

if (!$group) {
    die("<h2>❌ Group not found.</h2>");
}

// Fetch members
$members = $mysqli->query("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email, u.contact
    FROM cell_group_members cgm
    JOIN users u ON u.user_code = cgm.user_code
    WHERE cgm.cell_group_id = $group_id
    ORDER BY u.lastname ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 <?= htmlspecialchars($group['group_name']) ?> Members</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.1); max-width: 1000px; margin: 30px auto; }
h1 { color:#0271c0; }
button { padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
.unassign { background:#dc3545; color:white; }
.unassign:hover { background:#b92c3a; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.back-btn { text-decoration:none; background:#ccc; padding:8px 14px; border-radius:8px; color:black; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>👥 <?= htmlspecialchars($group['group_name']) ?> Members</h1>
<p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?></p>

<a href="cell_groups_admin.php" class="back-btn">⬅ Back</a>

<?php if ($members->num_rows === 0): ?>
<p>No members in this group.</p>
<?php else: ?>
<table>
<thead><tr><th>#</th><th>User Code</th><th>Full Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
<tbody>
<?php $i=1; while($m = $members->fetch_assoc()): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($m['user_code']) ?></td>
<td><?= htmlspecialchars($m['fullname']) ?></td>
<td><?= htmlspecialchars($m['email']) ?></td>
<td><?= htmlspecialchars($m['contact']) ?></td>
<td>
    <form method="POST" action="unassign_member.php" style="display:inline;">
        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
        <button type="submit" class="unassign" onclick="return confirm('Unassign this member?')">🗑️ Unassign</button>
    </form>
</td>
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
