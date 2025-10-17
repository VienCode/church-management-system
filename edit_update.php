<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // Include centralized logging helper
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]); // Only Admins & Editors

// --- Step 1: Validate update ID ---
if (!isset($_GET['id'])) {
    header("Location: upload.php?msg=❌ Invalid update ID");
    exit();
}

$update_id = intval($_GET['id']);

// --- Step 2: Fetch existing post ---
$stmt = $mysqli->prepare("SELECT * FROM church_updates WHERE update_id = ?");
$stmt->bind_param("i", $update_id);
$stmt->execute();
$update = $stmt->get_result()->fetch_assoc();

if (!$update) {
    header("Location: upload.php?msg=❌ Post not found");
    exit();
}

// --- Step 3: Handle POST update form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $image_path = $update['image_path'];

    // If new image uploaded
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;
        $imageType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if (in_array($imageType, ['jpg', 'jpeg', 'png'])) {
            move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);
            $image_path = $targetFile;
        } else {
            $msg = "❌ Only JPG, JPEG, and PNG formats allowed.";
        }
    }

    // Update record
    $stmt = $mysqli->prepare("
        UPDATE church_updates
        SET title = ?, description = ?, image_path = ?, is_pinned = ?, updated_at = NOW()
        WHERE update_id = ?
    ");
    $stmt->bind_param("sssii", $title, $description, $image_path, $is_pinned, $update_id);

    if ($stmt->execute()) {
        // Log the edit action in centralized system log
        log_action(
            $mysqli,
            $_SESSION['user_id'],          // Who performed the action
            $_SESSION['role'],             // Their role
            'EDIT',                        // Action type
            "Edited announcement '{$title}' (ID: {$update_id})", // Description
            'Normal'                       // Severity level
        );

        header("Location: upload.php?msg=✅ Post updated successfully!");
        exit();
    } else {
        $msg = "❌ Database error: " . $mysqli->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Church Update | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    max-width: 800px;
    margin: 30px auto;
}
.preview-img {
    width: 100%;
    max-height: 280px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 10px;
}
label { font-weight: 600; }
input[type="text"], textarea, select {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
.save-btn {
    background: #0271c0;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
.save-btn:hover { background: #02589b; }
.back-btn {
    background: #ccc;
    color: #333;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>✏️ Edit Church Update</h1>
            <?php if (isset($msg)) echo "<div class='error-message'>$msg</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <label>Title:</label>
                <input type="text" name="title" value="<?= htmlspecialchars($update['title']) ?>" required>

                <label>Description:</label>
                <textarea name="description" rows="5" required><?= htmlspecialchars($update['description']) ?></textarea>

                <label>Current Image:</label><br>
                <?php if ($update['image_path']): ?>
                    <img src="<?= htmlspecialchars($update['image_path']) ?>" class="preview-img">
                <?php else: ?>
                    <p>No image uploaded.</p>
                <?php endif; ?>

                <label>Replace Image (optional):</label>
                <input type="file" name="image" accept="image/*">

                <label>
                    <input type="checkbox" name="is_pinned" <?= $update['is_pinned'] ? 'checked' : '' ?>> Pin this post to top
                </label>
                <br><br>

                <button type="submit" class="save-btn">💾 Save Changes</button>
                <a href="upload.php" class="back-btn">⬅ Back</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
