<?php
$leader_email = $_SESSION['email'];
$leader_id = $mysqli->query("SELECT leader_id FROM leaders WHERE email='$leader_email' LIMIT 1")->fetch_assoc()['leader_id'] ?? null;
$group_id = $mysqli->query("SELECT id FROM cell_groups WHERE leader_id=$leader_id LIMIT 1")->fetch_assoc()['id'] ?? null;

$chart_data = $mysqli->query("
  SELECT DATE_FORMAT(meeting_date, '%b %d') AS date, COUNT(a.id) AS total_present
  FROM cell_group_meetings m
  LEFT JOIN cell_group_attendance a ON m.id = a.meeting_id AND a.status = 'Present'
  WHERE m.cell_group_id = $group_id
  GROUP BY m.meeting_date
  ORDER BY m.meeting_date ASC
");

$dates = []; $counts = [];
while ($row = $chart_data->fetch_assoc()) {
  $dates[] = $row['date'];
  $counts[] = $row['total_present'];
}
?>
<div class="card">
  <h2>📊 Attendance Trend</h2>
  <canvas id="attendanceChart"></canvas>
</div>
<script>
new Chart(document.getElementById('attendanceChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($dates) ?>,
    datasets: [{ label: 'Present Members', data: <?= json_encode($counts) ?>, borderColor: '#0271c0', fill: false }]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
