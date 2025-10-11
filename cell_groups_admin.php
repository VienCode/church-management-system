<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = $_GET['group_id'] ?? 0;

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

$members = $mysqli->query("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS member_name, u.email, r.role_name
    FROM cell_group_members cgm
    JOIN users u ON cgm.member_id = u.id
    JOIN roles r ON u.role_id = r.role_id
    WHERE cgm.cell_group_id = $group_id
    ORDER BY u.lastname ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Details | <?= htmlspecialchars($group['group_name']) ?></title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff; padding:24px; border-radius:12px; max-width:1000px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08); }
h1 { color:#0271c0; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>👥 <?= htmlspecialchars($group['group_name']) ?> - Members</h1>
            <p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?><br>
               <strong>Email:</strong> <?= htmlspecialchars($group['leader_email']) ?><br>
               <strong>Contact:</strong> <?= htmlspecialchars($group['leader_contact']) ?></p>

            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members->num_rows === 0): ?>
                        <tr><td colspan="4">No members found in this group.</td></tr>
                    <?php else: while ($m = $members->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['user_code']) ?></td>
                            <td><?= htmlspecialchars($m['member_name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['role_name']) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <a href="cell_groups_admin.php" class="save-btn" style="margin-top:20px; display:inline-block;">⬅ Back</a>
        </div>
    </div>
</div>
</body>
</html>
