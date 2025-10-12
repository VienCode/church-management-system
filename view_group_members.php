<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = intval($_GET['group_id'] ?? 0);
if ($group_id <= 0) {
    die("<h2>❌ Invalid group ID.</h2>");
}

// ✅ Fetch group info
$group = $mysqli->query("
    SELECT cg.group_name, l.leader_name, l.leader_id, l.email AS leader_email 
    FROM cell_groups cg 
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id 
    WHERE cg.id = $group_id
")->fetch_assoc();

if (!$group) {
    die("<h2>❌ Group not found.</h2>");
}

// ✅ Fetch members in this group
$members = $mysqli->query("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email, u.contact
    FROM cell_group_members cgm
    JOIN users u ON u.user_code = cgm.user_code
    WHERE cgm.cell_group_id = $group_id
    ORDER BY u.lastname ASC
");

// ✅ Fetch all other active leaders for transfers
$leaders = $mysqli->query("
    SELECT leader_id, leader_name 
    FROM leaders 
    WHERE status = 'active' 
    ORDER BY leader_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 <?= htmlspecialchars($group['group_name']) ?> Members</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.1);
    max-width: 1100px;
    margin: 30px auto;
}
h1 { color:#0271c0; }
button {
    padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600;
}
.unassign { background:#dc3545; color:white; }
.unassign:hover { background:#b92c3a; }
.transfer { background:#007bff; color:white; }
.transfer:hover { background:#0056b3; }
.history { background:#28a745; color:white; }
.history:hover { background:#1f7a35; }
.back-btn {
    text-decoration:none; background:#ccc; padding:8px 14px;
    border-radius:8px; color:black; font-weight:600;
}
select {
    padding:6px; border:1px solid #ccc; border-radius:6px;
}
table {
    width:100%; border-collapse:collapse; margin-top:15px;
}
th, td {
    padding:10px; border-bottom:1px solid #e6e6e6; text-align:center;
}
th { background:#0271c0; color:white; }
.action-form { display:inline; }
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>👥 <?= htmlspecialchars($group['group_name']) ?> Members</h1>
<p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?> | 
<strong>Email:</strong> <?= htmlspecialchars($group['leader_email']) ?></p>

<a href="cell_groups_admin.php" class="back-btn">⬅ Back</a>

<?php if ($members->num_rows === 0): ?>
    <p>No members in this group yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>User Code</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Transfer</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; while($m = $members->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($m['user_code']) ?></td>
                <td><?= htmlspecialchars($m['fullname']) ?></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
                <td><?= htmlspecialchars($m['contact']) ?></td>
                <td>
                    <form method="POST" action="transfer_member_action.php" class="action-form">
                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                        <select name="new_leader_id" required>
                            <option value="" disabled selected>Choose Leader</option>
                            <?php 
                            $leaders->data_seek(0);
                            while ($l = $leaders->fetch_assoc()): 
                                if ($l['leader_id'] != $group['leader_id']): ?>
                                    <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
                                <?php endif; 
                            endwhile; ?>
                        </select>
                        <button type="submit" class="transfer">🔄 Transfer</button>
                    </form>
                </td>
                <td>
                    <form method="POST" action="unassign_member.php" class="action-form" onsubmit="return confirm('Unassign this member from the group?')">
                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="unassign">🗑️ Unassign</button>
                    </form>
                    <a href="member_attendance.php?user_code=<?= urlencode($m['user_code']) ?>" class="history">📊 Attendance</a>
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
