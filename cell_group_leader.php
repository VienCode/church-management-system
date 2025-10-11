<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$leader_email = $_SESSION['email'] ?? null;
$fullname = trim($_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
$contact = $_SESSION['contact'] ?? '';

// ✅ Auto-register leader if not in table
$stmt = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $leader_email);
$stmt->execute();
$leader = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leader) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $insert->bind_param("sss", $fullname, $leader_email, $contact);
    $insert->execute();
    $insert->close();
    header("Location: cell_group_leader.php");
    exit();
}

$leader_id = $leader['leader_id'];

// ✅ Fetch assigned cell group
$group_stmt = $mysqli->prepare("
    SELECT id, group_name 
    FROM cell_groups 
    WHERE leader_id = ?
");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center;color:#555;'>ℹ️ You are not yet assigned to any Cell Group.</h2>";
    exit;
}

$group_id = $group['id'];

// ✅ Handle meeting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meeting'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $date = $_POST['meeting_date'];

    $insert = $mysqli->prepare("
        INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date)
        VALUES (?, ?, ?, ?)
    ");
    $insert->bind_param("isss", $group_id, $title, $desc, $date);
    $insert->execute();
    $insert->close();
    $success = "✅ Meeting added successfully!";
}

// ✅ Fetch members (includes hybrid roles)
$members_stmt = $mysqli->prepare("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email, u.contact
    FROM cell_group_members m
    JOIN users u ON m.member_id = u.id
    WHERE m.cell_group_id = ?
      AND (u.role_id = 3 OR u.is_cell_member = 1)
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("i", $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();

// ✅ Fetch meetings
$meetings_stmt = $mysqli->prepare("
    SELECT id, title, description, meeting_date 
    FROM cell_group_meetings 
    WHERE cell_group_id = ? 
    ORDER BY meeting_date DESC
");
$meetings_stmt->bind_param("i", $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📅 My Cell Group | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff; padding:25px; border-radius:12px; max-width:1100px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.1); }
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; margin-bottom:15px; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th,td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.save-btn { background:#0271c0; color:white; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
.success { background:#e6ffed; color:#256029; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:600; }
</style>
</head>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>
   <div class="content-area">
      <div class="cell-container">
         <h1>📅 My Cell Group</h1>
         <p>Welcome, <strong><?= htmlspecialchars($fullname) ?></strong>! You’re managing <strong><?= htmlspecialchars($group['group_name']) ?></strong>.</p>

         <?php if (!empty($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>

         <h2 class="section-title">➕ Add New Meeting</h2>
         <form method="POST">
            <input type="text" name="title" placeholder="Meeting Title" required style="width:100%; padding:8px;"><br><br>
            <textarea name="description" placeholder="Description" rows="3" style="width:100%; padding:8px;"></textarea><br><br>
            <input type="date" name="meeting_date" required><br><br>
            <button type="submit" name="add_meeting" class="save-btn">💾 Save Meeting</button>
         </form>

         <h2 class="section-title" style="margin-top:30px;">📅 Meetings</h2>
         <?php if ($meetings->num_rows === 0): ?>
            <p>No meetings yet.</p>
         <?php else: ?>
         <table>
            <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Attendance</th></tr></thead>
            <tbody>
               <?php while($m = $meetings->fetch_assoc()): ?>
               <tr>
                  <td><?= date('F j, Y', strtotime($m['meeting_date'])) ?></td>
                  <td><?= htmlspecialchars($m['title']) ?></td>
                  <td><?= htmlspecialchars($m['description']) ?></td>
                  <td><a href="cell_group_attendance.php?meeting_id=<?= $m['id'] ?>" class="save-btn">📝 Mark Attendance</a></td>
               </tr>
               <?php endwhile; ?>
            </tbody>
         </table>
         <?php endif; ?>

         <h2 class="section-title" style="margin-top:30px;">👥 Members</h2>
         <?php if ($members->num_rows === 0): ?>
            <p>No members assigned yet.</p>
         <?php else: ?>
         <table>
            <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Contact</th></tr></thead>
            <tbody>
               <?php while($m = $members->fetch_assoc()): ?>
               <tr>
                  <td><?= $m['user_code'] ?></td>
                  <td><?= $m['fullname'] ?></td>
                  <td><?= $m['email'] ?></td>
                  <td><?= $m['contact'] ?></td>
               </tr>
               <?php endwhile; ?>
            </tbody>
         </table>
         <?php endif; ?>
      </div>
   </div>
</div>
</body>
</html>
