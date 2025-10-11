<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// ✅ Handle Filters
$search = trim($_GET['search'] ?? '');
$purpose = trim($_GET['purpose'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$recorded_by = trim($_GET['recorded_by'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if ($search !== "") {
    $where .= " AND (purpose LIKE ? OR recorded_by LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $types .= "ss";
}
if ($purpose !== "") {
    $where .= " AND purpose = ?";
    $params[] = $purpose;
    $types .= "s";
}
if ($recorded_by !== "") {
    $where .= " AND recorded_by = ?";
    $params[] = $recorded_by;
    $types .= "s";
}
if ($start_date && $end_date) {
    $where .= " AND donation_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// ✅ Fetch Donations
$sql = "SELECT * FROM donations WHERE $where ORDER BY donation_date DESC";
$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$donations = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Summary Stats
$stats_sql = "SELECT COUNT(*) AS total_count, SUM(amount) AS total_sum, AVG(amount) AS avg_amount, MAX(amount) AS max_amount FROM donations WHERE $where";
$stats_stmt = $mysqli->prepare($stats_sql);
if ($params) $stats_stmt->bind_param($types, ...$params);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// ✅ Chart Data (by Purpose)
$chart_sql = "SELECT purpose, SUM(amount) AS total FROM donations WHERE $where GROUP BY purpose ORDER BY total DESC";
$chart_stmt = $mysqli->prepare($chart_sql);
if ($params) $chart_stmt->bind_param($types, ...$params);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

$chart_labels = [];
$chart_values = [];
$total_amount = 0;
$top_source = '';

while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = $row['purpose'];
    $chart_values[] = $row['total'];
    $total_amount += $row['total'];
    if (!$top_source) $top_source = $row['purpose'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donation Records | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.records-container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.1); max-width:1100px; margin:30px auto; }
.filter-section { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; align-items:center; }
.filter-section input, .filter-section select { padding:8px; border-radius:6px; border:1px solid #ccc; }
.stats-summary { display:flex; justify-content:space-around; margin:20px 0; flex-wrap:wrap; }
.stat-card { background:#f4f8fc; border-radius:10px; padding:15px; flex:1; text-align:center; margin:5px; min-width:180px; }
.stat-card h3 { color:#0271c0; margin:0; }
.stat-card .value { font-weight:bold; font-size:1.5em; color:#333; margin-top:8px; }
.chart-section { margin-top:30px; text-align:center; }
.chart-summary { margin-top:15px; display:flex; justify-content:center; gap:20px; font-weight:600; flex-wrap:wrap; }
.chart-summary div { background:#f5f8fc; padding:10px 15px; border-radius:8px; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:#fff; }
.high-value { background:linear-gradient(135deg, #fff7e6, #ffe9b3); font-weight:bold; }
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-area">
    <div class="records-container">
        <h1>💰 Donation Records</h1>
        <p>View and analyze all recorded church donations.</p>

        <!-- Filters -->
        <form method="GET" class="filter-section">
            <input type="text" name="search" placeholder="🔍 Search by purpose or recorder" value="<?= htmlspecialchars($search) ?>">
            <select name="purpose">
                <option value="">All Purposes</option>
                <?php
                $purposes = $mysqli->query("SELECT DISTINCT purpose FROM donations WHERE purpose IS NOT NULL ORDER BY purpose ASC");
                while ($p = $purposes->fetch_assoc()):
                ?>
                <option value="<?= htmlspecialchars($p['purpose']) ?>" <?= $purpose === $p['purpose'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['purpose']) ?>
                </option>
                <?php endwhile; ?>
            </select>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <input type="text" name="recorded_by" placeholder="Recorder name" value="<?= htmlspecialchars($recorded_by) ?>">
            <button type="submit" class="primary-btn">Apply Filters</button>
        </form>

        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-card">
                <h3>Total Donations</h3>
                <div class="value">₱<?= number_format($stats['total_sum'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <h3>Records Found</h3>
                <div class="value"><?= $stats['total_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Donation</h3>
                <div class="value">₱<?= number_format($stats['avg_amount'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <h3>Highest Donation</h3>
                <div class="value">₱<?= number_format($stats['max_amount'] ?? 0, 2) ?></div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-section">
            <h2>📊 Donations Breakdown by Purpose</h2>
            <canvas id="purposeChart" style="max-width:600px; margin:auto;"></canvas>
            <div class="chart-summary">
                <div>💵 Total: ₱<?= number_format($total_amount, 2) ?></div>
                <div>🏆 Top Source: <?= htmlspecialchars($top_source ?: 'N/A') ?></div>
            </div>
        </div>

        <!-- Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Donor</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($donations)): ?>
                    <tr><td colspan="6">No donations found for this filter.</td></tr>
                <?php else: $i=1; foreach ($donations as $d): ?>
                    <tr class="<?= ($d['amount'] >= 5000) ? 'high-value' : '' ?>">
                        <td><?= $i++ ?></td>
                        <td><?= date('F j, Y', strtotime($d['donation_date'])) ?></td>
                        <td>₱<?= number_format($d['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($d['purpose']) ?></td>
                        <td><?= htmlspecialchars($d['donor_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['recorded_by'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ✅ Pie Chart for Purpose Breakdown
const ctx = document.getElementById('purposeChart');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            data: <?= json_encode($chart_values) ?>,
            backgroundColor: [
                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.label + ': ₱' + ctx.formattedValue;
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>
