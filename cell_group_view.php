<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = $_GET['group_id'] ?? null;
if (!$group_id) {
    header("Location: cell_groups_admin.php?msg=❌ No group selected");
    exit();
}

// Fetch group & leader info safely
$stmt = $mysqli->prepare("
    SELECT cg.group_name, l.leader_name, l.email AS leader_email, l.contact AS leader_contact
    FROM cell_groups cg
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE cg.id = ?
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<div style='background:#ffe6e6;padding:12px;border-radius:6px;'>❌ Group not found.</div>";
    exit();
}

// Fetch members
$members = $mysqli->prepare("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS member_name, u.email, u.contact
    FROM cell_group_members cgm
    JOIN users u ON cgm.member_id = u.id
    WHERE cgm.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members->bind_param("i", $group_id);
$members->execute();
$members_result = $members->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Cell Group</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff;padding:20px;border-radius:10px;margin:30px auto;max-width:1100px;box-shadow:0 2px 10px rgba(0,0,0,.08); }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h2>📋 <?= htmlspecialchars($group['group_name']) ?></h2>
            <p>
                <strong>Leader:</strong> <?= htmlspecialchars($group['leader_name'] ?? 'None') ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($group['leader_email'] ?? '-') ?><br>
                <strong>Contact:</strong> <?= htmlspecialchars($group['leader_contact'] ?? '-') ?>
            </p>
            <h3>👥 Members</h3>
            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members_result->num_rows === 0): ?>
                        <tr><td colspan="4">No members in this group.</td></tr>
                    <?php else: while($m = $members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['user_code']) ?></td>
                            <td><?= htmlspecialchars($m['member_name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['contact']) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
