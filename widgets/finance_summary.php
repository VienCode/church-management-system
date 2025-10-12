<?php
$finance = $mysqli->query("
  SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM donations) AS total_donations,
    (SELECT COALESCE(SUM(amount), 0) FROM expenses) AS total_expenses
")->fetch_assoc();
$net = $finance['total_donations'] - $finance['total_expenses'];
?>
<div class="card">
  <h2>📊 Financial Summary</h2>
  <p>💰 Total Donations: <strong>₱<?= number_format($finance['total_donations'], 2) ?></strong></p>
  <p>💼 Total Expenses: <strong>₱<?= number_format($finance['total_expenses'], 2) ?></strong></p>
  <p>📈 Net Balance: <strong style="color:<?= $net >= 0 ? 'green' : 'red' ?>;">₱<?= number_format($net, 2) ?></strong></p>
</div>
