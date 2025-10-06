<?php
$mysqli = include 'database.php';
session_start();


if ($_SESSION['role'] !== 'Member') {
    die("Access denied.");
}

$user_id = $_SESSION['user_id'];

$attendance = $conn->query("SELECT a.date, a.status, a.time 
    FROM attendance a 
    WHERE a.member_id = $user_id 
    ORDER BY a.date DESC");
?>

<h2>My Cell Group Attendance</h2>
<table class="attendance-table">
    <tr><th>Date</th><th>Status</th><th>Time</th></tr>
    <?php while ($row = $attendance->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['date']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td><?= htmlspecialchars($row['time']) ?></td>
        </tr>
    <?php endwhile; ?>
</table>
