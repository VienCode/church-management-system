<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admins only
session_start();

// Handle success messages
$successMessage = '';
if (isset($_GET['msg'])) {
    $successMessage = htmlspecialchars($_GET['msg']);
}

// Fetch all roles for filters and dropdowns
$rolesQuery = $mysqli->query("SELECT * FROM roles ORDER BY role_name ASC");
$roles = $rolesQuery->fetch_all(MYSQLI_ASSOC);

// Search + Filter + Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedRole = isset($_GET['role']) ? $_GET['role'] : 'all';
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$where = "1";
$params = [];
$types = '';

if ($selectedRole !== 'all') {
    $where .= " AND roles.role_name = ?";
    $params[] = $selectedRole;
    $types .= 's';
}

if (!empty($search)) {
    $where .= " AND (users.firstname LIKE ? OR users.lastname LIKE ? OR users.email LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
    $types .= 'sss';
}

// Count total users
$countSql = "SELECT COUNT(*) AS total FROM users JOIN roles ON users.role_id = roles.role_id WHERE $where";
$countStmt = $mysqli->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch paginated users
$sql = "SELECT users.*, roles.role_name 
        FROM users 
        JOIN roles ON users.role_id = roles.role_id 
        WHERE $where 
        ORDER BY users.id DESC 
        LIMIT $limit OFFSET $offset";

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
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="logo-section">
            <div class="logo-placeholder"><span>⛪</span></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>

            <li class="nav-divider"></li>
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>

            <li class="nav-divider"></li>
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
            <li><a href="admin_dashboard.php" class="active"><span>⚙️</span> Manage Users</a></li>
            <li><a href="logout.php"><span>🚪</span> Logout</a></li>
        </ul>
    </nav>

    <!-- Content Area -->
    <div class="content-area">
        <div class="content-header">
            <div class="header-left">
                <h1 class="page-title">👤 Manage Users</h1>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                <div class="success-icon">✔</div>
                <div><?= $successMessage ?></div>
            </div>
        <?php endif; ?>

        <!-- 🕊️ Member Promotion Tool -->
        <div class="promotion-section" style="background:white; padding:20px; border-radius:10px; margin-bottom:20px;">
            <h2>🕊️ Member Promotion Tool</h2>
            <p>Check for non-members who have reached 10 attendances. They’ll automatically be promoted to Members.</p>

            <form id="promotionForm" action="php/promotions/promote_nonmembers.php" method="post">
                <button type="submit" class="primary-btn">Run Promotion Check</button>
            </form>

            <?php if (isset($_SESSION['promotion_result'])): ?>
                <div class="success-message" id="promotionMessage" style="margin-top:15px;">
                    <div class="success-icon">✔</div>
                    <div><?= $_SESSION['promotion_result'] ?></div>
                </div>
                <?php unset($_SESSION['promotion_result']); ?>
            <?php endif; ?>
        </div>
        <!-- End Promotion Tool -->

        <!-- Search & Filter -->
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

        <button class="primary-btn" onclick="openModal('addUserModal')">➕ Add User</button>

        <div class="attendance-table" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
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
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                                <td><?= ucfirst($row['role_name']) ?></td>
                                <td>
                                    <button class="edit-btn" onclick="openEditUserModal(<?= $row['id'] ?>)">✏️ Edit</button>
                                    <form method="POST" action="delete_user.php" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="delete-btn" onclick="return confirm('Delete this user?')">🗑️</button>
                                    </form>
                                    <form method="POST" action="reset_password.php" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="secondary-btn" onclick="return confirm('Reset this user password to default?')">🔒</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&role=<?= $selectedRole ?>&search=<?= urlencode($search) ?>" class="pagination-btn">⬅ Prev</a>
                <?php endif; ?>
                <span class="pagination-info">
                    Page <span class="current-page"><?= $page ?></span>
                    <span class="page-separator">of</span>
                    <span class="total-pages"><?= $totalPages ?></span>
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&role=<?= $selectedRole ?>&search=<?= urlencode($search) ?>" class="pagination-btn">Next ➡</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addUserModal')">&times;</span>
        <h2>Add New User</h2>
        <form action="add_user.php" method="POST">
            <label>First Name</label><input type="text" name="firstname" required>
            <label>Last Name</label><input type="text" name="lastname" required>
            <label>Email</label><input type="email" name="email" required>
            <label>Password</label><input type="password" name="password" required>
            <label>Role</label>
            <select name="role_id" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= ucfirst($r['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="save-btn">Save</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editUserModal')">&times;</span>
        <h2>Edit User</h2>
        <form action="edit_user.php" method="POST">
            <input type="hidden" name="user_id" id="edit_user_id">
            <label>First Name</label><input type="text" name="firstname" id="edit_firstname" required>
            <label>Last Name</label><input type="text" name="lastname" id="edit_lastname" required>
            <label>Email</label><input type="email" name="email" id="edit_email" required>
            <label>Role</label>
            <select name="role_id" id="edit_role_id" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= ucfirst($r['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="save-btn">Update</button>
        </form>
    </div>
</div>

<script src="script.js"></script>
<script>
// Fetch user data into edit modal
function openEditUserModal(userId) {
    fetch('fetch_user.php?id=' + userId)
        .then(res => res.json())
        .then(data => {
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_firstname').value = data.firstname;
            document.getElementById('edit_lastname').value = data.lastname;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_role_id').value = data.role_id;
            openModal('editUserModal');
        });
}

// Auto-hide messages, fade-out + auto-scroll + refresh
document.addEventListener('DOMContentLoaded', function() {
    const msg = document.querySelector('.success-message');
    const promotionMsg = document.getElementById('promotionMessage');

    // General fade out
    if (msg) {
        setTimeout(() => {
            msg.style.transition = 'all 0.8s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(() => msg.style.display = 'none', 800);
        }, 4000);
    }

    // Promotion success handling
    if (promotionMsg) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            promotionMsg.style.transition = 'all 1s ease';
            promotionMsg.style.opacity = '0';
            promotionMsg.style.transform = 'translateY(-15px)';
        }, 3000);
        setTimeout(() => {
            window.location.reload();
        }, 4500);
    }
});
</script>
</body>
</html>
