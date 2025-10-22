<?php
/* ==========================================================
   ⚡ QUICK ACTIONS WIDGET
   Shortcuts for the most common administrator tasks
========================================================== */ ?>

<div class="card quick-actions-card">
  <h2>⚡ Quick Actions</h2>
  <div class="quick-actions">
    <a href="admin_dashboard.php" class="action-btn">👤 Manage Users</a>
    <a href="admin_cell_groups.php" class="action-btn">📊 Manage Cell Groups</a>
    <a href="unassigned_members.php" class="action-btn">📋 Unassigned Members</a>
    <a href="expenses_approval.php" class="action-btn">💼 Approve Expenses</a>
    <a href="backup.php" class="action-btn">💾 Backup Database</a>
    <a href="activity_logs.php" class="action-btn">🕓 View Logs</a>
  </div>
</div>

<style>
.quick-actions {
  display: flex; flex-wrap: wrap; gap: 10px; margin-top:10px;
}
.action-btn {
  background: linear-gradient(135deg, #1e3a8a, #357abd);
  color:#fff; padding:10px 16px; border-radius:8px;
  font-weight:600; text-decoration:none; transition:0.2s;
  display:inline-flex; align-items:center; gap:6px;
  box-shadow:0 2px 8px rgba(0,0,0,0.15);
}
.action-btn:hover {
  transform:translateY(-2px);
  box-shadow:0 4px 12px rgba(0,0,0,0.2);
}
</style>
