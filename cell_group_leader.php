<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_email = $_SESSION['email'] ?? '';
$fullname = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];

// ✅ Fetch leader ID
$leader_stmt = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE email = ? AND status = 'active' LIMIT 1");
$leader_stmt->bind_param("s", $user_email);
$leader_stmt->execute();
$leader = $leader_stmt->get_result()->fetch_assoc();
$leader_stmt->close();

if (!$leader) {
    echo "<h2 style='text-align:center;color:red;'>❌ You are not registered as an active leader.</h2>";
    exit;
}

$leader_id = $leader['leader_id'];

// ✅ Fetch the leader's cell group
$group_stmt = $mysqli->prepare("SELECT id, group_name FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center;color:#555;'>ℹ️ You are not yet assigned to any Cell Group.</h2>";
    exit;
}

$group_id = $group['id'];
$group_name = $group['group_name'];

// ✅ Add meeting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meeting'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $date = $_POST['meeting_date'];

    $stmt = $mysqli->prepare("INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $group_id, $title, $desc, $date);
    $stmt->execute();
    $stmt->close();
    $success = "✅ Meeting added successfully!";
}

// ✅ Fetch meetings
$meetings = $mysqli->query("SELECT * FROM cell_group_meetings WHERE cell_group_id = $group_id ORDER BY meeting_date DESC");

// ✅ Fetch members
$members = $mysqli->query("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email, u.contact
    FROM cell_group_members m
    JOIN users u ON m.member_id = u.id
    WHERE m.cell_group_id = $group_id
    ORDER BY u.lastname ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📅 My Cell Group</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container { background:#fff;padding:25px;border-radius:12px;max-width:1100px;margin:30px auto;box-shadow:0 2px 10px rgba(0,0,0,.1);}
.save-btn{background:#0271c0;color:#fff;padding:8px 12px;border:none;border-radius:8px;cursor:pointer;}
.section-title{color:#0271c0;border-bottom:2px solid #0271c0;padding-bottom:5px;margin-bottom:10px;}
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="cell-container">
<h1>📅 <?= htmlspecialchars($group_name) ?></h1>
<?php if(isset($success)) echo "<div class='success'>$success</div>"; ?>
<form method="POST">
<h2 class="section-title">➕ Add New Meeting</h2>
<input type="text" name="title" placeholder="Meeting Title" required style="width:100%;padding:8px;"><br><br>
<textarea name="description" placeholder="Description" style="width:100%;padding:8px;"></textarea><br><br>
<input type="date" name="meeting_date" required><br><br>
<button type="submit" name="add_meeting" class="save-btn">💾 Save Meeting</button>
</form>

<h2 class="section-title">📋 Meetings</h2>
<?php if ($meetings->num_rows==0): ?>
<p>No meetings yet.</p>
<?php else: ?>
<table>
<tr><th>Date</th><th>Title</th><th>Description</th><th>Actions</th></tr>
<?php while($m=$meetings->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date']))) ?></td>
<td><?= htmlspecialchars($m['title']) ?></td>
<td><?= htmlspecialchars($m['description']) ?></td>
<td><a href="cell_group_attendance.php?meeting_id=<?= $m['id'] ?>" class="save-btn">📝 Mark Attendance</a></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>

<h2 class="section-title">👥 Members</h2>
<?php if ($members->num_rows==0): ?>
<p>No members assigned yet.</p>
<?php else: ?>
<table><tr><th>Code</th><th>Name</th><th>Email</th><th>Contact</th></tr>
<?php while($m=$members->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($m['user_code']) ?></td>
<td><?= htmlspecialchars($m['fullname']) ?></td>
<td><?= htmlspecialchars($m['email']) ?></td>
<td><?= htmlspecialchars($m['contact']) ?></td>
</tr>
<?php endwhile; ?></table>
<?php endif; ?>
</div></div></div></body></html>
