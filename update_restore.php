<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]);

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

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Restore Archived Posts | Church Updates</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
.success {
    background: #e6ffed;
    color: #256029;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: center;
}
th {
    background: #0271c0;
    color: white;
}
.post-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}
.action-btn {
    background: #0271c0;
    color: white;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}
.action-btn:hover {
    background: #02589b;
}
.delete-btn {
    background: #dc3545;
}
.delete-btn:hover {
    background: #a71d2a;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>🗂️ Archived Church Updates</h1>
            <p>Manage and restore previously archived posts.</p>

            <?php if ($msg): ?>
                <div class="success"><?= $msg ?></div>
            <?php endif; ?>

            <?php if (empty($archived_posts)): ?>
                <p>No archived posts found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Posted By</th>
                            <th>Archived On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_posts as $post): ?>
                            <tr>
                                <td>
                                    <?php if ($post['image_path']): ?>
                                        <img src="<?= htmlspecialchars($post['image_path']) ?>" class="post-img" alt="Post Image">
                                    <?php else: ?>
                                        <span style="color:#888;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($post['title']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($post['description'], 0, 80)) ?>...</td>
                                <td><?= htmlspecialchars($post['posted_by_name']) ?></td>
                                <td><?= htmlspecialchars($post['updated_at']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="restore_id" value="<?= $post['update_id'] ?>">
                                        <button type="submit" class="action-btn">♻️ Restore</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_id" value="<?= $post['update_id'] ?>">
                                        <button type="submit" class="action-btn delete-btn" onclick="return confirm('Delete permanently?')">🗑️ Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
