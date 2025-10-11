<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only admins can access

$msg = $_GET['msg'] ?? null;

// ✅ Handle Auto Sync Trigger
if (isset($_GET['sync']) && $_GET['sync'] === 'true') {
    $fixed = 0;

    // 1️⃣ Find all users who are leaders but not in leaders table
    $missing_leaders = $mysqli->query("
        SELECT id, firstname, lastname, email, contact 
        FROM users 
        WHERE role_id = 2 
        AND email NOT IN (SELECT email FROM leaders)
    ");

    while ($u = $missing_leaders->fetch_assoc()) {
        $fullname = trim($u['firstname'] . ' ' . $u['lastname']);
        $email = $u['email'];
        $contact = $u['contact'];

        // Add missing leader
        $insert = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $insert->bind_param("sss", $fullname, $contact, $email);
        $insert->execute();
        $leader_id = $insert->insert_id;
        $insert->close();

        // Create their cell group if missing
        $group_name = $fullname . "'s Cell Group";
        $mysqli->query("
            INSERT INTO cell_groups (group_name, leader_id, status)
            VALUES ('$group_name', $leader_id, 'active')
        ");
        $fixed++;
    }

    // 2️⃣ Reactivate leaders whose user role is leader but marked inactive
    $reactivate = $mysqli->query("
        UPDATE leaders l
        JOIN users u ON l.email = u.email
        SET l.status = 'active', l.deactivated_at = NULL
        WHERE u.role_id = 2 AND l.status = 'inactive'
    ");
    $fixed += $mysqli->affected_rows;

    // 3️⃣ Deactivate leaders whose user role is NOT leader
    $deactivate = $mysqli->query("
        UPDATE leaders l
        JOIN users u ON l.email = u.email
        SET l.status = 'inactive', l.deactivated_at = NOW()
        WHERE u.role_id != 2 AND l.status = 'active'
    ");
    $fixed += $mysqli->affected_rows;

    header("Location: leaders_management.php?msg=🔄 Auto Sync completed successfully. $fixed records checked and fixed.");
    exit();
}

// ✅ Handle permanent deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_leader"])) {
    $leader_id = intval($_POST["delete_leader"]);
    $mysqli->query("DELETE FROM leaders WHERE leader_id = $leader_id");
    $mysqli->query("DELETE FROM cell_groups WHERE leader_id = $leader_id");
    header("Location: leaders_management.php?msg=🗑️ Leader and their group permanently deleted.");
    exit;
}

// ✅ Fetch filters
$filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// ✅ Build query
$where = "1";
$params = [];
$types = "";

if ($filter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $filter;
    $types .= "s";
}
if (!empty($search)) {
    $where .= " AND (leader_name LIKE ? OR email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql = "SELECT leader_id, leader_name, contact, email, status, created_at, deactivated_at 
        FROM leaders WHERE $where ORDER BY status ASC, leader_name ASC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$leaders = $stmt->get_result();
$stmt->close();

// ✅ Get summary counts
$total = $mysqli->query("SELECT COUNT(*) AS c FROM leaders")->fetch_assoc()['c'];
$active = $mysqli->query("SELECT COUNT(*) AS c FROM leaders WHERE status = 'active'")->fetch_assoc()['c'];
$inactive = $mysqli->query("SELECT COUNT(*) AS c FROM leaders WHERE status = 'inactive'")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👑 Leaders Management | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.leader-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1150px;
    margin: 30px auto;
}
h1 { color: #0271c0; }
.filters {
    display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;
}
.filters input, .filters select {
    padding: 8px; border: 1px solid #ccc; border-radius: 6px;
}
.filters button {
    background: #0271c0; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer;
}
.filters button:hover { background: #02589b; }
.status-badge {
    padding: 5px 10px; border-radius: 8px; color: #fff; font-weight: bold;
}
.status-active { background: #28a745; }
.status-inactive { background: #dc3545; }
.summary { display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; }
.summary div {
    background: #f6f8fb; padding: 10px 20px; border-radius: 8px; font-weight: 600;
}
button.action-btn { padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.action-reactivate { background: #28a745; color: white; }
.action-delete { background: #dc3545; color: white; }
.action-reactivate:hover { background: #218838; }
.action-delete:hover { background: #c82333; }
.success { background:#e6ffed; color:#256029; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:600; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="leader-container">
            <h1>👑 Leaders Management</h1>
            <p>Manage, reactivate, or permanently remove church leaders.</p>

            <?php if ($msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="summary">
                <div>✅ Active: <?= $active ?></div>
                <div>❌ Inactive: <?= $inactive ?></div>
                <div>👥 Total Leaders: <?= $total ?></div>
            </div>

            <!-- Auto Sync -->
            <form method="GET" style="text-align:right; margin-bottom:15px;">
                <input type="hidden" name="sync" value="true">
                <button type="submit" class="action-btn action-reactivate" style="padding:10px 20px;">🔄 Run Auto Sync</button>
            </form>

            <!-- Filters -->
            <form method="GET" class="filters">
                <select name="status">
                    <option value="all" <?= $filter=='all'?'selected':'' ?>>All</option>
                    <option value="active" <?= $filter=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $filter=='inactive'?'selected':'' ?>>Inactive</option>
                </select>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Search leader...">
                <button type="submit">Filter</button>
                <a href="leaders_management.php" style="background:#ccc;padding:8px 12px;border-radius:6px;text-decoration:none;color:black;">Reset</a>
            </form>

            <!-- Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Leader Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Deactivated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($leaders->num_rows === 0): ?>
                        <tr><td colspan="8">No leaders found.</td></tr>
                    <?php else: $i=1; while ($l = $leaders->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($l['leader_name']) ?></td>
                            <td><?= htmlspecialchars($l['email']) ?></td>
                            <td><?= htmlspecialchars($l['contact'] ?? '-') ?></td>
                            <td>
                                <span class="status-badge <?= $l['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= ucfirst($l['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                            <td><?= $l['deactivated_at'] ? date('M j, Y', strtotime($l['deactivated_at'])) : '-' ?></td>
                            <td>
                                <?php if ($l['status'] == 'inactive'): ?>
                                    <form method="POST" action="reactivate_leader.php" style="display:inline;">
                                        <input type="hidden" name="leader_id" value="<?= $l['leader_id'] ?>">
                                        <button type="submit" class="action-btn action-reactivate" onclick="return confirm('Reactivate this leader?')">🔁 Reactivate</button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" action="leaders_management.php" style="display:inline;">
                                    <input type="hidden" name="delete_leader" value="<?= $l['leader_id'] ?>">
                                    <button type="submit" class="action-btn action-delete" onclick="return confirm('Permanently delete this leader and their cell group?')">🗑️ Delete</button>
                                </form>
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
