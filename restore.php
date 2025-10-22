<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);

// ✅ Fetch all existing backups
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
<title>🔁 Restore Database | UCF CMS</title>
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
.success {
    background:#e6ffed; color:#256029; padding:10px; border-radius:8px;
    margin-bottom:15px; font-weight:600;
}
.error {
    background:#ffeaea; color:#b72b2b; padding:10px; border-radius:8px;
    margin-bottom:15px; font-weight:600;
}
.note {
    font-size:0.9em; color:#555; margin-bottom:15px;
}
input[type="file"] {
    padding:8px;
    background:#f8f9fa;
    border:1px solid #ddd;
    border-radius:6px;
    width:100%;
}
.action-btn { padding:6px 10px; border-radius:6px; text-decoration:none; font-weight:600; }
.restore-btn { background:#ffb100; color:white; }
.restore-btn:hover { background:#cc8a00; }
.back-btn { background:#6c757d; color:white; }
.back-btn:hover { background:#565e64; }
.delete-btn { background:#dc3545; color:white; }
.delete-btn:hover { background:#b92c3a; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.action-group { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>🔁 Restore Database Backup</h1>

<?php if ($msg): ?>
    <div class="<?= str_contains($msg, '❌') ? 'error' : 'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<p class="note">
    You can restore your database using either:
    <ul style="line-height:1.7; color:#444;">
        <li>📤 Upload a backup file (.sql / .sql.enc)</li>
        <li>📁 Restore directly from an existing backup stored on the server</li>
    </ul>
</p>

<!-- ✅ Upload Restore Section -->
<form action="backup_manager.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('⚠️ This will overwrite your current database. Continue?');">
    <label for="backup_file"><strong>Select Backup File to Upload:</strong></label>
    <input type="file" name="backup_file" id="backup_file" accept=".sql,.enc,.sql.enc" required>
    <div style="margin-top:15px;">
        <button type="submit" name="restore_backup">🔁 Restore Uploaded Backup</button>
        <a href="backup.php" class="action-btn back-btn">⬅ Back to Backup Page</a>
    </div>
</form>

<hr style="margin:40px 0;">

<!-- ✅ Existing Backups Section -->
<h2>📂 Restore from Existing Backups</h2>

<?php if ($backups->num_rows === 0): ?>
    <p style="color:#666;">No existing backups found. Create one from the <a href="backup.php" style="color:#0271c0;">Backup Page</a>.</p>
<?php else: ?>
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
                        <form action="backup_manager.php" method="POST" style="display:inline;" onsubmit="return confirm('⚠️ This will overwrite the current database. Proceed?');">
                            <input type="hidden" name="restore_file_path" value="<?= htmlspecialchars($b['file_path']) ?>">
                            <input type="hidden" name="restore_file_name" value="<?= htmlspecialchars($b['file_name']) ?>">
                            <button type="submit" name="restore_existing" class="action-btn restore-btn">🔁 Restore</button>
                        </form>
                    <?php else: ?>
                        <span style="color:#888;">Missing File</span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

<hr style="margin:40px 0;">
<p class="note">
    💡 <strong>Tips:</strong>
    <ul style="line-height:1.7; color:#444;">
        <li>Encrypted files (.enc) are automatically decrypted during restore.</li>
        <li>All Cell Group–related tables (leaders, members, attendance) are included.</li>
        <li>Restoration actions are logged in <code>activity_logs</code> for auditing.</li>
    </ul>
</p>

</div>
</div>
</div>
</body>
</html>
