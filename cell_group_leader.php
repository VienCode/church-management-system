<?php
// cell_group_leader.php
session_start();
include 'database.php'; // must set $mysqli
// role check (accept both string and numeric role)
if (!isset($_SESSION['role']) || !isset($_SESSION['id'])) {
    header('Location: login.php'); exit;
}
$is_leader = ($_SESSION['role'] === 'leader' || $_SESSION['role'] === 2);
if (!$is_leader) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$leader_user_id = intval($_SESSION['id']);

// find the cell group this leader owns
$stmt = $mysqli->prepare("SELECT id, group_name FROM cell_groups WHERE leader_id = ?");
$stmt->bind_param('i', $leader_user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    // No group assigned
    $group = null;
}

// Handle create meeting form
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $meeting_date = $_POST['meeting_date'] ?? '';

    if (!$group) { $errors[] = "You don't have a cell group assigned."; }
    if ($title === '') $errors[] = "Title is required.";
    if ($meeting_date === '') $errors[] = "Meeting date is required.";
    if ($meeting_date > date('Y-m-d', strtotime('+2 years'))) $errors[] = "Meeting date too far in the future.";

    if (empty($errors)) {
        $ins = $mysqli->prepare("INSERT INTO cell_group_meetings (cell_group_id, title, description, meeting_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isss', $group['id'], $title, $description, $meeting_date);
        $ins->execute();
        $ins->close();
        $success = "Meeting created.";
    }
}

// fetch members for that group
$members = [];
if ($group) {
    $stmt = $mysqli->prepare("
        SELECT u.id, u.user_code, u.firstname, u.lastname, u.email
        FROM users u
        JOIN cell_group_members cm ON cm.member_id = u.id
        WHERE cm.cell_group_id = ?
        ORDER BY u.lastname, u.firstname
    ");
    $stmt->bind_param('i', $group['id']);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // fetch meetings
    $stmt = $mysqli->prepare("SELECT id, title, description, meeting_date FROM cell_group_meetings WHERE cell_group_id = ? ORDER BY meeting_date DESC");
    $stmt->bind_param('i', $group['id']);
    $stmt->execute();
    $meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $meetings = [];
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>My Cell Group - Leader</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { max-width:1100px; margin:30px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.grid { display:grid; grid-template-columns: 1fr 1fr; gap:18px; }
.section { padding:12px; border-radius:8px; background:#fafafa; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
form input, form textarea, form select { width:100%; padding:8px; margin:6px 0; border-radius:6px; border:1px solid #ccc; }
.button { background:#0271c0; color:#fff; padding:8px 12px; border:none; border-radius:6px; cursor:pointer; }
.notice { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:6px; margin-bottom:10px; }
.error { background:#ffecec; color:#9b2a2a; padding:10px; border-radius:6px; margin-bottom:10px; }
</style>
</head>
<body>
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
    <h1>Cell Group — Leader Panel</h1>

    <?php if ($success): ?><div class="notice"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($errors): ?><div class="error"><?=implode('<br>', array_map('htmlspecialchars',$errors))?></div><?php endif; ?>

    <?php if (!$group): ?>
        <div class="section">
            <h3>You are not assigned to any cell group yet.</h3>
            <p>Please contact admin to assign you to a group.</p>
        </div>
    <?php else: ?>
    <div style="margin-bottom:14px;"><strong>Group:</strong> <?=htmlspecialchars($group['group_name'])?></div>

    <div class="grid">
        <div class="section">
            <h3>Add Meeting</h3>
            <form method="POST">
                <label>Title</label>
                <input name="title" required>
                <label>Meeting Date</label>
                <input type="date" name="meeting_date" required max="<?=date('Y-m-d', strtotime('+2 years'))?>">
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
                <button class="button" name="create_meeting" type="submit">Create Meeting</button>
            </form>
        </div>

        <div class="section">
            <h3>Members (<?=count($members)?>)</h3>
            <table class="table">
                <thead><tr><th>User Code</th><th>Name</th><th>Email</th></tr></thead>
                <tbody>
                <?php foreach($members as $m): ?>
                    <tr>
                        <td><?=htmlspecialchars($m['user_code']??'')?></td>
                        <td><?=htmlspecialchars($m['firstname'].' '.$m['lastname'])?></td>
                        <td><?=htmlspecialchars($m['email'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section" style="margin-top:18px;">
        <h3>Meetings</h3>
        <?php if (empty($meetings)): ?>
            <p>No meetings yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Date</th><th>Title</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($meetings as $meet): ?>
                    <tr>
                        <td><?=htmlspecialchars(date('M d, Y', strtotime($meet['meeting_date'])))?></td>
                        <td><?=htmlspecialchars($meet['title'])?></td>
                        <td><?=htmlspecialchars($meet['description'])?></td>
                        <td>
                            <a class="button" href="cell_group_attendance.php?meeting_id=<?=intval($meet['id'])?>">Mark Attendance</a>
                            <a class="button" style="background:#6c757d" href="cell_group_attendance.php?meeting_id=<?=intval($meet['id'])?>&view=1">View Attendance</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
</div>
</body>
</html>
