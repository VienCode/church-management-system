<?php
$approvals = $mysqli->query("
  SELECT 
    SUM(status = 'Pending') AS pending,
    SUM(status = 'Approved') AS approved,
    SUM(status = 'Rejected') AS rejected
  FROM expenses
")->fetch_assoc();
?>
<div class="card">
  <h2>✅ Expense Approvals</h2>
  <p>🕒 Pending: <strong><?= $approvals['pending'] ?></strong></p>
  <p>✅ Approved: <strong><?= $approvals['approved'] ?></strong></p>
  <p>❌ Rejected: <strong><?= $approvals['rejected'] ?></strong></p>
</div>
