<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_email = $_SESSION['email'] ?? null;
$user_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];

// ✅ Ensure leader exists
$stmt = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE email = ? AND status = 'active' LIMIT 1");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$leader = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ✅ Auto-create leader if missing
if (!$leader && $_SESSION['role_id'] == ROLE_LEADER) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, status, created_at)
        VALUES (?, ?, (SELECT contact FROM users WHERE email = ? LIMIT 1), 'active', NOW())
    ");
    $insert->bind_param("sss", $user_name, $user_email, $user_email);
    $insert->execute();
    $insert->close();

    // Reload after creation
    header("Location: cell_group_leader.php");
    exit;
}

if (!$leader) {
    echo "<h2 style='color:red;text-align:center;'>❌ Leader not found or inactive. Contact admin.</h2>";
    exit;
}

$leader_id = $leader['leader_id'];

// ✅ Ensure leader has a cell group
$stmt = $mysqli->prepare("SELECT id, group_name FROM cell_groups WHERE leader_id = ? AND status = 'active'");
$stmt->bind_param("i", $leader_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    $group_name = $leader['leader_name'] . "'s Cell Group";
    $insert_group = $mysqli->prepare("INSERT INTO cell_groups (group_name, leader_id, status) VALUES (?, ?, 'active')");
    $insert_group->bind_param("si", $group_name, $leader_id);
    $insert_group->execute();
    $insert_group->close();

    header("Location: cell_group_leader.php");
    exit;
}

$group_id = $group['id'];
$group_name = $group['group_name'];

// ✅ Fetch members
$members = $mysqli->query("
    SELECT u.user_code, CONCAT(u.firstname,' ',u.lastname) AS fullname, u.email, u.contact
    FROM users u
    WHERE u.leader_id = $leader_id
");

// ✅ Fetch meetings
$meetings = $mysqli->query("
    SELECT id, title, description, meeting_date
    FROM cell_group_meetings
    WHERE cell_group_id = $group_id
    ORDER BY meeting_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>📅 My Cell Group | UCF</title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="cell-container">
            <h1>📅 <?= htmlspecialchars($group_name) ?></h1>
            <p>Welcome, <strong><?= htmlspecialchars($leader['leader_name']) ?></strong>.</p>

            <form method="POST" action="add_meeting.php">
                <input type="hidden" name="cell_group_id" value="<?= $group_id ?>">
                <label>Meeting Title</label><br>
                <input type="text" name="title" required><br>
                <label>Description</label><br>
                <textarea name="description"></textarea><br>
                <label>Date</label><br>
                <input type="date" name="meeting_date" required><br>
                <button type="submit" class="save-btn">💾 Add Meeting</button>
            </form>

            <h2>👥 Members</h2>
            <?php if ($members->num_rows === 0): ?>
                <p>No members assigned yet.</p>
            <?php else: ?>
                <ul>
                    <?php while ($m = $members->fetch_assoc()): ?>
                        <li><?= htmlspecialchars($m['fullname']) ?> (<?= htmlspecialchars($m['user_code']) ?>)</li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>

            <h2>📅 Meetings</h2>
            <?php if ($meetings->num_rows === 0): ?>
                <p>No meetings yet.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Attendance</th></tr></thead>
                    <tbody>
                    <?php while ($meet = $meetings->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($meet['meeting_date']) ?></td>
                            <td><?= htmlspecialchars($meet['title']) ?></td>
                            <td><?= htmlspecialchars($meet['description']) ?></td>
                            <td><a href="cell_group_attendance.php?meeting_id=<?= $meet['id'] ?>">📝 Mark Attendance</a></td>
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
