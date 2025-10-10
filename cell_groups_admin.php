<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$search_name = $_GET['search_name'] ?? '';
$where = "";
$params = [];
$types = "";

if (!empty($search_name)) {
    $where = "WHERE l.leader_name LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}

// Query all cell groups with summary stats
$sql = "
    SELECT 
        l.leader_id,
        l.leader_name,
        l.email AS leader_email,
        l.contact AS leader_contact,
        u.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS member_name,
        u.email AS member_email,
        u.role_id
    FROM leaders l
    LEFT JOIN users u 
        ON u.leader_id = l.leader_id
    ORDER BY l.leader_name ASC, u.lastname ASC
";

if (isset($_GET['leader_id']) && $_GET['leader_id'] !== 'all') {
    $sql .= " WHERE l.leader_id = " . intval($_GET['leader_id']);
}



$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$groups = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Groups Overview | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-admin-container {
    background:#fff; padding:24px; border-radius:12px; 
    max-width:1100px; margin:30px auto; 
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}
.search-bar { display:flex; gap:10px; margin-bottom:15px; align-items:center; }
.search-bar input { padding:8px; border-radius:6px; border:1px solid #ccc; flex:1; }
.search-bar button { background:#0271c0; color:#fff; border:none; padding:8px 14px; border-radius:8px; cursor:pointer; }
.search-bar button:hover { background:#02589b; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:#fff; }
.action-btn { background:#28a745; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; }
.action-btn:hover { background:#1e7e34; }
.no-data { text-align:center; padding:20px; color:#777; font-weight:600; }
</style>
</head>
<body>

<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-area">
    <div class="cell-admin-container">
        <h1>👥 Cell Group Overview (Admin Panel)</h1>
        <p>Monitor all cell group leaders, their members, and activities.</p>

        <!-- Search -->
        <form method="GET" class="search-bar">
            <input type="text" name="search_name" placeholder="Search leader name..." value="<?= htmlspecialchars($search_name) ?>">
            <button type="submit">🔍 Search</button>
        </form>

        <!-- Cell Group Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Leader Name</th>
                    <th>Email</th>
                    <th>Members</th>
                    <th>Activities</th>
                    <th>Last Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                    <tr><td colspan="7" class="no-data">No cell groups found.</td></tr>
                <?php else: $i=1; foreach ($groups as $g): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($g['leader_name']) ?></td>
                        <td><?= htmlspecialchars($g['leader_email'] ?? '-') ?></td>
                        <td><?= $g['member_count'] ?></td>
                        <td><?= $g['total_activities'] ?></td>
                        <td><?= $g['last_activity'] ? date("F j, Y", strtotime($g['last_activity'])) : '-' ?></td>
                        <td>
                            <form method="GET" action="cell_group_records.php" style="display:inline;">
                                <input type="hidden" name="leader_id" value="<?= $g['leader_id'] ?>">
                                <button type="submit" class="action-btn">📄 View Records</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</body>
</html>
