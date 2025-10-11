<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]); // Leaders and Admins only

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) {
    echo "<h2 style='color:red; text-align:center;'>❌ Invalid meeting ID.</h2>";
    exit;
}

$current_email = $_SESSION['email'] ?? null;
$current_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];
$current_contact = $_SESSION['contact'] ?? '';

// ✅ STEP 1: Find leader record
$stmt = $mysqli->prepare("SELECT leader_id, leader_name, email FROM leaders WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
$stmt->bind_param("s", $current_email);
$stmt->execute();
$leader = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ✅ STEP 2: Auto-register leader if missing
if (!$leader && $_SESSION['role_id'] == ROLE_LEADER) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $insert->bind_param("sss", $current_name, $current_email, $current_contact);
    $insert->execute();
    $insert->close();

    // Refresh the page so it re-checks with new leader ID
    header("Location: cell_group_attendance.php?meeting_id=" . urlencode($meeting_id));
    exit;
}

// ✅ STEP 3: Handle missing leader case
if (!$leader) {
    echo "<h2 style='text-align:center; color:red;'>❌ You are not registered as a leader.</h2>";
    exit;
}

$leader_id = $leader['leader_id'];

// ✅ STEP 4: Verify meeting belongs to this leader
$query = $mysqli->prepare("
    SELECT m.id AS meeting_id, m.title, m.description, m.meeting_date, c.group_name
    FROM cell_group_meetings m
    JOIN cell_groups c ON m.cell_group_id = c.id
    WHERE m.id = ? AND c.leader_id = ?
");
$query->bind_param("ii", $meeting_id, $leader_id);
$query->execute();
$meeting = $query->get_result()->fetch_assoc();
$query->close();

if (!$meeting) {
    echo "<h2 style='color:red; text-align:center;'>⚠️ This meeting is not assigned to your group.</h2>";
    exit;
}

// ✅ STEP 5: Fetch members of this leader’s cell group
$members = $mysqli->query("
    SELECT u.id AS member_id, CONCAT(u.firstname, ' ', u.lastname) AS fullname
    FROM cell_group_members m
    JOIN users u ON m.member_id = u.id
    JOIN cell_groups cg ON m.cell_group_id = cg.id
    WHERE cg.leader_id = $leader_id
    ORDER BY u.lastname ASC
");

// ✅ STEP 6: Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    foreach ($_POST['attendance'] as $member_id => $status) {
        $stmt = $mysqli->prepare("
            INSERT INTO cell_group_attendance (meeting_id, member_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("iis", $meeting_id, $member_id, $status);
        $stmt->execute();
        $stmt->close();
    }
    $success = "✅ Attendance saved successfully!";
}

// ✅ STEP 7: Fetch attendance records
$attendance_records = [];
$res = $mysqli->query("SELECT member_id, status FROM cell_group_attendance WHERE meeting_id = $meeting_id");
while ($row = $res->fetch_assoc()) {
    $attendance_records[$row['member_id']] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance | <?= htmlspecialchars($meeting['title']) ?></title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 900px;
    margin: 30px auto;
}
h1 { color: #0271c0; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: center; }
th { background: #0271c0; color: white; }
select { padding: 6px; border-radius: 6px; }
button.save-btn {
    background: #0271c0;
    color: white;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 15px;
}
button.save-btn:hover { background: #02589b; }
.success { background: #e6ffed; color: #256029; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="container">
            <h1>📝 Mark Attendance</h1>
            <p><strong>Meeting:</strong> <?= htmlspecialchars($meeting['title']) ?><br>
               <strong>Date:</strong> <?= htmlspecialchars($meeting['meeting_date']) ?><br>
               <strong>Group:</strong> <?= htmlspecialchars($meeting['group_name']) ?></p>

            <?php if (!empty($success)): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        while ($m = $members->fetch_assoc()):
                            $status = $attendance_records[$m['member_id']] ?? '';
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($m['fullname']) ?></td>
                            <td>
                                <select name="attendance[<?= $m['member_id'] ?>]" required>
                                    <option value="" <?= $status == '' ? 'selected' : '' ?>>Select</option>
                                    <option value="Present" <?= $status == 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Absent" <?= $status == 'Absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="Late" <?= $status == 'Late' ? 'selected' : '' ?>>Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <center><button type="submit" name="save_attendance" class="save-btn">💾 Save Attendance</button></center>
            </form>
        </div>
    </div>
</div>
</body>
</html>
