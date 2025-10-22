<?php
/* ==========================================================
   🧩 SYSTEM HEALTH WIDGET
   Displays connection status, backup info, and role
========================================================== */
if (!isset($mysqli)) { include_once __DIR__.'/../database.php'; global $mysqli; }

$backup_info = $mysqli->query("SELECT created_at FROM backups ORDER BY created_at DESC LIMIT 1");
$last_backup = ($backup_info && $backup_info->num_rows > 0)
  ? date('F j, Y g:i A', strtotime($backup_info->fetch_assoc()['created_at']))
  : 'No backups yet';
$db_status = $mysqli->ping() ? 'Connected' : 'Disconnected';
?>

<div class="card system-status-card">
  <h2>🧩 System Status</h2>
  <div class="system-status">
    <span class="status-chip success">✅ Database: <?= $db_status ?></span>
    <span class="status-chip info">💾 Last Backup: <?= htmlspecialchars($last_backup) ?></span>
    <span class="status-chip info">🔐 Role: Administrator</span>
  </div>
</div>

<style>
.system-status { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.status-chip {
  display:inline-block; padding:6px 12px; border-radius:15px;
  font-size:13px; font-weight:600;
}
.status-chip.success { background:#d4edda; color:#155724; }
.status-chip.info { background:#e8f0fe; color:#1a73e8; }
</style>
