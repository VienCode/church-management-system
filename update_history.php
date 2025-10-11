<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]);

$archived = $mysqli->query("
    SELECT * FROM church_updates WHERE is_archived = 1 ORDER BY updated_at DESC
");
// Handle restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $restore_id = intval($_POST['restore_id']);

    $stmt = $mysqli->prepare("
        UPDATE church_updates 
        SET is_archived = 0, updated_at = NOW() 
        WHERE update_id = ?
    ");
    $stmt->bind_param("i", $restore_id);
    $stmt->execute();
    header("Location: update_restore.php?msg=✅ Post restored successfully!");
    exit();
}

// Handle permanent delete (optional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);

    $stmt = $mysqli->prepare("DELETE FROM church_updates WHERE update_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: update_restore.php?msg=🗑️ Post permanently deleted.");
    exit();
}

// Fetch archived posts
$sql = "SELECT * FROM church_updates WHERE is_archived = 1 ORDER BY updated_at DESC";
$result = $mysqli->query($sql);
$archived_posts = $result->fetch_all(MYSQLI_ASSOC);

$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Post History | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.1); max-width:1000px; margin:30px auto; }
.post { border-bottom:1px solid #ddd; padding:10px 0; }
.restore-btn { background:#198754; color:#fff; padding:8px 12px; border:none; border-radius:6px; cursor:pointer; }
.restore-btn:hover { background:#146c43; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>🕒 Post History</h1>
            <?php if ($archived->num_rows == 0): ?>
                <p>No archived posts found.</p>
            <?php else: while ($p = $archived->fetch_assoc()): ?>
                <div class="post">
                    <h3><?= htmlspecialchars($p['title']) ?></h3>
                    <p><?= htmlspecialchars($p['description']) ?></p>
                    <form method="POST" action="update_restore.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $p['update_id'] ?>">
                        <button class="restore-btn">🔁 Repost</button>
                    </form>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</div>
</body>
</html>
