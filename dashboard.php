<?php
include 'database.php';
include 'auth_check.php';

// Allow all key roles
restrict_to_roles([
    ROLE_LEADER, ROLE_ATTENDANCE_MARKER, ROLE_EDITOR,
    ROLE_ADMIN, ROLE_MEMBER, ROLE_PASTOR, ROLE_ACCOUNTANT, ROLE_NON_MEMBER
]);

// Logged-in user info
$user_id = $_SESSION['user_id'];
$firstname = $_SESSION['firstname'];
$lastname = $_SESSION['lastname'];
$role_id = $_SESSION['role_id'];
$role_name = ucfirst($_SESSION['role'] ?? 'User');
$user_email = $_SESSION['email'] ?? '';

// Basic summaries (common data)
$attendanceSummary = ['total' => 0, 'present' => 0, 'absent' => 0];
$result = $mysqli->query("SELECT status, COUNT(*) as count FROM attendance GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendanceSummary['total'] += $row['count'];
        if ($row['status'] === 'Present') $attendanceSummary['present'] = $row['count'];
        elseif ($row['status'] === 'Absent') $attendanceSummary['absent'] = $row['count'];
    }
}

// Donations summary
$donationsSummary = ['total_amount' => 0, 'donation_count' => 0];
$res = $mysqli->query("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM donations");
if ($res && $row = $res->fetch_assoc()) {
    $donationsSummary['total_amount'] = $row['total_amount'] ?? 0;
    $donationsSummary['donation_count'] = $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 20px;
  margin-top: 20px;
}
.card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  padding: 20px;
}
.card h2 { color:#0271c0; margin-bottom:10px; }
</style>
</head>

<body>
<div class="main-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="content-area">

    <!-- ✨ Role-based Welcome Banner -->
    <?php include __DIR__ . '/widgets/welcome_banner.php'; ?>

    <!-- ✨ Role-based Dashboard Sections -->
    <div class="dashboard-grid">
      <?php
      switch ($role_id) {
        case ROLE_ADMIN:
          include 'dashboards/dashboard_admin.php';
          break;
        case ROLE_LEADER:
          include 'dashboards/dashboard_leader.php';
          break;
        case ROLE_MEMBER:
          include 'dashboards/dashboard_member.php';
          break;
        case ROLE_EDITOR:
          include 'dashboards/dashboard_editor.php';
          break;
        case ROLE_ACCOUNTANT:
          include 'dashboards/dashboard_accountant.php';
          break;
        case ROLE_PASTOR:
          include 'dashboards/dashboard_pastor.php';
          break;
        case ROLE_NON_MEMBER:
          include 'dashboards/dashboard_nonmember.php';
          break;
        default:
          echo "<p>⚠️ Unknown role detected. Please contact an administrator.</p>";
      }
      ?>
    </div>
  </div>
</div>
</body>
</html>
