<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

// ✅ Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $mysqli->prepare("SELECT file_name, file_path FROM backups WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $backup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($backup) {
        $file_name = $backup['file_name'];
        $file_path = $backup['file_path'];

        if (file_exists($file_path)) unlink($file_path);

        $del = $mysqli->prepare("DELETE FROM backups WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        log_action($mysqli, $_SESSION['user_id'], $_SESSION['role'], 'DELETE_BACKUP', "Deleted backup file {$file_name}", 'High');
        header("Location: backup.php?msg=🗑️ Backup file deleted successfully!");
        exit;
    } else {
        header("Location: backup.php?msg=❌ Backup record not found!");
        exit;
    }
}

// ✅ Fetch tables
$tables_result = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_array()) {
    $tables[] = $row[0];
}

// ✅ Fetch backup history
$backups = $mysqli->query("
    SELECT b.*, CONCAT(u.firstname, ' ', u.lastname) AS creator_name
    FROM backups b
    JOIN users u ON b.created_by = u.id
    ORDER BY b.created_at DESC
");

$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📦 Database Backups | UCF CMS</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    max-width:1000px;
    margin:30px auto;
}
button {
    background:#0271c0;
    color:white;
    padding:10px 16px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}
button:hover { background:#02589b; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
fieldset { border:1px solid #ddd; border-radius:8px; padding:10px; margin-bottom:15px; }
legend { font-weight:600; color:#0271c0; }
.success {
    background:#e6ffed; color:#256029; padding:10px; border-radius:8px;
    margin-bottom:15px; font-weight:600;
}
.error {
    background:#ffeaea; color:#b72b2b; padding:10px; border-radius:8px;
    margin-bottom:15px; font-weight:600;
}
.action-btn { padding:6px 10px; border-radius:6px; text-decoration:none; font-weight:600; }
.download-btn { background:#198754; color:white; }
.delete-btn { background:#dc3545; color:white; }
.restore-btn { background:#ffb100; color:white; }
.test-btn { background:#6c757d; color:white; }
.zip-btn { background:#7952b3; color:white; }
.view-btn { background:#0d6efd; color:white; }
.delete-btn:hover { background:#b92c3a; }
.download-btn:hover { background:#146c43; }
.restore-btn:hover { background:#cc8a00; }
.test-btn:hover { background:#565e64; }
.zip-btn:hover { background:#5e3d8d; }
.view-btn:hover { background:#0a58ca; }
.action-group { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
hr { margin:40px 0 25px; }
h2 { margin-top:25px; color:#222; }
.note { font-size:0.9em; color:#666; margin-bottom:10px; text-align:center; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>📦 Secure Database Backup</h1>

<?php if ($msg): ?>
    <div class="<?= str_contains($msg, '❌') ? 'error' : 'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- ✅ Backup Creation Form -->
<form action="backup_manager.php" method="POST">
    <!-- Core tables -->
    <fieldset>
        <legend>🧩 Core System Tables</legend>
        <?php foreach ($tables as $table): ?>
            <?php if (!str_starts_with($table, 'cell_group')): ?>
                <label style="margin-right:10px;">
                    <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" checked>
                    <?= htmlspecialchars($table) ?>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </fieldset>

    <!-- Cell Group tables -->
    <fieldset>
        <legend>👥 Cell Group Module Tables</legend>
        <?php foreach ($tables as $table): ?>
            <?php if (str_starts_with($table, 'cell_group')): ?>
                <label style="margin-right:10px;">
                    <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" checked>
                    <?= htmlspecialchars($table) ?>
                </label>
            <?php endif; ?>
        <?php endforeach; ?>
    </fieldset>

    <label><input type="checkbox" name="encrypt" value="1" checked> 🔒 Encrypt Backup File</label>
    <div class="note">Encrypted backups use AES-256 encryption for maximum security.</div>

    <div class="action-group">
        <button type="submit" name="create_backup">🧾 Create Backup</button>
        <a href="restore.php" class="action-btn view-btn">🔁 Go to Restore Page</a>
    </div>
</form>

<!-- ✅ Test & ZIP Actions -->
<form action="backup_manager.php" method="POST" style="margin-top:10px;">
    <div class="action-group">
        <button type="submit" name="test_backup" class="test-btn">🧪 Test Backup Connection</button>
        <button type="submit" name="download_all_backups" class="zip-btn">📦 Download All Backups (ZIP)</button>
    </div>
</form>

<hr>

<!-- ✅ Backup History Table -->
<h2>📂 Backup History</h2>
<table>
<thead>
<tr>
<th>#</th>
<th>File</th>
<th>Size</th>
<th>Created By</th>
<th>Date</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$i = 1;
if ($backups->num_rows > 0):
    while ($b = $backups->fetch_assoc()):
        $sizeKB = $b['file_size'] / 1024;
        $sizeDisplay = $sizeKB >= 1024
            ? number_format($sizeKB / 1024, 2) . ' MB'
            : number_format($sizeKB, 2) . ' KB';
?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($b['file_name']) ?></td>
<td><?= $sizeDisplay ?></td>
<td><?= htmlspecialchars($b['creator_name']) ?></td>
<td><?= date('F j, Y g:i A', strtotime($b['created_at'])) ?></td>
<td><?= htmlspecialchars($b['status']) ?></td>
<td>
    <div class="action-group">
        <?php if (file_exists($b['file_path'])): ?>
            <a href="download_backup.php?file=<?= urlencode($b['file_name']) ?>" class="action-btn download-btn">⬇ Download</a>

            <form action="backup_manager.php" method="POST" style="display:inline;" onsubmit="return confirm('⚠️ This will overwrite the database. Continue?');">
                <input type="hidden" name="restore_file_path" value="<?= htmlspecialchars($b['file_path']) ?>">
                <input type="hidden" name="restore_file_name" value="<?= htmlspecialchars($b['file_name']) ?>">
                <button type="submit" name="restore_existing" class="action-btn restore-btn">🔁 Restore</button>
            </form>
        <?php else: ?>
            <span style="color:#888;">Missing</span>
        <?php endif; ?>

        <a href="?delete=<?= $b['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Delete this backup file permanently?')">🗑️ Delete</a>
    </div>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="7">No backups found yet.</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>
</div>
</div>
</body>
</html>
