<?php
/* ==========================================================
   🧾 RECENT ACTIVITY WIDGET
   Displays latest actions from system_logs
========================================================== */
if (!isset($mysqli)) { include_once __DIR__.'/../database.php'; global $mysqli; }

$logs = $mysqli->query("
  SELECT user_role, action_type, action_description, importance, created_at
  FROM system_logs
  ORDER BY created_at DESC
  LIMIT 5
");
?>

<div class="card recent-activity-card">
  <h2>🧾 Recent Activity</h2>

  <?php if ($logs->num_rows === 0): ?>
    <p>No recent activity.</p>
  <?php else: ?>
    <table class="activity-table">
      <thead>
        <tr><th>Time</th><th>Action</th><th>Description</th><th>Level</th></tr>
      </thead>
      <tbody>
        <?php while ($l = $logs->fetch_assoc()): ?>
          <tr>
            <td><?= date('M j, g:i A', strtotime($l['created_at'])) ?></td>
            <td><strong><?= htmlspecialchars($l['action_type']) ?></strong></td>
            <td><?= htmlspecialchars($l['action_description']) ?></td>
            <td>
              <span class="badge <?= strtolower($l['importance']) ?>">
                <?= htmlspecialchars($l['importance']) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<style>
.activity-table {
  width:100%; border-collapse:collapse; margin-top:10px;
}
.activity-table th {
  background:#1e3a8a; color:white; text-align:left; padding:8px; font-size:13px;
}
.activity-table td {
  padding:8px; border-bottom:1px solid #eee; font-size:13px;
}
.badge {
  padding:3px 8px; border-radius:8px; font-size:12px; font-weight:600;
}
.badge.low { background:#e0f2fe; color:#0369a1; }
.badge.normal { background:#e2e3e5; color:#343a40; }
.badge.high { background:#fde2e1; color:#c82333; }
.badge.critical { background:#fff3cd; color:#856404; }
</style>
