<?php
include 'database.php';

$limit = 10; // logs per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$query = "SELECT l.*, CONCAT(u.firstname, ' ', u.lastname) AS fullname
          FROM system_logs l
          LEFT JOIN users u ON l.user_id = u.id
          WHERE 1";

$params = [];
$types = "";

// === Apply filters dynamically ===
if ($search) {
    $query .= " AND (l.user_role LIKE ? OR l.action_type LIKE ? OR l.action_description LIKE ? OR l.importance LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}
if ($start && $end) {
    $query .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= "ss";
}

// === Count total logs ===
$count_sql = "SELECT COUNT(*) AS total FROM ($query) AS counted";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_logs = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_logs / $limit);

// === Add limit and offset ===
$query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $mysqli->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- === Render Logs Table === -->
<table>
<thead>
<tr>
<th>User</th><th>Role</th><th>Action</th><th>Description</th>
<th>IP</th><th>Importance</th><th>Timestamp</th>
</tr>
</thead>
<tbody>
<?php if ($result->num_rows === 0): ?>
<tr><td colspan="7">No logs found.</td></tr>
<?php else: ?>
<?php while($log = $result->fetch_assoc()): 
$class = strtolower($log['importance']); ?>
<tr class="<?= $class ?>">
<td><?= htmlspecialchars($log['fullname'] ?? 'System') ?></td>
<td><?= htmlspecialchars($log['user_role']) ?></td>
<td><?= htmlspecialchars($log['action_type']) ?></td>
<td><?= htmlspecialchars($log['action_description']) ?></td>
<td><?= htmlspecialchars($log['ip_address']) ?></td>
<td><strong><?= htmlspecialchars($log['importance']) ?></strong></td>
<td><?= date('F j, Y g:i A', strtotime($log['created_at'])) ?></td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</tbody>
</table>

<!-- === Pagination Controls === -->
<div class="pagination">
<?php if ($total_pages > 1): ?>
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="#" class="page-link <?= $p == $page ? 'active' : '' ?>" data-page="<?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
<?php endif; ?>
</div>

<p style="text-align:center; margin-top:10px;">
Showing <strong><?= ($offset + 1) ?></strong>–
<strong><?= min($offset + $limit, $total_logs) ?></strong> of
<strong><?= $total_logs ?></strong> logs
</p>
<?php $stmt->close(); ?>
