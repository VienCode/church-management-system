<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_MEMBER, ROLE_ADMIN, ROLE_LEADER]); // Allow members & admins

$user_code = $_SESSION['user_code'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

if (!$user_code) {
    echo "<h2 style='text-align:center; color:red;'>❌ Session expired or missing user code.</h2>";
    exit;
}

/* ---------------------------------------------------
   1️⃣ FIND WHICH CELL GROUP THE MEMBER BELONGS TO
--------------------------------------------------- */
$sql = "
    SELECT cg.id AS group_id, cg.group_name, l.leader_name
    FROM cell_group_members m
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE m.user_code = ?
    AND cg.status = 'active'
    LIMIT 1
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $user_code);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<h2 style='text-align:center; color:#555;'>ℹ️ You are not assigned to any Cell Group yet. Please wait for your leader or admin to assign you.</h2>";
    exit;
}

$group_id = $group['group_id'];
$group_name = $group['group_name'];
$leader_name = $group['leader_name'];

/* ---------------------------------------------------
   2️⃣ FETCH MEETINGS OF THIS CELL GROUP
--------------------------------------------------- */
$meetings_stmt = $mysqli->prepare("
    SELECT id, title, description, meeting_date
    FROM cell_group_meetings
    WHERE cell_group_id = ?
    ORDER BY meeting_date DESC
");
$meetings_stmt->bind_param("i", $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result();
$meetings_stmt->close();

/* ---------------------------------------------------
   3️⃣ FETCH ATTENDANCE STATUS FOR THIS MEMBER
--------------------------------------------------- */
$attendance = [];
if ($meetings->num_rows > 0) {
    $att_stmt = $mysqli->prepare("
        SELECT meeting_id, status
        FROM cell_group_attendance
        WHERE user_code = ?
    ");
    $att_stmt->bind_param("s", $user_code);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    while ($row = $att_result->fetch_assoc()) {
        $attendance[$row['meeting_id']] = $row['status'];
    }
    $att_stmt->close();
}
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
    max-width: 1100px;
    margin: 30px auto;
}
.section-title {
    color: #0271c0;
    border-bottom: 2px solid #0271c0;
    padding-bottom: 5px;
    margin-bottom: 15px;
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
th {
    background: #0271c0;
    color: white;
}
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: bold;
}
.status-present { background: #e6ffed; color: #256029; }
.status-absent { background: #ffe6e6; color: #a94442; }
.status-late { background: #fff3cd; color: #856404; }
.status-notmarked { background: #f0f0f0; color: #555; }
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="cell-container">
            <h1>👥 My Cell Group</h1>
            <p>Welcome, <strong><?= htmlspecialchars($fullname) ?></strong>!</p>
            <p>You belong to <strong><?= htmlspecialchars($group_name) ?></strong> led by <strong><?= htmlspecialchars($leader_name) ?></strong>.</p>

            <section style="margin-top:30px;">
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
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($meeting = $meetings->fetch_assoc()): 
                                $status = $attendance[$meeting['id']] ?? 'Not Marked';
                                $status_class = strtolower(str_replace(' ', '', $status));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(date('F j, Y', strtotime($meeting['meeting_date']))) ?></td>
                                <td><?= htmlspecialchars($meeting['title']) ?></td>
                                <td><?= htmlspecialchars($meeting['description']) ?></td>
                                <td><span class="status-badge status-<?= $status_class ?>"><?= htmlspecialchars($status) ?></span></td>
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
