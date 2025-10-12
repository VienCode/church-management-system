<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER]);

$group_id = intval($_GET['group_id'] ?? 0);
if ($group_id <= 0) {
    die("<h2>❌ Invalid or missing group ID.</h2>");
}

// 🔍 Fetch group info
$group_stmt = $mysqli->prepare("
    SELECT cg.group_name, cg.leader_id, l.leader_name, l.email AS leader_email
    FROM cell_groups cg
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE cg.id = ?
");
$group_stmt->bind_param("i", $group_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    die("<h2>❌ Group not found or has been deleted.</h2>");
}

$leader_id = $group['leader_id'];
$leader_email = $group['leader_email'];
$group_name = $group['group_name'];

// 🧩 Auto-sync: Ensure all users with this leader_id exist in cell_group_members
$users_stmt = $mysqli->prepare("
    SELECT user_code FROM users 
    WHERE leader_id = ? AND role_id = 3
");
$users_stmt->bind_param("i", $leader_id);
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users_stmt->close();

while ($u = $users_result->fetch_assoc()) {
    $user_code = $u['user_code'];

    $check_stmt = $mysqli->prepare("
        SELECT id FROM cell_group_members 
        WHERE cell_group_id = ? AND user_code = ?
    ");
    $check_stmt->bind_param("is", $group_id, $user_code);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if (!$exists) {
        $insert_stmt = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, user_code)
            VALUES (?, ?)
        ");
        $insert_stmt->bind_param("is", $group_id, $user_code);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

// 🧩 Fetch all members (excluding the leader herself)
$members_stmt = $mysqli->prepare("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, 
           u.email, u.contact
    FROM cell_group_members m
    JOIN users u ON m.user_code = u.user_code
    WHERE m.cell_group_id = ? AND u.email != ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("is", $group_id, $leader_email);
$members_stmt->execute();
$members = $members_stmt->get_result();
$members_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 <?= htmlspecialchars($group_name) ?> | Members</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.1); max-width:1000px; margin:30px auto; }
h1 { color:#0271c0; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
button { padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
.unassign { background:#dc3545; color:white; }
.unassign:hover { background:#b92c3a; }
.back-btn { text-decoration:none; background:#ccc; padding:8px 14px; border-radius:8px; color:black; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>👥 <?= htmlspecialchars($group_name) ?> Members</h1>
<p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?></p>

<a href="cell_groups_admin.php" class="back-btn">⬅ Back</a>

<?php if ($members->num_rows === 0): ?>
    <p>No members currently assigned to this group.</p>
<?php else: ?>
<table>
<thead>
<tr>
    <th>#</th>
    <th>User Code</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Contact</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php $i=1; while ($m = $members->fetch_assoc()): ?>
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
