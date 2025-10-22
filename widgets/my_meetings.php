<?php
include_once __DIR__ . '/../database.php';
include_once __DIR__ . '/../auth_check.php';
restrict_to_roles([ROLE_MEMBER]);

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT m.meeting_date, m.title, c.group_name
    FROM cell_group_meetings m
    JOIN cell_groups c ON c.id = m.cell_group_id
    JOIN cell_group_members g ON g.cell_group_id = c.id
    WHERE g.member_id = ?
    ORDER BY m.meeting_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
  <h2>📅 My Recent Cell Group Meetings</h2>
  <?php if (empty($meetings)): ?>
    <p>No recent meetings found.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($meetings as $m): ?>
        <li>
          <strong><?= htmlspecialchars($m['title']) ?></strong><br>
          <small><?= htmlspecialchars($m['group_name']) ?> — <?= htmlspecialchars($m['meeting_date']) ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
