<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$user_code = $_SESSION['user_code'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

// ✅ Ensure leader is registered in `leaders`
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

$leader_id = $leader_data['leader_id'] ?? null;

// ✅ Get meeting ID
$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) {
    echo "<h2 style='color:red;text-align:center;'>❌ Invalid meeting access.</h2>";
    exit;
}

// ✅ Verify meeting belongs to this leader
$meeting_stmt = $mysqli->prepare("
    SELECT m.id, m.title, m.meeting_date, cg.id AS group_id, cg.group_name
    FROM cell_group_meetings m
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    WHERE m.id = ? AND cg.leader_id = ?
    LIMIT 1
");
$meeting_stmt->bind_param("ii", $meeting_id, $leader_id);
$meeting_stmt->execute();
$meeting = $meeting_stmt->get_result()->fetch_assoc();
$meeting_stmt->close();

if (!$meeting) {
    echo "<h2 style='color:red;text-align:center;'>❌ This meeting doesn’t belong to your group.</h2>";
    exit;
}

$group_id = $meeting['group_id'];
$group_name = $meeting['group_name'];

// ✅ Handle attendance save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    foreach ($_POST['attendance'] as $user_code => $status) {
        // Ensure valid status
        if (!in_array($status, ['Present', 'Absent', 'Late'])) continue;

        // Upsert attendance
        $stmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance (meeting_id, user_code, status, marked_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_at = NOW()
        ");
        $stmt->bind_param("iss", $meeting_id, $user_code, $status);
        $stmt->execute();
        $stmt->close();
    }

    $success = "✅ Attendance updated successfully!";
}

// ✅ Fetch members of this group
$members_stmt = $mysqli->prepare("
    SELECT 
        u.user_code,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        u.email,
        COALESCE(a.status, 'Not Marked') AS attendance_status
    FROM cell_group_members m
    JOIN users u ON m.user_code = u.user_code
    LEFT JOIN cell_group_attendance a 
        ON a.user_code = u.user_code AND a.meeting_id = ?
    WHERE m.cell_group_id = ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("ii", $meeting_id, $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result();
$members_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📝 Mark Attendance | <?= htmlspecialchars($meeting['title']) ?></title>
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
.save-btn {
    background:#0271c0; color:white; border:none;
    padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600;
}
.save-btn:hover { background:#02589b; }
.success {
    background:#e6ffed; color:#256029; padding:12px;
    border-radius:8px; margin-bottom:15px; font-weight:600;
}
.status-select {
    padding:6px 10px; border-radius:6px; border:1px solid #ccc;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="cell-container">
            <h1>📝 Mark Attendance</h1>
            <p>Meeting: <strong><?= htmlspecialchars($meeting['title']) ?></strong></p>
            <p>Date: <?= htmlspecialchars(date('F j, Y', strtotime($meeting['meeting_date']))) ?></p>
            <p>Group: <?= htmlspecialchars($group_name) ?></p>

            <?php if (!empty($success)): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <table>
                    <thead>
                        <tr><th>#</th><th>User Code</th><th>Full Name</th><th>Email</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($m = $members->fetch_assoc()):
                            $current_status = $m['attendance_status'];
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($m['user_code']) ?></td>
                            <td><?= htmlspecialchars($m['fullname']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td>
                                <select name="attendance[<?= htmlspecialchars($m['user_code']) ?>]" class="status-select">
                                    <option value="Not Marked" <?= $current_status == 'Not Marked' ? 'selected' : '' ?>>Not Marked</option>
                                    <option value="Present" <?= $current_status == 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Absent" <?= $current_status == 'Absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="Late" <?= $current_status == 'Late' ? 'selected' : '' ?>>Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <center><button type="submit" name="save_attendance" class="save-btn" style="margin-top:15px;">💾 Save Attendance</button></center>
            </form>
        </div>
    </div>
</div>
</body>
</html>
