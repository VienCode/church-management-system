<?php
include_once __DIR__ . '/../database.php';
include_once __DIR__ . '/../auth_check.php';
restrict_to_roles([ROLE_LEADER]);

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("
    SELECT m.title, m.meeting_date, m.description, c.group_name
    FROM cell_group_meetings m
    JOIN cell_groups c ON c.id = m.cell_group_id
    WHERE c.leader_id = (SELECT leader_id FROM leaders WHERE email = (SELECT email FROM users WHERE id=?))
      AND m.meeting_date >= CURDATE()
    ORDER BY m.meeting_date ASC
    LIMIT 3
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
  <h2>🗓️ Upcoming Meetings</h2>
  <?php if (empty($meetings)): ?>
    <p>No upcoming meetings scheduled.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($meetings as $m): ?>
        <li>
          <strong><?= htmlspecialchars($m['title']) ?></strong><br>
          <small><?= htmlspecialchars($m['meeting_date']) ?> — <?= htmlspecialchars($m['group_name']) ?></small><br>
          <em><?= htmlspecialchars($m['description']) ?></em>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
