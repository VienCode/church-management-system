<?php
include_once __DIR__ . '/../database.php';
include_once __DIR__ . '/../auth_check.php';
restrict_to_roles([ROLE_MEMBER]);

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT m.meeting_date, m.title, a.status
    FROM cell_group_attendance a
    JOIN cell_group_meetings m ON m.id = a.meeting_id
    WHERE a.member_id = ?
    ORDER BY m.meeting_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
  <h2>🧾 My Attendance Summary</h2>
  <?php if (empty($attendance)): ?>
    <p>No attendance records found.</p>
  <?php else: ?>
    <table class="cellgroup-table">
      <tr><th>Date</th><th>Meeting</th><th>Status</th></tr>
      <?php foreach ($attendance as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['meeting_date']) ?></td>
          <td><?= htmlspecialchars($a['title']) ?></td>
          <td style="font-weight:600; color:<?= 
              $a['status'] === 'Present' ? '#28a745' :
              ($a['status'] === 'Late' ? '#f39c12' : '#dc3545')
          ?>"><?= htmlspecialchars($a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
