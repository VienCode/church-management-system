<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // ✅ Include centralized logging helper
restrict_to_roles([ROLE_ADMIN, ROLE_EDITOR]);

$success = $error = "";

// Handle new post
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $is_pinned = isset($_POST["is_pinned"]) ? 1 : 0;
    $posted_by = $_SESSION['user_id'];
    $posted_by_name = $_SESSION['firstname'] . " " . $_SESSION['lastname'];

    $image_path = null;

    // Handle image upload
    if (!empty($_FILES["image"]["name"])) {
        $target_dir = "uploads/updates/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $image_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        $image_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($image_type, ['jpg', 'jpeg', 'png'])) {
            if ($_FILES["image"]["size"] <= 2 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_path = $target_file;
                } else $error = "❌ Error uploading image.";
            } else $error = "⚠️ Image must be less than 2MB.";
        } else $error = "⚠️ Invalid format (Only JPG, JPEG, PNG allowed).";
    }

    if (!$error && $title && $description) {
        $stmt = $mysqli->prepare("
            INSERT INTO church_updates (title, description, image_path, is_pinned, posted_by, posted_by_name)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssiss", $title, $description, $image_path, $is_pinned, $posted_by, $posted_by_name);
        $stmt->execute();
        $stmt->close();

        // ✅ Centralized log for posting new announcement
        log_action(
            $mysqli,
            $_SESSION['user_id'],       // Who posted
            $_SESSION['role'],          // Their role
            'POST',                     // Action type
            "Posted new announcement titled '{$title}'", // Log message
            'Normal'                    // Severity
        );

        $success = "✅ Announcement posted successfully!";
    }
}

// Fetch posts (pinned first)
$posts = $mysqli->query("
    SELECT * FROM church_updates 
    WHERE is_archived = 0 
    ORDER BY is_pinned DESC, created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Church Updates | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
form input, form textarea {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    margin-bottom: 10px;
}
form button {
    background: #0271c0;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
form button:hover { background: #02589b; }
.post {
    background: #f8f9fb;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
}
.post img {
    width: 100%;
    max-height: 250px;
    object-fit: cover;
    border-radius: 6px;
    margin-top: 10px;
}
.actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}
.edit-btn, .delete-btn, .pin-btn {
    padding: 8px 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
.edit-btn { background: #ffc107; color: #333; }
.delete-btn { background: #dc3545; color: #fff; }
.pin-btn { background: #198754; color: #fff; }
.success { background:#e6ffed; color:#1b6b33; padding:10px; border-radius:8px; margin-bottom:15px; }
.preview-modal {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.6); justify-content:center; align-items:center;
}
.preview-content {
    background:white; padding:25px; border-radius:10px; width:600px; max-height:80vh; overflow:auto;
}
</style>
</head>
<body>

<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>📢 Church Announcements</h1>

            <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

            <!-- Posting Form -->
            <form method="POST" enctype="multipart/form-data" id="postForm">
                <input type="text" id="title" name="title" placeholder="Title" required>
                <textarea id="description" name="description" rows="4" placeholder="Write your announcement..." required></textarea>
                <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png" required>
                <label><input type="checkbox" name="is_pinned"> 📌 Pin this post</label><br><br>
                <button type="button" id="previewBtn">👁️ Preview Post</button>
                <button type="submit">Post Announcement</button>
            </form>

            <hr>

            <h2>📜 Recent Announcements</h2>
            <?php while ($post = $posts->fetch_assoc()): ?>
                <div class="post">
                    <h3><?= htmlspecialchars($post['title']) ?> 
                        <?= $post['is_pinned'] ? '<span style="color:#d39e00;">📌 Pinned</span>' : '' ?></h3>
                    <p><?= nl2br(htmlspecialchars($post['description'])) ?></p>
                    <?php if ($post['image_path']): ?>
                        <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Announcement image">
                    <?php endif; ?>
                    <p><small>Posted by <?= htmlspecialchars($post['posted_by_name']) ?> on <?= date('F j, Y', strtotime($post['created_at'])) ?></small></p>

                    <div class="actions">
                        <a href="edit_update.php?id=<?= $post['update_id'] ?>" class="edit-btn">✏️ Edit</a>
                        <a href="update_delete.php?id=<?= $post['update_id'] ?>" class="delete-btn" onclick="return confirm('Delete this post?')">🗑️ Delete</a>
                        <a href="update_pin_toggle.php?id=<?= $post['update_id'] ?>" class="pin-btn"><?= $post['is_pinned'] ? '📍 Unpin' : '📌 Pin' ?></a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- 🔍 Preview Modal -->
<div class="preview-modal" id="previewModal">
    <div class="preview-content">
        <h2 id="previewTitle"></h2>
        <p id="previewDesc"></p>
        <img id="previewImage" style="display:none; width:100%; border-radius:8px; margin-top:10px;">
        <div style="text-align:right; margin-top:15px;">
            <button onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById("previewModal");
document.getElementById("previewBtn").addEventListener("click", () => {
    const title = document.getElementById("title").value.trim();
    const desc = document.getElementById("description").value.trim();
    const file = document.getElementById("image").files[0];
    if (!title || !desc) return alert("⚠️ Please enter both title and description.");
    document.getElementById("previewTitle").innerText = title;
    document.getElementById("previewDesc").innerText = desc;
    const imgTag = document.getElementById("previewImage");
    if (file) {
        const reader = new FileReader();
        reader.onload = e => { imgTag.src = e.target.result; imgTag.style.display = "block"; };
        reader.readAsDataURL(file);
    } else imgTag.style.display = "none";
    modal.style.display = "flex";
});
function closePreview() { modal.style.display = "none"; }
</script>
</body>
</html>
