<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER]);

$leader_email = $_SESSION['email'] ?? null;

// Get leader info
$stmt = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE email = ?");
$stmt->bind_param("s", $leader_email);
$stmt->execute();
$leader = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leader) {
    echo "<div style='background:#ffe6e6;color:#a11b1b;padding:15px;border-radius:8px;'>❌ This leader is not registered in the leaders table.</div>";
    exit();
}

$leader_id = $leader['leader_id'];

// Get group info
$stmt = $mysqli->prepare("SELECT id, group_name FROM cell_groups WHERE leader_id = ?");
$stmt->bind_param("i", $leader_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<div style='background:#ffe6e6;color:#a11b1b;padding:15px;border-radius:8px;'>❌ No cell group assigned to you yet.</div>";
    exit();
}

$group_id = $group['id'];

// Handle new meeting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meeting'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['meeting_date'];

    $stmt = $mysqli->prepare("INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $group_id, $title, $description, $date);
    $stmt->execute();
    $stmt->close();

    $msg = "✅ Meeting created successfully!";
}

// Fetch group members
$members = $mysqli->prepare("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email
    FROM cell_group_members cgm
    JOIN users u ON cgm.member_id = u.id
    WHERE cgm.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members->bind_param("i", $group_id);
$members->execute();
$members_result = $members->get_result();

// Fetch meetings
$meetings = $mysqli->prepare("
    SELECT id, title, description, meeting_date 
    FROM cell_group_meetings 
    WHERE cell_group_id = ? 
    ORDER BY meeting_date DESC
");
$meetings->bind_param("i", $group_id);
$meetings->execute();
$meetings_result = $meetings->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group - Leader View</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff;padding:20px;border-radius:10px;margin:30px auto;max-width:1150px;box-shadow:0 2px 10px rgba(0,0,0,.08); }
table { width:100%;border-collapse:collapse;margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; }
th { background:#0271c0; color:white; }
h2 { color:#0271c0; }
.btn { background:#0271c0; color:white; padding:8px 14px; border:none; border-radius:6px; cursor:pointer; }
.btn:hover { background:#02589b; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__.'/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h2>📋 <?= htmlspecialchars($group['group_name']) ?> - Leader Panel</h2>
            <?php if(isset($msg)): ?><div class="success"><?= $msg ?></div><?php endif; ?>

            <h3>🗓️ Add New Meeting</h3>
            <form method="POST">
                <input type="text" name="title" placeholder="Meeting Title" required>
                <textarea name="description" placeholder="Description" required></textarea>
                <input type="date" name="meeting_date" required>
                <button type="submit" name="add_meeting" class="btn">Add Meeting</button>
            </form>

            <h3>👥 Members</h3>
            <table>
                <thead><tr><th>User Code</th><th>Name</th><th>Email</th></tr></thead>
                <tbody>
                    <?php if($members_result->num_rows == 0): ?>
                        <tr><td colspan="3">No members assigned to this group yet.</td></tr>
                    <?php else: while($m = $members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $m['user_code'] ?></td>
                            <td><?= $m['fullname'] ?></td>
                            <td><?= $m['email'] ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <h3>📅 Meetings</h3>
            <table>
                <thead><tr><th>Title</th><th>Description</th><th>Date</th><th>Attendance</th></tr></thead>
                <tbody>
                    <?php if($meetings_result->num_rows == 0): ?>
                        <tr><td colspan="4">No meetings found.</td></tr>
                    <?php else: while($meeting = $meetings_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($meeting['title']) ?></td>
                            <td><?= htmlspecialchars($meeting['description']) ?></td>
                            <td><?= htmlspecialchars($meeting['meeting_date']) ?></td>
                            <td><a href="cell_group_attendance.php?meeting_id=<?= $meeting['id'] ?>" class="btn">Mark Attendance</a></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
