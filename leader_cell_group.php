<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_LEADER]);

$user_id = $_SESSION['user_id'];
$message = '';

// ----------------------------------------------------
// FETCH LEADER’S GROUP (Fixed Query)
// ----------------------------------------------------
$stmt = $mysqli->prepare("
    SELECT cg.*, l.leader_id, l.leader_name, COUNT(m.member_id) AS member_count
    FROM cell_groups cg
    JOIN leaders l ON cg.leader_id = l.leader_id
    JOIN users u ON u.email = l.email
    LEFT JOIN cell_group_members m ON m.cell_group_id = cg.id
    WHERE u.id = ?
    GROUP BY cg.id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
    echo "<div class='cellgroup-message info'><h2>⚠️ You are not assigned to any cell group yet.</h2></div>";
    exit;
}

$group_id = $group['id'];

// ----------------------------------------------------
// HANDLE FORM SUBMISSIONS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- CREATE MEETING ----
    if ($action === 'create_meeting') {
        $title = trim($_POST['title']);
        $desc  = trim($_POST['description']);
        $date  = $_POST['meeting_date'];

        if ($title !== '' && $date) {
            $stmt = $mysqli->prepare("INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $group_id, $title, $desc, $date, $user_id);
            $stmt->execute();

            log_action($mysqli, $user_id, 'Leader', 'CREATE_MEETING', "Created meeting '$title' on $date for Cell Group #$group_id", 'Normal');
            $message = "<div class='cellgroup-message success'>✅ Meeting created successfully.</div>";
        } else {
            $message = "<div class='cellgroup-message info'>⚠️ Please provide a title and date.</div>";
        }
    }

    // ---- MARK ATTENDANCE ----
    elseif ($action === 'mark_attendance') {
        $meeting_id = intval($_POST['meeting_id']);
        $statuses = $_POST['status'] ?? [];
        $mysqli->begin_transaction();

        try {
            $stmt = $mysqli->prepare("
                INSERT INTO cell_group_attendance (meeting_id, member_id, status, marked_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), marked_at=NOW(), marked_by=VALUES(marked_by)
            ");
            foreach ($statuses as $member_id => $status) {
                $mid = intval($member_id);
                $stmt->bind_param("iisi", $meeting_id, $mid, $status, $user_id);
                $stmt->execute();
            }
            $mysqli->commit();

            log_action($mysqli, $user_id, 'Leader', 'MARK_ATTENDANCE', "Marked attendance for meeting ID #$meeting_id", 'Normal');
            $message = "<div class='cellgroup-message success'>✅ Attendance saved successfully.</div>";
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "<div class='cellgroup-message error'>❌ Error saving attendance: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// ----------------------------------------------------
// FETCH MEMBERS (Fixed Query)
// ----------------------------------------------------
$members = [];
$stmt = $mysqli->prepare("
    SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name, u.email
    FROM cell_group_members m
    JOIN users u ON u.id = m.member_id
    WHERE m.cell_group_id = ?
    ORDER BY u.firstname
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

// ----------------------------------------------------
// FETCH MEETINGS
// ----------------------------------------------------
$meetings = [];
$stmt = $mysqli->prepare("SELECT * FROM cell_group_meetings WHERE cell_group_id=? ORDER BY meeting_date DESC");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leader — My Cell Group</title>
<link rel="stylesheet" href="styles_system.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">

    <h1>👑 My Cell Group — <?= htmlspecialchars($group['group_name']) ?></h1>
    <div class="cellgroup-stats">
        <div class="cellgroup-stat-card">
            <h3>Group ID</h3>
            <div class="number"><?= $group['id'] ?></div>
        </div>
        <div class="cellgroup-stat-card">
            <h3>Leader</h3>
            <div class="number" style="font-size:16px;"><?= htmlspecialchars($group['leader_name']) ?></div>
        </div>
        <div class="cellgroup-stat-card">
            <h3>Members</h3>
            <div class="number"><?= $group['member_count'] ?></div>
        </div>
    </div>

    <?= $message ?>

    <!-- CREATE MEETING -->
    <div class="cellgroup-section">
      <h2>🗓️ Create New Meeting</h2>
      <form method="POST" class="cellgroup-form">
        <input type="hidden" name="action" value="create_meeting">
        <label>Title:</label>
        <input type="text" name="title" required>
        <label>Description:</label>
        <textarea name="description" rows="3"></textarea>
        <label>Date:</label>
        <input type="date" name="meeting_date" required>
        <button type="submit" class="primary-btn">Create Meeting</button>
      </form>
    </div>

    <!-- MEETINGS -->
    <div class="cellgroup-section">
      <h2>📅 Meetings</h2>
      <?php if (empty($meetings)): ?>
        <p><em>No meetings yet.</em></p>
      <?php else: ?>
      <table class="cellgroup-table">
        <tr><th>Date</th><th>Title</th><th>Description</th><th>Actions</th></tr>
        <?php foreach($meetings as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['meeting_date']) ?></td>
          <td><?= htmlspecialchars($m['title']) ?></td>
          <td><?= htmlspecialchars($m['description']) ?></td>
          <td>
            <form method="GET">
              <input type="hidden" name="meeting" value="<?= $m['id'] ?>">
              <button type="submit" class="secondary-btn">📝 Mark Attendance</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>

<?php
if (isset($_GET['meeting'])):
$mid = intval($_GET['meeting']);
$stmt = $mysqli->prepare("SELECT * FROM cell_group_meetings WHERE id=? AND cell_group_id=?");
$stmt->bind_param("ii", $mid, $group_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if ($meeting):
?>
    <!-- MARK ATTENDANCE -->
    <div class="cellgroup-section">
      <h2>📝 Mark Attendance — <?= htmlspecialchars($meeting['title']) ?> (<?= htmlspecialchars($meeting['meeting_date']) ?>)</h2>
      <form method="POST" class="cellgroup-form">
        <input type="hidden" name="action" value="mark_attendance">
        <input type="hidden" name="meeting_id" value="<?= $mid ?>">
        <table class="cellgroup-table">
          <tr><th>Member</th><th>Status</th></tr>
          <?php foreach($members as $mem): ?>
          <tr>
            <td><?= htmlspecialchars($mem['name']) ?> <br><small><?= htmlspecialchars($mem['email']) ?></small></td>
            <td>
              <select class="attendance-select" name="status[<?= $mem['id'] ?>]">
                <option value="Present">Present</option>
                <option value="Absent">Absent</option>
                <option value="Late">Late</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <button type="submit" class="primary-btn">💾 Save Attendance</button>
      </form>
    </div>
<?php endif; endif; ?>

  </div>
</div>
</body>
</html>
