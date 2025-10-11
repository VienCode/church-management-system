<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// ✅ Handle new donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_donation'])) {
    $amount = floatval($_POST['amount']);
    $donation_date = $_POST['donation_date'];
    $purpose = $_POST['purpose'];
    $recorded_by = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];

    // Outsider-specific fields
    $donor_name = ($purpose === 'Outsider Donations') ? trim($_POST['donor_name']) : null;
    $donation_description = ($purpose === 'Outsider Donations') ? trim($_POST['donation_description']) : null;

    $stmt = $mysqli->prepare("
        INSERT INTO donations (amount, donation_date, purpose, donor_name, donation_description, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("dsssss", $amount, $donation_date, $purpose, $donor_name, $donation_description, $recorded_by);
    $stmt->execute();
    $success = "✅ Donation successfully recorded!";
}

// ✅ Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $mysqli->query("DELETE FROM donations WHERE id = $id");
    $success = "🗑️ Donation deleted successfully!";
}

// ✅ Fetch all donations
$result = $mysqli->query("SELECT * FROM donations ORDER BY donation_date DESC");
$donations = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Chart Data: Group by Purpose
$chart_query = $mysqli->query("
    SELECT purpose, SUM(amount) AS total 
    FROM donations 
    GROUP BY purpose 
    ORDER BY total DESC
");
$chart_labels = [];
$chart_values = [];
$total_donations = 0;
$top_source = '';

while ($row = $chart_query->fetch_assoc()) {
    $chart_labels[] = $row['purpose'];
    $chart_values[] = $row['total'];
    $total_donations += $row['total'];
    if (!$top_source) $top_source = $row['purpose'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Church Donations | UCF</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        .donations-container {
            background:white; padding:25px; border-radius:12px; 
            max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.1);
        }
        form input, form select, textarea {
            padding:8px; border-radius:6px; border:1px solid #ccc; width:100%; margin-bottom:10px;
        }
        .save-btn {
            background:#0271c0; color:white; border:none; border-radius:8px; 
            padding:10px 18px; cursor:pointer; font-weight:600;
        }
        .save-btn:hover { background:#02589b; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
        th { background:#0271c0; color:#fff; }
        .high-value { background:linear-gradient(135deg, #fff7e6, #ffe9b3); font-weight:bold; }
        .chart-section { margin-top:40px; text-align:center; }
        .chart-summary { margin-top:15px; display:flex; justify-content:center; gap:20px; font-weight:600; }
        .chart-summary div { background:#f5f8fc; padding:10px 15px; border-radius:8px; }
    </style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="donations-container">
            <h1>💰 Church Donations</h1>

            <?php if (isset($success)): ?>
                <div class="success-message"><?= $success ?></div>
            <?php endif; ?>

            <!-- Add Donation Form -->
            <form method="POST">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input type="date" name="donation_date" required value="<?= date('Y-m-d') ?>">
                    <input type="number" step="0.01" name="amount" placeholder="Amount (₱)" required style="flex:1;">
                </div>

                <label for="purpose"><strong>Purpose:</strong></label>
                <select name="purpose" id="purposeSelect" required>
                    <option value="" disabled selected>Select Purpose</option>
                    <option value="Tithes & Offerings">Tithes & Offerings</option>
                    <option value="Event Earnings">Event Earnings</option>
                    <option value="Merchandise">Merchandise</option>
                    <option value="Outsider Donations">Outsider Donations</option>
                </select>

                <!-- Outsider Donation Fields -->
                <div id="outsiderFields" style="display:none; margin-top:10px;">
                    <input type="text" name="donor_name" id="donor_name" placeholder="Donor Name">
                    <textarea name="donation_description" id="donation_description" placeholder="Describe the donation (optional)" rows="2"></textarea>
                </div>

                <button type="submit" name="save_donation" class="save-btn">💾 Record Donation</button>
            </form>

            <!-- Donations Table -->
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Purpose</th>
                        <th>Donor</th>
                        <th>Description</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donations)): ?>
                        <tr><td colspan="7">No donations recorded yet.</td></tr>
                    <?php else: foreach ($donations as $d): ?>
                        <tr class="<?= ($d['amount'] >= 5000) ? 'high-value' : '' ?>">
                            <td><?= date('F j, Y', strtotime($d['donation_date'])) ?></td>
                            <td>₱<?= number_format($d['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($d['purpose']) ?></td>
                            <td><?= htmlspecialchars($d['donor_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['donation_description'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['recorded_by'] ?? '-') ?></td>
                            <td>
                                <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Delete this donation?')" class="secondary-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Chart Section -->
            <div class="chart-section">
                <h2>📊 Donations Breakdown by Purpose</h2>
                <canvas id="purposeChart" style="max-width:600px; margin:auto;"></canvas>
                <div class="chart-summary">
                    <div>💵 Total: ₱<?= number_format($total_donations, 2) ?></div>
                    <div>🏆 Top Source: <?= htmlspecialchars($top_source ?: 'N/A') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ✅ Show outsider fields dynamically
document.getElementById('purposeSelect').addEventListener('change', function() {
    const outsiderFields = document.getElementById('outsiderFields');
    outsiderFields.style.display = (this.value === 'Outsider Donations') ? 'block' : 'none';
});

// ✅ Purpose Pie Chart
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
