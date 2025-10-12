<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER, ROLE_ADMIN]);

$user_code = $_SESSION['user_code'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

// ✅ Find the member’s assigned cell group
$group_stmt = $mysqli->prepare("
    SELECT 
        cg.id AS group_id,
        cg.group_name,
        l.leader_name,
        l.email AS leader_email,
        l.contact AS leader_contact
    FROM users u
    LEFT JOIN cell_group_members m ON u.user_code = m.user_code
    LEFT JOIN cell_groups cg ON m.cell_group_id = cg.id
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE u.user_code = ?
    LIMIT 1
");
$group_stmt->bind_param("s", $user_code);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

// If member has no group
if (!$group || !$group['group_id']) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not assigned to any Cell Group yet. Please wait for your leader or admin to assign you.</h2>";
    exit;
}

$group_id = $group['group_id'];
$group_name = $group['group_name'];
$leader_name = $group['leader_name'];
$leader_email = $group['leader_email'];
$leader_contact = $group['leader_contact'];

// ✅ Fetch all meetings for the member’s cell group
$meetings_stmt = $mysqli->prepare("
    SELECT 
        m.id,
        m.title,
        m.description,
        m.meeting_date,
        COALESCE(a.status, 'Not Marked') AS attendance_status
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a 
        ON m.id = a.meeting_id AND a.user_code = ?
    WHERE m.cell_group_id = ?
    ORDER BY m.meeting_date DESC
");
$meetings_stmt->bind_param("si", $user_code, $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>👥 My Cell Group | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
.section-title { color:#0271c0; border-bottom:2px solid #0271c0; padding-bottom:5px; margin-bottom:15px; }
.info-box {
    background: #f6f8fb;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #e6e6e6;
    text-align: center;
}
th { background: #0271c0; color: white; }
.status {
    font-weight: bold;
    padding: 6px 10px;
    border-radius: 6px;
}
.present { background: #28a745; color: white; }
.absent { background: #dc3545; color: white; }
.late { background: #ffc107; color: black; }
.not-marked { background: #ccc; color: black; }
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-area">
<div class="cell-container">
<h1>👥 My Cell Group</h1>
<p>Welcome, <strong><?= htmlspecialchars($fullname) ?></strong>!</p>

<!-- Cell Group Info -->
<div class="info-box">
    <h2 class="section-title">📘 Group Information</h2>
    <p><strong>Cell Group:</strong> <?= htmlspecialchars($group_name) ?></p>
    <p><strong>Leader:</strong> <?= htmlspecialchars($leader_name) ?></p>
    <p><strong>Contact:</strong> <?= htmlspecialchars($leader_contact) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($leader_email) ?></p>
</div>

<!-- Meeting List -->
<div>
<h2 class="section-title">📅 Meetings & Attendance</h2>
<?php if ($meetings->num_rows === 0): ?>
    <p>No meetings have been scheduled yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Description</th>
                <th>Your Attendance</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($m = $meetings->fetch_assoc()): ?>
                <?php
                    $status_class = match($m['attendance_status']) {
                        'Present' => 'present',
                        'Absent' => 'absent',
                        'Late' => 'late',
                        default => 'not-marked'
                    };
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date']))) ?></td>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td><?= htmlspecialchars($m['description']) ?></td>
                    <td><span class="status <?= $status_class ?>"><?= htmlspecialchars($m['attendance_status']) ?></span></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
</div>
</div>
</div>
</body>
</html>
