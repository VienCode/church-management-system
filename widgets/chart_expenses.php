<?php
$expenses = $mysqli->query("
  SELECT DATE_FORMAT(expense_date, '%b') AS month, SUM(amount) AS total
  FROM expenses
  WHERE YEAR(expense_date) = YEAR(CURDATE())
  GROUP BY MONTH(expense_date)
  ORDER BY MONTH(expense_date)
");
$months = $totals = [];
while ($row = $expenses->fetch_assoc()) {
  $months[] = $row['month'];
  $totals[] = $row['total'];
}
?>
<div class="card">
  <h2>💼 Expenses (This Year)</h2>
  <canvas id="expenseChart"></canvas>
</div>
<script>
new Chart(document.getElementById('expenseChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [{ label: 'Expenses', data: <?= json_encode($totals) ?>, backgroundColor: '#dc3545' }]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
