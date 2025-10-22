<?php
// ============================================================
// 📊 Cell Group Summary Widget
// Displays Active, Archived, and Unassigned member stats
// ============================================================

if (!isset($mysqli)) {
    include_once __DIR__ . '/../database.php';
    global $mysqli;
}

// ----------------------------
// Query Counts
// ----------------------------
$active_groups = $mysqli->query("SELECT COUNT(*) AS count FROM cell_groups WHERE status='active'")->fetch_assoc()['count'] ?? 0;
$archived_groups = $mysqli->query("SELECT COUNT(*) AS count FROM cell_groups WHERE status='archived'")->fetch_assoc()['count'] ?? 0;
$unassigned_members = $mysqli->query("SELECT COUNT(*) AS count FROM users WHERE role_id=3 AND (cell_group_id IS NULL OR cell_group_id=0)")->fetch_assoc()['count'] ?? 0;
?>

<!-- 📊 Cell Group Statistics Section -->
<div class="cellgroup-section">
  <h2>📈 Cell Group Overview</h2>

  <div class="stats-container">
    <div class="stat-card total">
      <h3>Active Groups</h3>
      <div class="number"><?= $active_groups ?></div>
    </div>

    <div class="stat-card absent">
      <h3>Archived Groups</h3>
      <div class="number"><?= $archived_groups ?></div>
    </div>

    <div class="stat-card present">
      <h3>Unassigned Members</h3>
      <div class="number"><?= $unassigned_members ?></div>
    </div>
  </div>
</div>
