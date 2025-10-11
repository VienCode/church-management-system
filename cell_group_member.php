<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER, ROLE_ADMIN, ROLE_LEADER]); // Members, Leaders, Admins can view

$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;

// ✅ Step 1: Check if the member belongs to a cell group
$query = $mysqli->prepare("
    SELECT 
        cg.id AS cell_group_id, 
        cg.group_name,
        l.leader_name,
        l.email AS leader_email
    FROM cell_group_members m
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE m.member_id = ?
");
$query->bind_param("i", $user_id);
$query->execute();
$group = $query->get_result()->fetch_assoc();
$query->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:red; margin-top:40px;'>❌ You are not currently assigned to any cell group.</h2>";
    exit;
}

$cell_group_id = $group['cell_group_id'];
$group_name = $group['group_name'];
$leader_name = $group['leader_name'];
$leader_email = $group['leader_email'];

// ✅ Step 2: Fetch all meetings for this group
$meetings_stmt = $mysqli->prepare("
    SELECT 
        m.id AS meeting_id,
        m.title,
        m.description,
        m.meeting_date,
        IFNULL(a.status, 'Not Marked') AS attendance_status
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a 
        ON m.id = a.meeting_id AND a.member_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY m.meeting_date DESC
");
$meetings_stmt->bind_param("ii", $user_id, $cell_group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();

// ✅ Step 3: Fetch group members for display
$members_stmt = $mysqli->prepare("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email
    FROM cell_group_members m
    JOIN users u ON m.member_id = u.id
    WHERE m.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("i", $cell_group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Cell Group | Unity Christian Fellowship</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1100px;
    margin: 30px auto;
}
h1 { color: #0271c0; margin-bottom: 10px; }
h2 { margin-top: 25px; color: #333; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: center; }
th { background: #0271c0; color: white; }
.status-present { color: green; font-weight: bold; }
.status-absent { color: red; font-weight: bold; }
.status-late { color: orange; font-weight: bold; }
.status-not-marked { color: gray; font-style: italic; }
.member-table, .meeting-table { margin-bottom: 25px; }
</style>
</head>

<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

   <div class="content-area">
      <div class="container">
         <h1>👥 My Cell Group</h1>
         <p>Welcome, <?= htmlspecialchars($_SESSION['firstname']) ?>! You’re part of <strong><?= htmlspecialchars($group_name) ?></strong>.</p>
         <p><strong>Leader:</strong> <?= htmlspecialchars($leader_name) ?> | <strong>Email:</strong> <?= htmlspecialchars($leader_email) ?></p>

         <!-- Group Members -->
         <h2>Members</h2>
         <?php if ($members->num_rows === 0): ?>
            <p>No other members listed yet.</p>
         <?php else: ?>
            <table class="member-table">
               <thead>
                  <tr><th>Code</th><th>Name</th><th>Email</th></tr>
               </thead>
               <tbody>
                  <?php while ($m = $members->fetch_assoc()): ?>
                     <tr>
                        <td><?= htmlspecialchars($m['user_code']) ?></td>
                        <td><?= htmlspecialchars($m['fullname']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                     </tr>
                  <?php endwhile; ?>
               </tbody>
            </table>
         <?php endif; ?>

         <!-- Meeting List -->
         <h2>📅 Meetings</h2>
         <?php if ($meetings->num_rows === 0): ?>
            <p>No meetings have been scheduled yet for your group.</p>
         <?php else: ?>
            <table class="meeting-table">
               <thead>
                  <tr><th>Date</th><th>Title</th><th>Description</th><th>Your Attendance</th></tr>
               </thead>
               <tbody>
                  <?php while ($m = $meetings->fetch_assoc()): ?>
                     <tr>
                        <td><?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date']))) ?></td>
                        <td><?= htmlspecialchars($m['title']) ?></td>
                        <td><?= htmlspecialchars($m['description']) ?></td>
                        <td class="
                           <?php
                           switch($m['attendance_status']) {
                               case 'Present': echo 'status-present'; break;
                               case 'Absent': echo 'status-absent'; break;
                               case 'Late': echo 'status-late'; break;
                               default: echo 'status-not-marked'; break;
                           } ?>">
                           <?= htmlspecialchars($m['attendance_status']) ?>
                        </td>
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
