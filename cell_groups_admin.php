<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

// --- FILTERS ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// --- BASE QUERY ---
$sql = "
SELECT 
    cg.id AS group_id,
    cg.group_name,
    cg.status AS group_status,
    l.leader_name,
    l.email AS leader_email,
    COUNT(cgm.user_code) AS member_count
FROM cell_groups cg
LEFT JOIN leaders l ON cg.leader_id = l.leader_id
LEFT JOIN cell_group_members cgm ON cgm.cell_group_id = cg.id
WHERE 1
";

// --- APPLY FILTERS ---
if ($search) {
    $search = "%$search%";
    $stmt = $mysqli->prepare($sql . " AND (cg.group_name LIKE ? OR l.leader_name LIKE ?) GROUP BY cg.id ORDER BY cg.group_name ASC");
    $stmt->bind_param("ss", $search, $search);
} elseif ($status_filter !== 'all') {
    $stmt = $mysqli->prepare($sql . " AND cg.status = ? GROUP BY cg.id ORDER BY cg.group_name ASC");
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $mysqli->prepare($sql . " GROUP BY cg.id ORDER BY cg.group_name ASC");
}

$stmt->execute();
$result = $stmt->get_result();

// --- SUMMARY STATS ---
$summary = $mysqli->query("
SELECT 
    SUM(status = 'active') AS active_groups,
    SUM(status = 'inactive') AS inactive_groups,
    COUNT(*) AS total_groups
FROM cell_groups
")->fetch_assoc();

// --- FETCH LEADERS FOR REASSIGNING ---
$leaders = $mysqli->query("SELECT leader_id, leader_name FROM leaders WHERE status='active' ORDER BY leader_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📜 Cell Group Management | Admin</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1200px;
    margin: 30px auto;
}
.search-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; }
.search-bar input, .search-bar select {
    padding: 8px; border-radius: 6px; border: 1px solid #ccc;
}
.search-bar button {
    background: #0271c0; color: #fff; border: none; border-radius: 8px;
    padding: 8px 14px; cursor: pointer; font-weight: 600;
}
.search-bar button:hover { background: #02589b; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { padding: 10px 12px; border-bottom: 1px solid #e6e6e6; text-align: center; }
th { background: #0271c0; color: white; }
.action-btn {
    border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600;
}
.edit { background: #ffc107; color: black; }
.deactivate { background: #dc3545; color: white; }
.reactivate { background: #28a745; color: white; }
.view { background: #17a2b8; color: white; }
.transfer { background: #007bff; color: white; }
.summary-cards { display: flex; gap: 10px; margin-top: 15px; }
.summary-card { background: #f8f9fa; padding: 15px; border-radius: 8px; flex: 1; text-align: center; }
.members-table { margin-top: 10px; border: 1px solid #ddd; border-radius: 8px; padding: 10px; background: #fafafa; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>📜 Cell Group Management</h1>

<form class="search-bar" method="GET">
    <input type="text" name="search" placeholder="🔍 Search by group or leader..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    <select name="status">
        <option value="all" <?= ($status_filter=='all')?'selected':'' ?>>All Statuses</option>
        <option value="active" <?= ($status_filter=='active')?'selected':'' ?>>Active</option>
        <option value="inactive" <?= ($status_filter=='inactive')?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit">Filter</button>
    <a href="cell_groups_admin.php" style="text-decoration:none;background:#ccc;padding:8px 14px;border-radius:8px;color:black;">Reset</a>
</form>

<div class="summary-cards">
    <div class="summary-card">✅ Active Groups: <strong><?= $summary['active_groups'] ?></strong></div>
    <div class="summary-card">🛑 Inactive Groups: <strong><?= $summary['inactive_groups'] ?></strong></div>
    <div class="summary-card">📊 Total Groups: <strong><?= $summary['total_groups'] ?></strong></div>
</div>

<table>
<thead>
<tr>
<th>#</th>
<th>Group Name</th>
<th>Leader</th>
<th>Email</th>
<th>Status</th>
<th>Members</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
if ($result->num_rows === 0):
    echo "<tr><td colspan='7'>No results found.</td></tr>";
else:
    $i=1;
    while ($row = $result->fetch_assoc()):
?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['group_name']) ?></td>
<td><?= htmlspecialchars($row['leader_name']) ?></td>
<td><?= htmlspecialchars($row['leader_email']) ?></td>
<td><?= ucfirst($row['group_status']) ?></td>
<td><?= $row['member_count'] ?></td>
<td>
    <a href="view_group_members.php?group_id=<?= $row['group_id'] ?>" class="action-btn view">👥 View</a>
    <?php if ($row['group_status'] === 'active'): ?>
        <a href="deactivate_group.php?id=<?= $row['group_id'] ?>" class="action-btn deactivate" onclick="return confirm('Deactivate this group?')">🛑 Deactivate</a>
    <?php else: ?>
        <a href="reactivate_group.php?id=<?= $row['group_id'] ?>" class="action-btn reactivate" onclick="return confirm('Reactivate this group?')">✅ Reactivate</a>
    <?php endif; ?>
    <a href="transfer_members.php?group_id=<?= $row['group_id'] ?>" class="action-btn transfer">🔄 Transfer</a>
</td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</body>
</html>
