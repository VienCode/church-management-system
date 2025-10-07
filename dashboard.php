<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([1, 2, 3, 4, 5 ,6, 7, 8]);

// ---- Attendance Summary ----
$attendanceSummary = [
    'total' => 0,
    'present' => 0,
    'absent' => 0
];
$attendanceQuery = "SELECT status, COUNT(*) as count FROM attendance GROUP BY status";
$result = $mysqli->query($attendanceQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendanceSummary['total'] += $row['count'];
        if ($row['status'] === 'Present') {
            $attendanceSummary['present'] = $row['count'];
        } elseif ($row['status'] === 'Absent') {
            $attendanceSummary['absent'] = $row['count'];
        }
    }
}

// ---- Attendance Trend (last 7 days) ----
$attendanceTrend = [];
$attendanceTrendQuery = "
    SELECT DATE(attendance_date) as attendance_date,
           SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance
    GROUP BY DATE(attendance_date)
    ORDER BY DATE(attendance_date) DESC
    LIMIT 7
";

$result = $mysqli->query($attendanceTrendQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendanceTrend[] = $row;
    }
}
$attendanceTrend = array_reverse($attendanceTrend);

// ---- Donations Summary ----
$donationsSummary = [
    'total_amount' => 0,
    'donation_count' => 0
];
$donationsQuery = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM donations";
$donResult = $mysqli->query($donationsQuery);
if ($donResult && $row = $donResult->fetch_assoc()) {
    $donationsSummary['total_amount'] = $row['total_amount'] ?? 0;
    $donationsSummary['donation_count'] = $row['total'];
}

// ---- Donations Trend (last 6 months) ----
$donationTrend = [];
$donTrendQuery = "
    SELECT DATE_FORMAT(donation_date, '%Y-%m') as month, SUM(amount) as total_amount
    FROM donations
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
";
$dResult = $mysqli->query($donTrendQuery);
if ($dResult) {
    while ($row = $dResult->fetch_assoc()) {
        $donationTrend[] = $row;
    }
}
$donationTrend = array_reverse($donationTrend);

// ---- Uploads Summary ----
$uploadsSummary = [
    'file_count' => 0
];
$uploadsQuery = "SELECT COUNT(*) as total FROM posts";
$upResult = $mysqli->query($uploadsQuery);
if ($upResult && $row = $upResult->fetch_assoc()) {
    $uploadsSummary['file_count'] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Church Dashboard</title>
    <link rel="stylesheet" href="styles_system.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <!-- General Pages -->
            <li><a href="dashboard.php" class="active"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>

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

    <!-- Main Content -->
    <div class="content-area">
        <h1>Dashboard</h1>
        <p>General overview of church activities</p>

        <div class="stats-container">
            <div class="stat-card total">
                <h3>Total Attendance Records</h3>
                <div class="number"><?php echo $attendanceSummary['total']; ?></div>
            </div>
            <div class="stat-card present">
                <h3>Present</h3>
                <div class="number"><?php echo $attendanceSummary['present']; ?></div>
            </div>
            <div class="stat-card absent">
                <h3>Absent</h3>
                <div class="number"><?php echo $attendanceSummary['absent']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Donations</h3>
                <div class="number">₱<?php echo number_format($donationsSummary['total_amount'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Donation Count</h3>
                <div class="number"><?php echo $donationsSummary['donation_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Church Updates</h3>
                <div class="number"><?php echo $uploadsSummary['file_count']; ?></div>
            </div>
        </div>

        <!-- Charts Section -->
        <h2>Trends</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <canvas id="attendanceChart"></canvas>
            <canvas id="donationsChart"></canvas>
        </div>
    </div>
</div>

<script>
const attendanceLabels = <?php echo json_encode(array_column($attendanceTrend, 'attendance_date')); ?>;
const presentData = <?php echo json_encode(array_column($attendanceTrend, 'present_count')); ?>;
const absentData = <?php echo json_encode(array_column($attendanceTrend, 'absent_count')); ?>;

const donationLabels = <?php echo json_encode(array_column($donationTrend, 'month')); ?>;
const donationAmounts = <?php echo json_encode(array_column($donationTrend, 'total_amount')); ?>;
</script>
<script src="/capstones/phpdatabasetest/attendance%20test/script.js"></script>
</body>
</html>
