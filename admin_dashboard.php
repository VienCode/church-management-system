<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

$successMessage = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

$rolesQuery = $mysqli->query("SELECT * FROM roles ORDER BY role_name ASC");
$roles = $rolesQuery->fetch_all(MYSQLI_ASSOC);

// Search + Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedRole = $_GET['role'] ?? 'all';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "1";
$params = [];
$types = '';

if ($selectedRole !== 'all') {
    $where .= " AND roles.role_name = ?";
    $params[] = $selectedRole;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND (users.firstname LIKE ? OR users.lastname LIKE ? OR users.email LIKE ? OR users.user_code LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= 'ssss';
}

// Count
$countSql = "SELECT COUNT(*) AS total FROM users JOIN roles ON users.role_id = roles.role_id WHERE $where";
$countStmt = $mysqli->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Data
$sql = "SELECT users.*, roles.role_name FROM users JOIN roles ON users.role_id = roles.role_id WHERE $where ORDER BY users.id DESC LIMIT $limit OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Manage Users</title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="content-header">
            <h1 class="page-title">👤 Manage Users</h1>
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                <div class="success-icon">✔</div>
                <div><?= $successMessage ?></div>
            </div>
        <?php endif; ?>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:15px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Search user..." style="padding:10px; border-radius:5px; border:1px solid #ddd; flex:1;">
            <select name="role" style="padding:10px; border-radius:5px;">
                <option value="all" <?= $selectedRole=='all'?'selected':'' ?>>All Roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['role_name'] ?>" <?= $selectedRole==$r['role_name']?'selected':'' ?>><?= ucfirst($r['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="primary-btn">Filter</button>
        </form>

        <a href="add_user_form.php" class="primary-btn">➕ Add New User</a>

        <div class="attendance-table" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="6" style="text-align:center;">No users found.</td></tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $role_id = $row['role_id'];
                        $user_id = $row['id'];
                        $email = $row['email'];
                        $member_count = 0;
                        $real_leader_id = null;

                        if ($role_id == 2) {
                            // Count members under leader
                            $leader_check = $mysqli->prepare("
                                SELECT l.leader_id, COUNT(m.member_id) AS members
                                FROM leaders l
                                LEFT JOIN cell_groups cg ON cg.leader_id = l.leader_id
                                LEFT JOIN cell_group_members m ON m.cell_group_id = cg.id
                                WHERE l.email = ?
                                GROUP BY l.leader_id
                            ");
                            $leader_check->bind_param("s", $email);
                            $leader_check->execute();
                            $data = $leader_check->get_result()->fetch_assoc();
                            $member_count = $data['members'] ?? 0;
                            $real_leader_id = $data['leader_id'] ?? null;
                            $leader_check->close();
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_code']) ?></td>
                            <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['contact']) ?></td>
                            <td><?= ucfirst($row['role_name']) ?></td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                    <a href="edit_user_form.php?id=<?= $row['id'] ?>" class="edit-btn">✏️ Edit</a>

                                    <form method="POST" action="delete_user.php" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="delete-btn" onclick="return confirm('Delete this user?')">🗑️</button>
                                    </form>

                                    <form method="POST" action="reset_password.php" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="secondary-btn" title="Reset password to default">🔒</button>
                                    </form>

                                    <?php if ($role_id == 2): ?>
                                        <?php if ($member_count > 0): ?>
                                            <div style="display:flex; flex-direction:column; gap:3px;">
                                                <span style="color:#dc2626; font-size:13px;">⚠️ <?= $member_count ?> members assigned</span>
                                                <?php if ($real_leader_id): ?>
                                                    <a href="reassign_members.php?leader_id=<?= $real_leader_id ?>" class="primary-btn" style="font-size:13px;">🔁 Reassign Members</a>
                                                <?php else: ?>
                                                    <button disabled class="secondary-btn" style="opacity:0.6;">❌ Leader Missing</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="demote_leader.php" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                                <button type="submit" class="delete-btn" onclick="return confirm('Demote this leader back to Member?')">⬇️ Demote</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="POST" action="assign_leader.php" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="secondary-btn">⭐ Make Leader</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-wrapper">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&role=<?= $selectedRole ?>&search=<?= urlencode($search) ?>" class="pagination-btn">⬅ Prev</a>
                <?php endif; ?>
                <span class="pagination-info">Page <span class="current-page"><?= $page ?></span> of <span class="total-pages"><?= $totalPages ?></span></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&role=<?= $selectedRole ?>&search=<?= urlencode($search) ?>" class="pagination-btn">Next ➡</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
