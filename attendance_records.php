<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER]);

// === FILTER LOGIC ===
$search_name = $_GET['search_name'] ?? '';
$search_code = $_GET['search_code'] ?? '';
$search_role = $_GET['role'] ?? 'all';
$search_status = $_GET['status'] ?? 'all';
$search_date = $_GET['date'] ?? '';
$search_start = $_GET['start_date'] ?? '';
$search_end = $_GET['end_date'] ?? '';

$where = "1";
$params = [];
$types = "";

// Build filters dynamically
if (!empty($search_name)) {
    $where .= " AND CONCAT(u.firstname, ' ', u.lastname) LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}
if (!empty($search_code)) {
    $where .= " AND u.user_code LIKE ?";
    $params[] = "%$search_code%";
    $types .= "s";
}
if ($search_role !== 'all') {
    $where .= " AND r.role_id = ?";
    $params[] = $search_role;
    $types .= "i";
}
if ($search_status !== 'all') {
    $where .= " AND a.status = ?";
    $params[] = $search_status;
    $types .= "s";
}
if (!empty($search_date)) {
    $where .= " AND a.attendance_date = ?";
    $params[] = $search_date;
    $types .= "s";
}
if (!empty($search_start) && !empty($search_end)) {
    $where .= " AND a.attendance_date BETWEEN ? AND ?";
    $params[] = $search_start;
    $params[] = $search_end;
    $types .= "ss";
}

// === PAGINATION ===
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Count total records
$count_sql = "
    SELECT COUNT(*) as total
    FROM attendance a
    JOIN users u ON a.user_code = u.user_code
    JOIN roles r ON u.role_id = r.role_id
    WHERE $where
";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// === FETCH FILTERED RECORDS ===
$sql = "
    SELECT 
        a.attendance_date,
        a.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        r.role_name,
        a.status,
        a.time_in,
        a.recorded_by
    FROM attendance a
    JOIN users u ON a.user_code = u.user_code
    JOIN roles r ON u.role_id = r.role_id
    WHERE $where
    ORDER BY a.attendance_date DESC, u.lastname ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch roles for filter dropdown
$roles = $mysqli->query("SELECT role_id, role_name FROM roles WHERE role_id != 4 ORDER BY role_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Records | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.records-container { background:#fff; padding:24px; border-radius:12px; max-width:1200px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08); }
.filter-form { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; align-items:center; }
.filter-form input, .filter-form select { padding:8px; border-radius:6px; border:1px solid #ccc; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px; text-align:center; border-bottom:1px solid #e6e6e6; }
th { background:#0271c0; color:#fff; }
.export-btn { background:#28a745; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
.export-btn:hover { background:#218838; }
.pagination { margin-top:20px; text-align:center; }
.pagination a { background:#0271c0; color:white; padding:6px 10px; border-radius:6px; text-decoration:none; margin:0 2px; }
.pagination a:hover { background:#02589b; }
</style>
</head>

<body>
<div class="main-layout">
   <nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder"><span><img src="images/ucf.png" alt="ucf_logo"></span></div>
        <div class="logo">Unity Christian Fellowship</div>
    </div>
    <ul class="nav-menu">
        <!-- GENERAL PAGES -->
        <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>

        <?php if (can_access([ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="attendance_records.php" class="active"><span>📋</span> Attendance Records</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_MEMBER, ROLE_LEADER])): ?>
            <li><a href="members.php"><span>👤</span> Members</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
        <!-- Divider -->
        <li class="nav-divider"></li>
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ADMIN])): ?>
        <li class="nav-divider"></li>
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
            <li><a href="admin_dashboard.php"><span>⚙️</span> Manage Users</a></li>
            <li><a href="promotion_page.php"><span>🕊️</span> Promotion Panel</a></li>
            <li><a href="promotion_logs.php"><span>🕊️</span> Promotion Logs</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>


    <div class="content-area">
        <div class="records-container">
            <h1>📋 Attendance Records</h1>

            <!-- Filter Form -->
            <form class="filter-form" method="GET">
                <input type="text" name="search_name" placeholder="Search by name..." value="<?= htmlspecialchars($search_name) ?>">
                <input type="text" name="search_code" placeholder="User Code..." value="<?= htmlspecialchars($search_code) ?>">
                
                <select name="role">
                    <option value="all">All Roles</option>
                    <?php while ($r = $roles->fetch_assoc()): ?>
                        <option value="<?= $r['role_id'] ?>" <?= ($search_role == $r['role_id']) ? 'selected' : '' ?>>
                            <?= ucfirst($r['role_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="status">
                    <option value="all" <?= $search_status=='all'?'selected':'' ?>>All Status</option>
                    <option value="Present" <?= $search_status=='Present'?'selected':'' ?>>Present</option>
                    <option value="Absent" <?= $search_status=='Absent'?'selected':'' ?>>Absent</option>
                </select>

                <label><strong>Date:</strong></label>
                <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>" max="<?= date('Y-m-d') ?>">

                <label><strong>Or Range:</strong></label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($search_start) ?>" max="<?= date('Y-m-d') ?>">
                <input type="date" name="end_date" value="<?= htmlspecialchars($search_end) ?>" max="<?= date('Y-m-d') ?>">

                <button type="submit" class="save-btn">🔍 Filter</button>
                <button type="button" class="export-btn" onclick="window.location='export_attendance.php'">📤 Export to Excel</button>
            </form>

            <!-- Records Table -->
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User Code</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Time In</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="7">No attendance records found.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['attendance_date']) ?></td>
                                <td><strong><?= htmlspecialchars($row['user_code']) ?></strong></td>
                                <td><?= htmlspecialchars($row['fullname']) ?></td>
                                <td><?= htmlspecialchars($row['role_name']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['time_in']) ?></td>
                                <td><?= htmlspecialchars($row['recorded_by'] ?? '—') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&<?= http_build_query($_GET) ?>">⬅ Prev</a>
                <?php endif; ?>
                Page <?= $page ?> of <?= $total_pages ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&<?= http_build_query($_GET) ?>">Next ➡</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
