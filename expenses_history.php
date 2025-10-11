<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_PASTOR, ROLE_LEADER]);

// Filters
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build WHERE conditions
$where = "1";
$params = [];
$types = "";

if ($category !== 'all') { $where .= " AND category = ?"; $params[] = $category; $types .= "s"; }
if ($status !== 'all') { $where .= " AND status = ?"; $params[] = $status; $types .= "s"; }
if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND submitted_at BETWEEN ? AND ?";
    $params[] = $start_date . " 00:00:00";
    $params[] = $end_date . " 23:59:59";
    $types .= "ss";
}

$sql = "SELECT * FROM expenses WHERE $where ORDER BY submitted_at DESC";
$stmt = $mysqli->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Expense History | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.1); max-width:1100px; margin:30px auto; }
.filters { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; }
.filters select, .filters input { padding:8px; border-radius:6px; border:1px solid #ccc; }
.filters button { background:#0271c0; color:white; border:none; padding:8px 14px; border-radius:8px; cursor:pointer; }
.filters button:hover { background:#02589b; }
.status-pending { color:orange; font-weight:bold; }
.status-approved { color:green; font-weight:bold; }
.status-rejected { color:red; font-weight:bold; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { padding:10px; text-align:center; border-bottom:1px solid #ddd; }
th { background:#0271c0; color:white; }
.total-box { margin-top:20px; background:#f4f8fc; padding:10px; border-radius:8px; font-weight:600; text-align:center; }
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
    <div class="container">
        <h1>📜 Expense History</h1>

        <form class="filters" method="GET">
            <select name="category">
                <option value="all">All Categories</option>
                <option value="Maintenance" <?= $category=='Maintenance'?'selected':'' ?>>Maintenance</option>
                <option value="Ministry" <?= $category=='Ministry'?'selected':'' ?>>Ministry</option>
                <option value="Supplies" <?= $category=='Supplies'?'selected':'' ?>>Supplies</option>
                <option value="Event" <?= $category=='Event'?'selected':'' ?>>Event</option>
                <option value="Outreach" <?= $category=='Outreach'?'selected':'' ?>>Outreach</option>
                <option value="Other" <?= $category=='Other'?'selected':'' ?>>Other</option>
            </select>

            <select name="status">
                <option value="all">All Status</option>
                <option value="Pending" <?= $status=='Pending'?'selected':'' ?>>Pending</option>
                <option value="Approved" <?= $status=='Approved'?'selected':'' ?>>Approved</option>
                <option value="Rejected" <?= $status=='Rejected'?'selected':'' ?>>Rejected</option>
            </select>

            <label>From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <label>To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">

            <button type="submit">Filter</button>
        </form>

        <?php if ($expenses->num_rows === 0): ?>
            <p>No expenses found for the selected filters.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Submitted By</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Approved By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    while ($e = $expenses->fetch_assoc()):
                        $total += $e['amount'];
                    ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($e['submitted_at'])) ?></td>
                            <td><?= htmlspecialchars($e['submitted_by']) ?></td>
                            <td><?= htmlspecialchars($e['category']) ?></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td>₱<?= number_format($e['amount'], 2) ?></td>
                            <td class="status-<?= strtolower($e['status']) ?>"><?= htmlspecialchars($e['status']) ?></td>
                            <td><?= htmlspecialchars($e['approved_by'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($e['remarks'] ?? '-') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="total-box">💰 Total Recorded Expenses: ₱<?= number_format($total, 2) ?></div>
        <?php endif; ?>
    </div>
</div>
</div>
</body>
</html>
