<?php
$mysqli = include 'database.php';
session_start();

if ($_SESSION['role'] !== 'Leader') {
    die("Access denied.");
}

$leader_id = $_SESSION['user_id'];

// Get leader’s cell group
$group = $conn->query("SELECT * FROM cell_groups WHERE leader_id = $leader_id")->fetch_assoc();
$group_id = $group['id'];

// Get group members
$members = $conn->query("SELECT u.id, u.name 
    FROM users u 
    JOIN cell_group_members cgm ON u.id = cgm.member_id
    WHERE cgm.group_id = $group_id");
?>

<h2>Cell Group Attendance - <?= htmlspecialchars($group['name']) ?></h2>
<form method="POST" action="save_cellgroup_attendance.php">
    <input type="hidden" name="group_id" value="<?= $group_id ?>">
    <table class="attendance-table">
        <tr><th>Member</th><th>Present</th><th>Absent</th><th>Time</th></tr>
        <?php while ($m = $members->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><input type="radio" name="attendance[<?= $m['id'] ?>]" value="Present"></td>
            <td><input type="radio" name="attendance[<?= $m['id'] ?>]" value="Absent"></td>
            <td><input type="time" name="time[<?= $m['id'] ?>]" class="time-input" disabled></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <button type="submit" class="save-btn">Save Attendance</button>
</form>
