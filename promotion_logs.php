<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$result = $mysqli->query("
    SELECT p.*, l.leader_name, a.firstname AS admin_first, a.lastname AS admin_last
    FROM promotion_logs p
    LEFT JOIN leaders l ON p.assigned_leader_id = l.leader_id
    LEFT JOIN users a ON p.promoted_by_admin_id = a.id
    ORDER BY p.promoted_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Promotion Logs</title>
<link rel="stylesheet" href="styles_system.css">
<style>
    .logs-table { background:white; padding:25px; border-radius:10px; margin-top:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { border-bottom:1px solid #ddd; padding:10px; text-align:center; }
    th { background:#007bff; color:white; }
</style>
</head>
<body>
<div class="main-layout">
    <nav class="sidebar">
        <div class="logo-section">
            <div class="logo-placeholder"><span>⛪</span></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="promotion_page.php"><span>🕊️</span> Promotion Panel</a></li>
            <li><a href="promotion_logs.php" class="active"><span>📜</span> Promotion Logs</a></li>
        </ul>
    </nav>

    <div class="content-area">
        <h1>📜 Promotion Logs</h1>
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Leader</th>
                        <th>Promoted By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_code']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_name']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_user_email']) ?></td>
                            <td><?= htmlspecialchars($row['leader_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['admin_first'] . ' ' . $row['admin_last']) ?></td>
                            <td><?= htmlspecialchars($row['promoted_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
