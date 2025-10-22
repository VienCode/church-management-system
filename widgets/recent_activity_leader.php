<?php
include_once __DIR__ . '/../database.php';
include_once __DIR__ . '/../auth_check.php';
restrict_to_roles([ROLE_LEADER]);

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("
    SELECT action_type, action_description, created_at
    FROM system_logs
    WHERE user_id = ? 
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
  <h2>🧾 Recent Activity</h2>
  <?php if (empty($logs)): ?>
    <p>No recent activity logged.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($logs as $log): ?>
        <li>
          <strong><?= htmlspecialchars($log['action_type']) ?></strong> — <?= htmlspecialchars($log['action_description']) ?><br>
          <small><?= htmlspecialchars($log['created_at']) ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
