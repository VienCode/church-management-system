<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only admins can access this page

// Handle filters
$search = $_GET['search'] ?? '';
$leader_filter = $_GET['leader_id'] ?? 'all';

// Fetch all leaders for filter dropdown
$leaders = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");

// Base query
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
WHERE 1
";

// Apply filters
$params = [];
$types = "";

if ($leader_filter !== 'all') {
    $sql .= " AND l.leader_id = ? ";
    $params[] = $leader_filter;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (
        l.leader_name LIKE ? OR 
        u.firstname LIKE ? OR 
        u.lastname LIKE ? OR 
        u.user_code LIKE ?
    )";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}

$sql .= " ORDER BY l.leader_name ASC, u.lastname ASC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Management | Admin</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container {
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    max-width: 1150px;
    margin: 30px auto;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}
h1 { color:#0271c0; }
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
}
.filter-bar input, .filter-bar select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
.filter-bar button {
    background: #0271c0;
    color: #fff;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
}
.filter-bar button:hover {
    background: #02589b;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #e6e6e6;
    text-align: center;
}
th {
    background: #0271c0;
    color: white;
}
tr:nth-child(even) {
    background: #f8f9fb;
}
.summary {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
}
.summary div {
    background: #f4f7fa;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="cell-container">
            <h1>🧩 Cell Group Management (Admin View)</h1>
            <p>View all cell groups, their leaders, and members.</p>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <select name="leader_id">
                    <option value="all" <?= $leader_filter == 'all' ? 'selected' : '' ?>>All Leaders</option>
                    <?php while ($leader = $leaders->fetch_assoc()): ?>
                        <option value="<?= $leader['leader_id'] ?>" <?= $leader_filter == $leader['leader_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($leader['leader_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="search" placeholder="🔍 Search by name, code..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Filter</button>
                <a href="cell_groups_admin.php" style="padding:8px 14px; background:#ccc; border-radius:8px; text-decoration:none; color:black;">Reset</a>
            </form>

            <!-- Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Leader</th>
                        <th>Leader Contact</th>
                        <th>Leader Email</th>
                        <th>Member Code</th>
                        <th>Member Name</th>
                        <th>Member Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    if ($result->num_rows === 0): ?>
                        <tr><td colspan="8">No records found.</td></tr>
                    <?php else: 
                        while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['leader_name']) ?></td>
                                <td><?= htmlspecialchars($row['leader_contact']) ?></td>
                                <td><?= htmlspecialchars($row['leader_email']) ?></td>
                                <td><?= htmlspecialchars($row['user_code'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['member_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['member_email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['role_id'] ?? '-') ?></td>
                            </tr>
                        <?php endwhile;
                    endif; ?>
                </tbody>
            </table>

            <div class="summary">
                <div>👑 Total Leaders: <?= $leaders->num_rows ?></div>
                <div>👥 Total Members Displayed: <?= $result->num_rows ?></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
