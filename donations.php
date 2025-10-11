<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// ✅ Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_donation'])) {
    $amount = $_POST['amount'];
    $donation_date = $_POST['donation_date'];
    $purpose = $_POST['purpose'];
    $stmt = $mysqli->prepare("INSERT INTO donations (amount, donation_date, purpose) VALUES (?, ?, ?)");
    $stmt->bind_param("dss", $amount, $donation_date, $purpose);
    $stmt->execute();
    $success = "✅ Donation recorded successfully!";
}

// ✅ Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_donation'])) {
    $id = $_POST['donation_id'];
    $amount = $_POST['amount'];
    $date = $_POST['donation_date'];
    $purpose = $_POST['purpose'];
    $stmt = $mysqli->prepare("UPDATE donations SET amount=?, donation_date=?, purpose=? WHERE id=?");
    $stmt->bind_param("dssi", $amount, $date, $purpose, $id);
    $stmt->execute();
    $success = "✅ Donation updated successfully!";
}

// ✅ Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM donations WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $success = "🗑️ Donation deleted successfully!";
}

// ✅ Aggregation
$aggregation = $_POST['aggregation'] ?? 'weekly';
switch ($aggregation) {
    case 'monthly':
        $range = "MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE())";
        break;
    case 'weekly':
        $range = "YEARWEEK(donation_date,1)=YEARWEEK(CURDATE(),1)";
        break;
    default:
        $range = "donation_date=CURDATE()";
        break;
}

// ✅ Stats
$stats = $mysqli->query("SELECT COUNT(*) as count, SUM(amount) as total, AVG(amount) as avg FROM donations WHERE $range")->fetch_assoc();
$chart = $mysqli->query("SELECT donation_date, SUM(amount) as total FROM donations WHERE $range GROUP BY donation_date ORDER BY donation_date ASC");
$donations = $mysqli->query("SELECT * FROM donations WHERE $range ORDER BY donation_date DESC");

$chart_labels = [];
$chart_data = [];
while ($row = $chart->fetch_assoc()) {
    $chart_labels[] = date('M j', strtotime($row['donation_date']));
    $chart_data[] = $row['total'];
}

$high_value_threshold = 5000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donations | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.page-header { display:flex; align-items:center; justify-content:space-between; }
.page-header h2 { margin:0; color:#02589b; }
.stats-row { display:flex; gap:20px; margin-top:20px; flex-wrap:wrap; }
.stat-card { flex:1; background:#f6f8fb; border-radius:10px; padding:15px; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.05); }
.stat-card h3 { margin:0; color:#0271c0; }
.stat-card .number { font-size:1.6em; font-weight:bold; color:#333; margin-top:8px; }
.success-message { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:8px; margin:10px 0; }
.table-container { overflow-x:auto; margin-top:20px; }
table { width:100%; border-collapse:collapse; }
th,td { padding:10px; border-bottom:1px solid #eaeaea; text-align:center; }
th { background:#0271c0; color:white; }
tr.high-value { background:#fff3cd; }
input.inline-edit { width:90%; padding:6px; border:1px solid #ccc; border-radius:5px; text-align:center; }
form.inline-form { display:inline; }
button.edit-btn, button.delete-btn, button.save-btn { border:none; border-radius:5px; padding:6px 10px; cursor:pointer; font-weight:600; }
button.edit-btn { background:#0271c0; color:white; }
button.delete-btn { background:#dc3545; color:white; }
button.save-btn { background:#28a745; color:white; }
.chart-container { margin:30px auto; max-width:800px; }
.inline-add-form input { margin:5px; padding:8px; border-radius:6px; border:1px solid #ccc; }
.inline-add-form button { padding:8px 14px; border:none; border-radius:6px; background:#0271c0; color:white; font-weight:600; cursor:pointer; }
.inline-add-form button:hover { background:#02589b; }
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-area">
    <div class="page-header">
        <h2>💰 Church Donations</h2>
        <form method="POST">
            <select name="aggregation" onchange="this.form.submit()">
                <option value="daily" <?= $aggregation=='daily'?'selected':'' ?>>Daily</option>
                <option value="weekly" <?= $aggregation=='weekly'?'selected':'' ?>>Weekly</option>
                <option value="monthly" <?= $aggregation=='monthly'?'selected':'' ?>>Monthly</option>
            </select>
        </form>
    </div>

    <?php if(isset($success)): ?><div class="success-message"><?= $success ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><h3>Total Donations</h3><div class="number"><?= number_format($stats['total'] ?? 0,2) ?></div></div>
        <div class="stat-card"><h3>Number of Donations</h3><div class="number"><?= $stats['count'] ?? 0 ?></div></div>
        <div class="stat-card"><h3>Average Donation</h3><div class="number"><?= number_format($stats['avg'] ?? 0,2) ?></div></div>
    </div>

    <!-- Chart -->
    <div class="chart-container"><canvas id="donationsChart"></canvas></div>

    <!-- Inline Add -->
    <h3>Add New Donation</h3>
    <form method="POST" class="inline-add-form">
        <input type="date" name="donation_date" required value="<?= date('Y-m-d') ?>">
        <input type="number" step="0.01" name="amount" placeholder="Amount" required>
        <input type="text" name="purpose" placeholder="Purpose (optional)">
        <button type="submit" name="add_donation">➕ Add Donation</button>
    </form>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($donations->num_rows > 0): $i=1; while($d=$donations->fetch_assoc()): ?>
                <tr class="<?= $d['amount'] >= $high_value_threshold ? 'high-value' : '' ?>">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                        <td><input type="date" name="donation_date" class="inline-edit" value="<?= $d['donation_date'] ?>"></td>
                        <td><input type="number" step="0.01" name="amount" class="inline-edit" value="<?= $d['amount'] ?>"></td>
                        <td><input type="text" name="purpose" class="inline-edit" value="<?= htmlspecialchars($d['purpose']) ?>"></td>
                        <td>
                            <button type="submit" name="update_donation" class="save-btn">💾 Save</button>
                            <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Delete this donation?')" class="delete-btn" style="text-decoration:none; padding:6px 10px;">🗑️</a>
                        </td>
                    </form>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="4" style="color:#888;">No donations recorded for this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('donationsChart').getContext('2d');
new Chart(ctx, {
    type:'bar',
    data:{
        labels: <?= json_encode($chart_labels) ?>,
        datasets:[{ 
            label:'Total Donations',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor:'rgba(2,113,192,0.7)',
            borderRadius:5
        }]
    },
    options:{
        plugins:{ legend:{display:false} },
        scales:{ y:{ beginAtZero:true } }
    }
});
</script>
</body>
</html>
