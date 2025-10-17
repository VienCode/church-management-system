<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

// ✅ Log viewing action
log_action(
    $mysqli,
    $_SESSION['user_id'],
    $_SESSION['role'],
    'VIEW',
    "Viewed logs page",
    'Normal'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>🗂️ System Activity Logs</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background:#fff; padding:25px; border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1); margin:30px auto;
    max-width:1200px;
}
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #ddd; text-align:center; }
th { background:#0271c0; color:white; }
.high { background:#fff4e5; }
.critical { background:#ffe0e0; }
.pagination { text-align:center; margin-top:20px; }
.pagination a {
    margin:0 5px; padding:8px 12px; background:#f0f0f0;
    border-radius:6px; text-decoration:none; color:#333;
    font-weight:600;
}
.pagination a.active { background:#0271c0; color:white; }
.pagination a:hover { background:#02589b; color:white; }
.search-bar {
    display:flex; gap:10px; margin-bottom:15px;
    align-items:center;
}
.search-bar input {
    flex:1; padding:8px; border-radius:6px; border:1px solid #ccc;
}
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>🗂️ System Activity Logs</h1>
<p>Track all user actions within the system. Logs are immutable for accountability.</p>

<!-- ✅ Live Search + Filters -->
<div class="search-bar">
  <input type="text" id="searchInput" placeholder="Search logs (role, action, importance, description)...">
  <input type="date" id="startDate">
  <input type="date" id="endDate">
  <button id="filterBtn" class="save-btn">🔍 Filter</button>
  <button id="resetBtn" class="secondary-btn">Reset</button>
</div>

<!-- ✅ Logs Table Container (AJAX-updated) -->
<div id="logsContainer" style="min-height:400px;">
  <p>Loading logs...</p>
</div>

</div>
</div>
</div>

<script>
// === AJAX: Fetch logs dynamically ===
function fetchLogs(page = 1) {
    const search = document.getElementById('searchInput').value.trim();
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;

    const params = new URLSearchParams({
        page, search, start, end
    });

    fetch("fetch_logs.php?" + params)
    .then(res => res.text())
    .then(data => {
        document.getElementById('logsContainer').innerHTML = data;
    });
}

// === Event listeners ===
document.addEventListener('DOMContentLoaded', () => {
    fetchLogs(); // Initial load

    document.getElementById('searchInput').addEventListener('keyup', () => fetchLogs(1));
    document.getElementById('filterBtn').addEventListener('click', () => fetchLogs(1));
    document.getElementById('resetBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        fetchLogs(1);
    });

    // Handle pagination clicks (event delegation)
    document.addEventListener('click', e => {
        if (e.target.classList.contains('page-link')) {
            e.preventDefault();
            fetchLogs(e.target.dataset.page);
        }
    });
});
</script>
</body>
</html>
