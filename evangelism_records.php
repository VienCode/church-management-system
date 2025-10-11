<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER]);

// Filters
$search_name = $_GET['search_name'] ?? '';
$search_status = $_GET['status'] ?? 'all';
$search_start = $_GET['start_date'] ?? '';
$search_end = $_GET['end_date'] ?? '';

// Build conditions
$where = "1";
$params = [];
$types = "";

if (!empty($search_name)) {
    $where .= " AND CONCAT(n.firstname, ' ', n.lastname) LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}
if ($search_status !== 'all') {
    $where .= " AND e.status = ?";
    $params[] = $search_status;
    $types .= "s";
}
if (!empty($search_start) && !empty($search_end)) {
    $where .= " AND e.attendance_date BETWEEN ? AND ?";
    $params[] = $search_start;
    $params[] = $search_end;
    $types .= "ss";
}

// Query
$sql = "
    SELECT 
        n.id,
        n.user_code,
        CONCAT(n.firstname, ' ', n.lastname) AS fullname,
        n.contact,
        n.email,
        n.attendances_count,
        e.attendance_date,
        e.status,
        e.time_in,
        e.recorded_by
    FROM non_members n
    LEFT JOIN evangelism_attendance e ON n.id = e.non_member_id
    WHERE $where
    ORDER BY e.attendance_date DESC, n.lastname ASC
";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$records = $result->fetch_all(MYSQLI_ASSOC);
$present = $absent = 0;
foreach ($records as $r) {
    if ($r['status'] === 'Present') $present++;
    elseif ($r['status'] === 'Absent') $absent++;
}
$total = count($records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Evangelism Records | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.records-container {
    background:#fff;
    padding:24px;
    border-radius:12px;
    max-width:1100px;
    margin:30px auto;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}
.search-filters {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:15px;
    align-items:center;
}
.search-filters input,
.search-filters select {
    padding:8px;
    border:1px solid #ccc;
    border-radius:6px;
}
.search-btn {
    background:#0271c0;
    color:white;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
}
.export-btn {
    background:#28a745;
    color:white;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
}
table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}
th, td {
    padding:10px 12px;
    border-bottom:1px solid #e6e6e6;
    text-align:center;
}
th {
    background:#0271c0;
    color:white;
}
.summary {
    margin-top:20px;
    display:flex;
    justify-content:center;
    gap:10px;
}
.summary div {
    background:#f6f8fb;
    padding:10px 20px;
    border-radius:8px;
    font-weight:600;
}
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="records-container">
            <h1>🌱 Evangelism Records</h1>
            <p>View and manage attendance logs for non-members.</p>

            <!-- Filters -->
            <form method="GET" class="search-filters">
                <input type="text" name="search_name" placeholder="Search name..." value="<?= htmlspecialchars($search_name) ?>">
                <select name="status">
                    <option value="all" <?= $search_status == 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="Present" <?= $search_status == 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Absent" <?= $search_status == 'Absent' ? 'selected' : '' ?>>Absent</option>
                </select>
                <label>From:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($search_start) ?>">
                <label>To:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($search_end) ?>">
                <button type="submit" class="search-btn">🔍 Filter</button>
            </form>

            <!-- Export Button -->
            <form method="GET" action="export_evangelism_report.php">
                <input type="hidden" name="search_name" value="<?= htmlspecialchars($search_name) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($search_status) ?>">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($search_start) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($search_end) ?>">
                <button type="submit" class="export-btn">📤 Export to Excel</button>
            </form>

            <!-- Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User Code</th>
                        <th>Full Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Attendance Date</th>
                        <th>Status</th>
                        <th>Time In</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="9">No records found.</td></tr>
                    <?php else: $i=1; foreach ($records as $r): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($r['user_code'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= htmlspecialchars($r['contact']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= htmlspecialchars($r['attendance_date'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['status'] ?? 'Not Marked') ?></td>
                            <td><?= htmlspecialchars($r['time_in'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['recorded_by'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Summary -->
            <div class="summary">
                <div>✅ Present: <?= $present ?></div>
                <div>❌ Absent: <?= $absent ?></div>
                <div>👥 Total Records: <?= $total ?></div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Collapsible submenus
    document.querySelectorAll('.collapse-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const submenu = btn.nextElementSibling;
            submenu.classList.toggle('open');
        });
    });

    // Sidebar collapse button
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    toggleBtn?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
});
</script>
</body>
</html>
