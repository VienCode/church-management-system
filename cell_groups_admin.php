<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$search = $_GET['search'] ?? '';
$leader_filter = $_GET['leader_id'] ?? 'all';

// Fetch all leaders
$leaders = $mysqli->query("SELECT leader_id, leader_name, email FROM leaders ORDER BY leader_name ASC");

$sql = "
SELECT 
    l.leader_id,
    l.leader_name,
    l.email AS leader_email,
    l.contact AS leader_contact,
    u.user_code,
    CONCAT(u.firstname, ' ', u.lastname) AS member_name,
    u.email AS member_email,
    u.role_id,
    u.is_cell_member
FROM leaders l
LEFT JOIN users u 
    ON u.leader_id = l.leader_id
WHERE (u.role_id = 3 OR u.is_cell_member = 1)
ORDER BY l.leader_name ASC, u.lastname ASC
";


// Filters
$params = [];
$types = "";

if ($leader_filter !== 'all') {
    $sql .= " AND l.leader_id = ? ";
    $params[] = $leader_filter;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (cg.group_name LIKE ? OR l.leader_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$sql .= " GROUP BY cg.id ORDER BY cg.group_name ASC";
$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$groups = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Management | Admin</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.1); max-width:1150px; margin:30px auto; }
h1 { color:#0271c0; }
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
.filter-bar input, .filter-bar select { padding:8px; border-radius:6px; border:1px solid #ccc; }
.filter-bar button { background:#0271c0; color:#fff; border:none; border-radius:8px; padding:8px 14px; cursor:pointer; }
.filter-bar button:hover { background:#02589b; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="cell-container">
            <h1>🧩 Cell Group Management (Admin View)</h1>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <select name="leader_id">
                    <option value="all" <?= $leader_filter=='all'?'selected':'' ?>>All Leaders</option>
                    <?php while ($l = $leaders->fetch_assoc()): ?>
                        <option value="<?= $l['leader_id'] ?>" <?= $leader_filter==$l['leader_id']?'selected':'' ?>>
                            <?= htmlspecialchars($l['leader_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="search" placeholder="Search by group or leader..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Filter</button>
                <a href="cell_groups_admin.php" style="padding:8px 14px; background:#ccc; border-radius:8px; text-decoration:none; color:black;">Reset</a>
            </form>

            <!-- Groups Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Group Name</th>
                        <th>Leader</th>
                        <th>Leader Email</th>
                        <th>Contact</th>
                        <th>Members</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($groups->num_rows === 0):
                        echo "<tr><td colspan='6'>No cell groups found.</td></tr>";
                    else:
                        $i = 1;
                        while ($g = $groups->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($g['group_name']) ?></td>
                            <td><?= htmlspecialchars($g['leader_name']) ?></td>
                            <td><?= htmlspecialchars($g['leader_email']) ?></td>
                            <td><?= htmlspecialchars($g['leader_contact']) ?></td>
                            <td><?= htmlspecialchars($row['role_id'] == 3 ? 'Member' : 'Staff / ' . ucfirst($row['role_id'])) ?></td>
                            <td><?= $row['is_cell_member'] ? '✅ Yes' : '❌ No' ?></td>
                            <td><?= $g['member_count'] ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
