<?php
$leader_email = $_SESSION['email'];
$leader = $mysqli->query("SELECT leader_id FROM leaders WHERE email='$leader_email' LIMIT 1")->fetch_assoc();
if ($leader) {
  $leader_id = $leader['leader_id'];
  $group = $mysqli->query("SELECT id, group_name FROM cell_groups WHERE leader_id=$leader_id AND status='active' LIMIT 1")->fetch_assoc();
  if ($group) {
    $group_id = $group['id'];
    $count = $mysqli->query("SELECT COUNT(*) AS total FROM cell_group_members WHERE cell_group_id=$group_id")->fetch_assoc()['total'];
    echo "<div class='card'><h2>👥 Your Cell Group</h2><p><b>{$group['group_name']}</b></p><p>Total Members: <strong>$count</strong></p></div>";
  } else {
    echo "<div class='card'><p>⚠️ You don’t have an active group yet.</p></div>";
  }
}
?>
    