<?php
$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ATTENDANCE_MARKER, ROLE_EDITOR, ROLE_ADMIN, ROLE_MEMBER, ROLE_PASTOR, ROLE_ACCOUNTANT, ROLE_NON_MEMBER]);

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
// Fetch pinned announcements (always on top)
$pinned_updates = $mysqli->query("
    SELECT title, description, image_path, posted_by_name, created_at
    FROM church_updates
    WHERE is_archived = 0 AND is_pinned = 1
    ORDER BY created_at DESC
");

// Fetch latest 3 non-pinned announcements
$recent_updates = $mysqli->query("
    SELECT title, description, image_path, posted_by_name, created_at
    FROM church_updates
    WHERE is_archived = 0 AND is_pinned = 0
    ORDER BY created_at DESC
    LIMIT 3
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Church Dashboard</title>
    <link rel="stylesheet" href="styles_system.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<script src="scripts/sidebar_badges.js"></script>

<body>
<div class="main-layout">
    <!-- Sidebar -->
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

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

        <!-- 📢 Church Announcements Section -->
<div class="announcement-container">
    <h2>📢 Church Announcements</h2>
    <p>Stay updated with the latest church news and events.</p>

    <!-- 🧷 Pinned Posts -->
    <?php if ($pinned_updates->num_rows > 0): ?>
        <h3 style="margin-top:25px; color:#d39e00;">📌 Pinned Announcements</h3>
        <div class="announcement-grid">
            <?php while ($p = $pinned_updates->fetch_assoc()): ?>
                <div class="announcement-card pinned">
                    <?php if ($p['image_path']): ?>
                        <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="Announcement">
                    <?php endif; ?>
                    <div class="announcement-body">
                        <h4><?= htmlspecialchars($p['title']) ?></h4>
                        <p><?= nl2br(htmlspecialchars(substr($p['description'], 0, 120))) ?><?= strlen($p['description']) > 120 ? '...' : '' ?></p>
                        <small>Posted by <?= htmlspecialchars($p['posted_by_name']) ?> on <?= date('F j, Y', strtotime($p['created_at'])) ?></small>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <!-- 🆕 Latest Announcements -->
    <?php if ($recent_updates->num_rows > 0): ?>
        <h3 style="margin-top:30px;">📰 Latest Announcements</h3>
        <div class="announcement-grid">
            <?php while ($r = $recent_updates->fetch_assoc()): ?>
                <div class="announcement-card">
                    <?php if ($r['image_path']): ?>
                        <img src="<?= htmlspecialchars($r['image_path']) ?>" alt="Announcement">
                    <?php endif; ?>
                    <div class="announcement-body">
                        <h4><?= htmlspecialchars($r['title']) ?></h4>
                        <p><?= nl2br(htmlspecialchars(substr($r['description'], 0, 120))) ?><?= strlen($r['description']) > 120 ? '...' : '' ?></p>
                        <small>Posted by <?= htmlspecialchars($r['posted_by_name']) ?> on <?= date('F j, Y', strtotime($r['created_at'])) ?></small>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p style="color:#555;">No announcements yet.</p>
    <?php endif; ?>
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
