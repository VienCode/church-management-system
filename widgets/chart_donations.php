<?php
$donations = $mysqli->query("
  SELECT DATE_FORMAT(donation_date, '%b') AS month, SUM(amount) AS total
  FROM donations
  WHERE YEAR(donation_date) = YEAR(CURDATE())
  GROUP BY MONTH(donation_date)
  ORDER BY MONTH(donation_date)
");
$months = $totals = [];
while ($row = $donations->fetch_assoc()) {
  $months[] = $row['month'];
  $totals[] = $row['total'];
}
?>
<div class="card">
  <h2>💰 Donations (This Year)</h2>
  <canvas id="donationsChart"></canvas>
</div>
<script>
new Chart(document.getElementById('donationsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [{ label: 'Donations', data: <?= json_encode($totals) ?>, backgroundColor: '#0271c0' }]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
