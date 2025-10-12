<?php
$stats = $mysqli->query("
  SELECT 
    (SELECT COUNT(*) FROM users WHERE role_id = 3) AS total_members,
    (SELECT COUNT(*) FROM leaders WHERE status = 'active') AS total_leaders,
    (SELECT COUNT(*) FROM cell_groups WHERE status = 'active') AS total_groups
")->fetch_assoc();
?>
<div class="card">
  <h2>📊 Summary</h2>
  <p>👥 Members: <strong><?= $stats['total_members'] ?></strong></p>
  <p>👑 Leaders: <strong><?= $stats['total_leaders'] ?></strong></p>
  <p>📂 Cell Groups: <strong><?= $stats['total_groups'] ?></strong></p>
</div>
