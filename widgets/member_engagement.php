<?php
include_once __DIR__ . '/../database.php';
include_once __DIR__ . '/../auth_check.php';
restrict_to_roles([ROLE_LEADER]);

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("
    SELECT u.firstname, u.lastname,
           SUM(a.status='Present') AS attended,
           COUNT(a.id) AS total
    FROM cell_group_members m
    JOIN users u ON u.id = m.member_id
    LEFT JOIN cell_group_attendance a ON a.member_id = m.member_id
    LEFT JOIN cell_group_meetings mt ON mt.id = a.meeting_id
    WHERE mt.cell_group_id = (SELECT cg.id FROM cell_groups cg 
                              WHERE cg.leader_id = (SELECT leader_id FROM leaders 
                              WHERE email = (SELECT email FROM users WHERE id=?)))
      AND MONTH(mt.meeting_date) = MONTH(CURDATE())
    GROUP BY u.id
    ORDER BY attended DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
  <h2>👥 Member Engagement (This Month)</h2>
  <?php if (empty($members)): ?>
    <p>No attendance data available yet.</p>
  <?php else: ?>
    <table class="cellgroup-table">
      <tr><th>Member</th><th>Attendance Rate</th></tr>
      <?php foreach ($members as $m):
          $rate = $m['total'] > 0 ? round(($m['attended'] / $m['total']) * 100, 1) : 0;
      ?>
      <tr>
        <td><?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?></td>
        <td><strong><?= $rate ?>%</strong></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
