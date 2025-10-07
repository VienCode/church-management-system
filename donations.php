<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([1, 7]); // Admin & Accountant    

// Handle new donation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_donation'])) {
    $amount = $_POST['amount'];
    $donation_date = $_POST['donation_date'];
    $purpose = $_POST['purpose'];

    $stmt = $mysqli->prepare("INSERT INTO donations (amount, donation_date, purpose) VALUES (?, ?, ?)");
    $stmt->bind_param("dss", $amount, $donation_date, $purpose);
    $stmt->execute();
    $success = "Donation successfully recorded!";
}

// Handle Edit Donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_donation'])) {
    $id = $_POST['donation_id'];
    $amount = $_POST['amount'];
    $donation_date = $_POST['donation_date'];
    $purpose = $_POST['purpose'];

    $stmt = $mysqli->prepare("UPDATE donations SET amount=?, donation_date=?, purpose=? WHERE id=?");
    $stmt->bind_param("dssi", $amount, $donation_date, $purpose, $id);
    $stmt->execute();
    $success = "Donation updated successfully!";
}

// Handle Delete Donation
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM donations WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $success = "Donation deleted successfully!";
}

// Handle Export to Excel
if (isset($_POST['export_excel'])) {
    $aggregation = $_POST['aggregation'] ?? 'daily';

    switch ($aggregation) {
        case 'weekly':
            $query = "SELECT * FROM donations WHERE WEEK(donation_date,1)=WEEK(CURDATE(),1) AND YEAR(donation_date)=YEAR(CURDATE()) ORDER BY donation_date DESC";
            break;
        case 'monthly':
            $query = "SELECT * FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE()) ORDER BY donation_date DESC";
            break;
        default:
            $query = "SELECT * FROM donations WHERE donation_date=CURDATE() ORDER BY donation_date DESC";
            break;
    }

    $result = $mysqli->query($query);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="donations_'.$aggregation.'_'.date('Y-m-d').'.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Amount', 'Purpose']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['donation_date'], $row['amount'], $row['purpose']]);
    }
    fclose($output);
    exit;
}

// Aggregation filter
$aggregation = $_POST['aggregation'] ?? 'daily';

// Set high-value threshold
$high_value_threshold = 5000;

// Fetch donations & stats
switch ($aggregation) {
    case 'weekly':
        $donations_stmt = $mysqli->prepare("SELECT * FROM donations WHERE WEEK(donation_date,1)=WEEK(CURDATE(),1) AND YEAR(donation_date)=YEAR(CURDATE()) ORDER BY donation_date DESC");
        $stats_stmt = $mysqli->prepare("SELECT COUNT(*) as donation_count, SUM(amount) as total_amount, AVG(amount) as avg_amount FROM donations WHERE WEEK(donation_date,1)=WEEK(CURDATE(),1) AND YEAR(donation_date)=YEAR(CURDATE())");
        $chart_stmt = $mysqli->prepare("SELECT donation_date, SUM(amount) as total FROM donations WHERE WEEK(donation_date,1)=WEEK(CURDATE(),1) AND YEAR(donation_date)=YEAR(CURDATE()) GROUP BY donation_date ORDER BY donation_date ASC");
        break;
    case 'monthly':
        $donations_stmt = $mysqli->prepare("SELECT * FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE()) ORDER BY donation_date DESC");
        $stats_stmt = $mysqli->prepare("SELECT COUNT(*) as donation_count, SUM(amount) as total_amount, AVG(amount) as avg_amount FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE())");
        $chart_stmt = $mysqli->prepare("SELECT donation_date, SUM(amount) as total FROM donations WHERE MONTH(donation_date)=MONTH(CURDATE()) AND YEAR(donation_date)=YEAR(CURDATE()) GROUP BY donation_date ORDER BY donation_date ASC");
        break;
    default:
        $donations_stmt = $mysqli->prepare("SELECT * FROM donations WHERE donation_date=CURDATE() ORDER BY donation_date DESC");
        $stats_stmt = $mysqli->prepare("SELECT COUNT(*) as donation_count, SUM(amount) as total_amount, AVG(amount) as avg_amount FROM donations WHERE donation_date=CURDATE()");
        $chart_stmt = $mysqli->prepare("SELECT donation_date, SUM(amount) as total FROM donations WHERE donation_date=CURDATE() GROUP BY donation_date ORDER BY donation_date ASC");
        break;
}

$donations_stmt->execute();
$donations = $donations_stmt->get_result();
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result()->fetch_assoc();
$chart_stmt->execute();
$chart_data = $chart_stmt->get_result();

