<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_email = $_SESSION['email'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

// ✅ Ensure leader exists or auto-register
$check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? LIMIT 1");
$check_leader->bind_param("s", $user_email);
$check_leader->execute();
$leader_data = $check_leader->get_result()->fetch_assoc();
$check_leader->close();

if (!$leader_data && !empty($user_email)) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, status, created_at)
        VALUES (?, ?, (SELECT contact FROM users WHERE email = ? LIMIT 1), 'active', NOW())
    ");
    $insert->bind_param("sss", $fullname, $user_email, $user_email);
    $insert->execute();
    $insert->close();
    header("Location: cell_group_leader.php");
    exit;
}

$leader_id = $leader_data['leader_id'];

// ✅ Get active cell group
$group_stmt = $mysqli->prepare("
    SELECT id, group_name
    FROM cell_groups
    WHERE leader_id = ? AND status = 'active'
    LIMIT 1
");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not yet assigned to any Cell Group.</h2>";
    exit;
}

$group_id = $group['id'];
$group_name = $group['group_name'];

// ✅ Handle meeting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meeting'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $meeting_date = $_POST['meeting_date'];

    $stmt = $mysqli->prepare("
        INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isss", $group_id, $title, $description, $meeting_date);
    $stmt->execute();
    $stmt->close();

    $success = "✅ Meeting added successfully!";
}

// ✅ Fetch all members under this leader
$members_stmt = $mysqli->prepare("
    SELECT 
        u.user_code, 
        CONCAT(u.firstname, ' ', u.lastname) AS fullname, 
        u.email, 
        u.contact
    FROM cell_group_members m
    JOIN users u ON m.user_code = u.user_code
    WHERE m.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("i", $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();

// ✅ Fetch all meetings with attendance summary
$meetings_stmt = $mysqli->prepare("
    SELECT 
        m.id, 
        m.title, 
        m.description, 
        m.meeting_date,
        COUNT(a.user_code) AS total_marked,
        SUM(a.status = 'Present') AS present_count,
        SUM(a.status = 'Absent') AS absent_count,
        SUM(a.status = 'Late') AS late_count
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a ON m.id = a.meeting_id
    WHERE m.cell_group_id = ?
    GROUP BY m.id
    ORDER BY m.meeting_date DESC
");
$meetings_stmt->bind_param("i", $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📅 My Cell Group (Leader) | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1100px;
    margin: 30px auto;
}
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; padding-bottom:5px; margin-bottom:15px; }
.save-btn { background:#0271c0; color:white; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
.save-btn:hover { background:#02589b; }
.success { background:#e6ffed; color:#256029; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:600; }
.table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.stats { background:#f4f7fa; padding:5px 10px; border-radius:6px; font-size:0.9em; }
</style>
</head>

<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

   <div class="content-area">
      <div class="cell-container">
         <h1>📅 My Cell Group</h1>
         <p>Welcome, <strong><?= htmlspecialchars($fullname) ?></strong>! You’re managing <strong><?= htmlspecialchars($group_name) ?></strong>.</p>

         <?php if (!empty($success)): ?>
            <div class="success"><?= $success ?></div>
         <?php endif; ?>

         <!-- Add Meeting -->
         <section>
            <h2 class="section-title">➕ Add New Meeting</h2>
            <form method="POST">
               <label>Meeting Title:</label><br>
               <input type="text" name="title" required style="width:100%; padding:8px; margin-bottom:10px;"><br>
               <label>Description:</label><br>
               <textarea name="description" rows="3" style="width:100%; padding:8px; margin-bottom:10px;"></textarea><br>
               <label>Date:</label><br>
               <input type="date" name="meeting_date" required style="padding:8px; margin-bottom:10px;"><br>
               <button type="submit" name="add_meeting" class="save-btn">💾 Save Meeting</button>
            </form>
         </section>

         <!-- Meeting List -->
         <section style="margin-top:30px;">
            <h2 class="section-title">📋 Meetings & Attendance Summary</h2>
            <?php if ($meetings->num_rows === 0): ?>
               <p>No meetings scheduled yet.</p>
            <?php else: ?>
               <table class="table">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Marked</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php while ($m = $meetings->fetch_assoc()): ?>
                     <tr>
                        <td><?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date']))) ?></td>
                        <td><?= htmlspecialchars($m['title']) ?></td>
                        <td><?= htmlspecialchars($m['description']) ?></td>
                        <td><span class="stats"><?= $m['total_marked'] ?></span></td>
                        <td><span class="stats" style="color:green;"><?= $m['present_count'] ?></span></td>
                        <td><span class="stats" style="color:red;"><?= $m['absent_count'] ?></span></td>
                        <td><span class="stats" style="color:orange;"><?= $m['late_count'] ?></span></td>
                        <td><a href="cell_group_attendance.php?meeting_id=<?= $m['id'] ?>" class="save-btn">📝 Mark</a></td>
                     </tr>
                     <?php endwhile; ?>
                  </tbody>
               </table>
            <?php endif; ?>
         </section>

         <!-- Members List -->
         <section style="margin-top:30px;">
            <h2 class="section-title">👥 Members</h2>
            <?php if ($members->num_rows === 0): ?>
               <p>No members assigned to your group yet.</p>
            <?php else: ?>
               <table class="table">
                  <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Contact</th></tr></thead>
                  <tbody>
                     <?php while ($m = $members->fetch_assoc()): ?>
                     <tr>
                        <td><?= htmlspecialchars($m['user_code']) ?></td>
                        <td><?= htmlspecialchars($m['fullname']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['contact']) ?></td>
                     </tr>
                     <?php endwhile; ?>
                  </tbody>
               </table>
            <?php endif; ?>
         </section>
      </div>
   </div>
</div>
</body>
</html>
