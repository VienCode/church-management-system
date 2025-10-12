<?php
$user_email = $_SESSION['email'];
$non_member = $mysqli->query("SELECT attendances_count FROM non_members WHERE email = '$user_email'")->fetch_assoc();
$attendances = $non_member['attendances_count'] ?? 0;
$progress = min(100, ($attendances / 10) * 100);
?>
<div class="card">
  <h2>🌱 Your Journey to Membership</h2>
  <p>Attendance Progress: <strong><?= $attendances ?>/10</strong> services attended</p>
  <div style="background:#e6e6e6;border-radius:8px;overflow:hidden;">
    <div style="background:#0271c0;height:20px;width:<?= $progress ?>%;"></div>
  </div>
  <p style="margin-top:10px;">🎯 Attend 10 services to qualify as a member!</p>
</div>