$chart_labels = [];
$chart_totals = [];
while ($row = $chart_data->fetch_assoc()) {
    $chart_labels[] = date('M j', strtotime($row['donation_date']));
    $chart_totals[] = $row['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Church Donations</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <!-- Sidebar Navbar -->
    <nav class="sidebar">
        <div class="logo-section">
            <div class="logo-placeholder"><span>⛪</span></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <ul class="nav-menu">
            <!-- General Pages -->
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php" class="active"><span>💰</span> Donations</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- Expenses Section -->
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- System Section -->
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
        </ul>
    </nav>


    <!-- Content Area -->
    <div class="content-area">
        <h2>Church Donations</h2>

        <?php if(isset($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>

        <!-- Aggregation Dropdown & Export -->
        <div class="date-selector">
            <form method="POST">
                <label for="aggregation">View donations by:</label>
                <select name="aggregation" id="aggregation" onchange="this.form.submit()">
                    <option value="daily" <?= $aggregation=='daily'?'selected':'' ?>>Daily</option>
                    <option value="weekly" <?= $aggregation=='weekly'?'selected':'' ?>>Weekly</option>
                    <option value="monthly" <?= $aggregation=='monthly'?'selected':'' ?>>Monthly</option>
                </select>
                <button type="submit" name="export_excel" class="secondary-btn">Export to Excel</button>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <h3>Total Donations</h3>
                <div class="number"><?= number_format($stats_result['total_amount'], 2) ?></div>
            </div>
            <div class="stat-card present">
                <h3>Number of Donations</h3>
                <div class="number"><?= $stats_result['donation_count'] ?></div>
            </div>
            <div class="stat-card absent">
                <h3>Average Donation</h3>
                <div class="number"><?= number_format($stats_result['avg_amount'], 2) ?></div>
            </div>
        </div>

        <!-- Donations Chart -->
        <div style="margin:30px 0;">
            <canvas id="donationsChart" style="width:100%; max-width:800px;"></canvas>
        </div>

        <!-- Donation Form -->
        <div class="date-selector">
            <form method="POST">
                <input type="date" name="donation_date" required value="<?= date('Y-m-d') ?>">
                <input type="number" step="0.01" name="amount" placeholder="Amount" required>
                <input type="text" name="purpose" placeholder="Purpose (optional)">
                <button type="submit" name="save_donation" class="save-btn">Record Donation</button>
            </form>
        </div>

        <!-- Donations Table -->
        <div class="attendance-table">
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
                    <?php if($donations->num_rows > 0): ?>
                        <?php while($row = $donations->fetch_assoc()): 
                            $highlight = $row['amount'] >= $high_value_threshold ? 'high-value' : '';
                        ?>
                            <tr class="<?= $highlight ?>">
                                <td><?= date('F j, Y', strtotime($row['donation_date'])) ?></td>
                                <td><?= number_format($row['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($row['purpose']) ?></td>
                                <td>
                                    <button class="primary-btn" onclick="openModal('editModal_<?= $row['id'] ?>')">Edit</button>
                                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')" class="secondary-btn">Delete</a>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div id="editModal_<?= $row['id'] ?>" class="modal">
                                <div class="modal-content">
                                    <span class="close" onclick="closeModal('editModal_<?= $row['id'] ?>')">&times;</span>
                                    <h3>Edit Donation</h3>
                                    <form method="POST">
                                        <input type="hidden" name="donation_id" value="<?= $row['id'] ?>">
                                        <input type="date" name="donation_date" required value="<?= $row['donation_date'] ?>">
                                        <input type="number" step="0.01" name="amount" required value="<?= $row['amount'] ?>">
                                        <input type="text" name="purpose" placeholder="Purpose (optional)" value="<?= htmlspecialchars($row['purpose']) ?>">
                                        <button type="submit" name="edit_donation" class="save-btn">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#666;">No donations recorded for this period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.high-value {
    background: linear-gradient(135deg, #fff4e5, #ffe0b2);
    font-weight: bold;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Donations Chart
const ctx = document.getElementById('donationsChart').getContext('2d');
const donationsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Total Donations',
            data: <?= json_encode($chart_totals) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

// Modal JS
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if(modal) { modal.classList.add('show'); document.body.style.overflow = 'hidden'; }
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if(modal) { modal.classList.remove('show'); document.body.style.overflow = 'auto'; }
}
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => { if(event.target === modal) { modal.classList.remove('show'); document.body.style.overflow = 'auto'; } });
}
</script>
</body>
</html>
