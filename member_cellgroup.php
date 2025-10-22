<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_MEMBER]);

$user_id = $_SESSION['user_id'];

// ✅ Log page view
log_action($mysqli, $user_id, 'Member', 'VIEW', 'Accessed My Cell Group page', 'Low');

// Fetch group + leader info
$stmt = $mysqli->prepare("SELECT cg.id AS group_id, cg.group_name, cg.leader_id, l.leader_name, l.email, l.contact
                          FROM users u
                          LEFT JOIN cell_groups cg ON cg.id = u.cell_group_id
                          LEFT JOIN leaders l ON l.leader_id = cg.leader_id
                          WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if (!$info || !$info['group_id']) {
    echo "<div class='cellgroup-message info'><h2>⚠️ You are not yet assigned to a Cell Group.</h2></div>";
    exit;
}

$group_id = $info['group_id'];

// Meetings
$stmt = $mysqli->prepare("SELECT * FROM cell_group_meetings WHERE cell_group_id=? ORDER BY meeting_date DESC");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Attendance
$stmt = $mysqli->prepare("SELECT a.*, m.meeting_date, m.title
                          FROM cell_group_attendance a
                          JOIN cell_group_meetings m ON m.id=a.meeting_id
                          WHERE a.member_id=? AND m.cell_group_id=?
                          ORDER BY m.meeting_date DESC");
$stmt->bind_param("ii", $user_id, $group_id);
$stmt->execute();
$attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_meetings = count($meetings);
$total_present = 0;
foreach ($attendance_records as $r) if ($r['status'] === 'Present') $total_present++;
$attendance_rate = $total_meetings > 0 ? round(($total_present / $total_meetings) * 100, 1) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Member — My Cell Group</title>
<link rel="stylesheet" href="styles_system.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">

    <h1>👥 My Cell Group</h1>

    <!-- Group Info -->
    <div class="cellgroup-section">
      <h2>📘 Group Information</h2>
      <div class="cellgroup-stats">
        <div class="cellgroup-stat-card">
          <h3>Group Name</h3>
          <div class="number"><?= htmlspecialchars($info['group_name']) ?></div>
        </div>
        <div class="cellgroup-stat-card">
          <h3>Leader</h3>
          <div class="number"><?= htmlspecialchars($info['leader_name']) ?></div>
        </div>
        <div class="cellgroup-stat-card">
          <h3>Attendance Rate</h3>
          <div class="number"><?= $attendance_rate ?>%</div>
        </div>
      </div>
      <p><strong>📞 Contact:</strong> <?= htmlspecialchars($info['contact'] ?? 'N/A') ?><br>
      <strong>✉️ Email:</strong> <?= htmlspecialchars($info['email'] ?? 'N/A') ?></p>
    </div>

    <!-- Meetings -->
    <div class="cellgroup-section">
      <h2>📅 Meetings</h2>
      <table class="cellgroup-table">
        <tr><th>Date</th><th>Title</th><th>Description</th></tr>
        <?php foreach($meetings as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['meeting_date']) ?></td>
          <td><?= htmlspecialchars($m['title']) ?></td>
          <td><?= htmlspecialchars($m['description']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- Attendance History -->
    <div class="cellgroup-section">
      <h2>🧾 Attendance History</h2>
      <table class="cellgroup-table">
        <tr><th>Date</th><th>Meeting</th><th>Status</th><th>Marked At</th></tr>
        <?php foreach($attendance_records as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['meeting_date']) ?></td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td class="<?=
              $r['status']==='Present'?'status-present':
              ($r['status']==='Late'?'status-late':'status-absent')
          ?>"><?= htmlspecialchars($r['status']) ?></td>
          <td><?= htmlspecialchars($r['marked_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  </div>
</div>
</body>
</html>
