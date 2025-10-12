<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_LEADER, ROLE_ADMIN]);

$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$user_code = $_SESSION['user_code'] ?? null;
$fullname = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

// ✅ 1️⃣ Ensure the leader exists in leaders table
$check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ? LIMIT 1");
$check_leader->bind_param("s", $user_email);
$check_leader->execute();
$leader_data = $check_leader->get_result()->fetch_assoc();
$check_leader->close();

if (!$leader_data) {
    $insert = $mysqli->prepare("
        INSERT INTO leaders (leader_name, email, contact, status, created_at)
        VALUES (?, ?, (SELECT contact FROM users WHERE email = ? LIMIT 1), 'active', NOW())
    ");
    $insert->bind_param("sss", $fullname, $user_email, $user_email);
    $insert->execute();
    $insert->close();
}

// ✅ 2️⃣ Fetch leader info
$leader_stmt = $mysqli->prepare("SELECT * FROM leaders WHERE email = ? LIMIT 1");
$leader_stmt->bind_param("s", $user_email);
$leader_stmt->execute();
$leader = $leader_stmt->get_result()->fetch_assoc();
$leader_stmt->close();

if (!$leader) {
    echo "<h2 style='text-align:center;color:red;'>❌ Leader profile missing. Contact admin.</h2>";
    exit;
}

$leader_id = $leader['leader_id'];

// ✅ 3️⃣ Ensure cell group exists
$group_stmt = $mysqli->prepare("SELECT id, group_name FROM cell_groups WHERE leader_id = ? AND status = 'active' LIMIT 1");
$group_stmt->bind_param("i", $leader_id);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();
$group_stmt->close();

if (!$group) {
    $group_name = "$fullname's Cell Group";
    $create_group = $mysqli->prepare("
        INSERT INTO cell_groups (group_name, leader_id, status, created_at)
        VALUES (?, ?, 'active', NOW())
    ");
    $create_group->bind_param("si", $group_name, $leader_id);
    $create_group->execute();
    $group_id = $create_group->insert_id;
    $create_group->close();
} else {
    $group_id = $group['id'];
    $group_name = $group['group_name'];
}

// ✅ 4️⃣ Auto-sync members (adds all users with this leader_id)
$sync = $mysqli->prepare("
    SELECT user_code FROM users 
    WHERE leader_id = ? AND role_id = 3
");
$sync->bind_param("i", $leader_id);
$sync->execute();
$result = $sync->get_result();

while ($u = $result->fetch_assoc()) {
    $code = $u['user_code'];
    $check = $mysqli->prepare("SELECT id FROM cell_group_members WHERE cell_group_id = ? AND user_code = ?");
    $check->bind_param("is", $group_id, $code);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        $insert = $mysqli->prepare("
            INSERT INTO cell_group_members (cell_group_id, user_code)
            VALUES (?, ?)
        ");
        $insert->bind_param("is", $group_id, $code);
        $insert->execute();
        $insert->close();
    }
}
$sync->close();

// ✅ 5️⃣ Handle meeting creation
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

// ✅ 6️⃣ Fetch members excluding the leader herself
$members_stmt = $mysqli->prepare("
    SELECT u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.email, u.contact
    FROM cell_group_members m
    JOIN users u ON m.user_code = u.user_code
    WHERE m.cell_group_id = ? AND u.email != ?
    ORDER BY u.lastname ASC
");
$members_stmt->bind_param("is", $group_id, $user_email);
$members_stmt->execute();
$members = $members_stmt->get_result();
$members_stmt->close();

// ✅ 7️⃣ Fetch meetings
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📅 My Cell Group (Leader)</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.cell-container {background:#fff;padding:25px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);max-width:1100px;margin:30px auto;}
.section-title {color:#0271c0;border-bottom:2px solid #0271c0;padding-bottom:5px;margin-bottom:15px;}
table {width:100%;border-collapse:collapse;margin-top:15px;}
th,td {padding:10px;border-bottom:1px solid #e6e6e6;text-align:center;}
th {background:#0271c0;color:white;}
.save-btn {background:#0271c0;color:white;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:600;}
.save-btn:hover {background:#02589b;}
.success {background:#e6ffed;color:#256029;padding:12px;border-radius:8px;margin-bottom:15px;font-weight:600;}
</style>
</head>

<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="cell-container">
<h1>📅 My Cell Group</h1>
<p>Welcome, <strong><?= htmlspecialchars($leader['leader_name']) ?></strong>! You’re managing <strong><?= htmlspecialchars($group_name) ?></strong>.</p>

<?php if (!empty($success)): ?>
<div class="success"><?= $success ?></div>
<?php endif; ?>

<!-- Members -->
<section style="margin-top:30px;">
<h2 class="section-title">👥 Members</h2>
<?php if ($members->num_rows === 0): ?>
<p>No members assigned to your group yet.</p>
<?php else: ?>
<table>
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
