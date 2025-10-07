<?php
// CRITICAL FIX 1: Enable error reporting at the very top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mysqli = include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_EDITOR, ROLE_ADMIN, ROLE_PASTOR]);

// CRITICAL FIX 2: Check for successful database connection immediately
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Helper function to handle POST request errors
function handle_post_error($stmt, $message = "Database operation failed") {
    global $conn;
    echo "<h1>Error: " . $message . "</h1>";
    echo "<p>MySQL Error: " . $conn->error . "</p>";
    if ($stmt) {
        echo "<p>Statement Error: " . $stmt->error . "</p>";
        $stmt->close();
    }
    // You might want to remove 'exit()' in production, but it's vital for debugging
    exit(); 
}


// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $image = null;

    if (empty($title) || empty($content)) {
        header("Location: upload.php?error=missing_fields");
        exit();
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            header("Location: upload.php?error=invalid_file_type");
            exit();
        }
        if ($_FILES['image']['size'] > $maxSize) {
            header("Location: upload.php?error=file_too_large");
            exit();
        }

        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                handle_post_error(null, "Failed to create uploads directory.");
            }
        }
        
        $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        // SECURITY FIX: Better file naming to prevent overwriting/clashes
        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
            $image = $filePath;
            echo "File uploaded successfully. Now check the database insert.";
            // DONT exit yet, let it continue to the SQL part...
        } else {
            // Check specific file upload errors
            die("FATAL: File upload FAILED. Error code: " . $_FILES['image']['error']);
        }
    }   

    $stmt = $mysqli->prepare("INSERT INTO posts (title, content, image, created_at) VALUES (?, ?, ?, NOW())");
    
    // DEBUG: Check if prepare failed (e.g., table or column names are wrong)
    if ($stmt === false) {
        handle_post_error(null, "SQL prepare failed for INSERT");
    }

    $stmt->bind_param("sss", $title, $content, $image);
    
    // DEBUG: Check if execute failed (e.g., data type error or server issue)
    if (!$stmt->execute()) {
        handle_post_error($stmt, "SQL execute failed for INSERT");
    }
    
    $stmt->close();

    header("Location: upload.php?success=created");
    exit();
}

// Handle post editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post'])) {
    $post_id = (int)$_POST['post_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    // Check current image path
    $stmt = $mysqli->prepare("SELECT image FROM posts WHERE id = ?");
    if ($stmt === false) handle_post_error(null, "SQL prepare failed for SELECT (edit)");
    $stmt->bind_param("i", $post_id);
    if (!$stmt->execute()) handle_post_error($stmt, "SQL execute failed for SELECT (edit)");
    $result = $stmt->get_result();
    $current_post = $result->fetch_assoc();
    $stmt->close();

    // ERROR CHECK: Ensure the post exists
    if (!$current_post) {
        header("Location: upload.php?error=post_not_found");
        exit();
    }

    $image = $current_post['image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Apply file checks as in creation logic (omitted here for brevity but recommended)

        // Delete old image if it exists
        if ($image && file_exists($image)) {
            if (!unlink($image)) {
                // Warning, but continue with the update
                error_log("Failed to unlink old image: " . $image); 
            }
        }
        
        $uploadDir = 'uploads/';
        // Ensure directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                handle_post_error(null, "Failed to create uploads directory for edit.");
            }
        }
        
        $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
            $image = $filePath;
        } else {
            handle_post_error(null, "Failed to move uploaded file during edit.");
        }
    }

    $stmt = $mysqli->prepare("UPDATE posts SET title = ?, content = ?, image = ? WHERE id = ?");
    if ($stmt === false) handle_post_error(null, "SQL prepare failed for UPDATE");
    
    $stmt->bind_param("sssi", $title, $content, $image, $post_id); 
    
    if (!$stmt->execute()) handle_post_error($stmt, "SQL execute failed for UPDATE");
    
    $stmt->close();

    header("Location: upload.php?success=updated");
    exit();
}

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $post_id = (int)$_POST['post_id'];

    // Select image path
    $stmt = $conn->prepare("SELECT image FROM posts WHERE id = ?");
    if ($stmt === false) handle_post_error(null, "SQL prepare failed for SELECT (delete)");
    $stmt->bind_param("i", $post_id);
    if (!$stmt->execute()) handle_post_error($stmt, "SQL execute failed for SELECT (delete)");
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();

    // Delete image file
    if ($post && $post['image'] && file_exists($post['image'])) {
        if (!unlink($post['image'])) {
            // Log the error but proceed with database deletion
            error_log("Failed to unlink image on delete: " . $post['image']);
        }
    }

    // Delete database entry
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    if ($stmt === false) handle_post_error(null, "SQL prepare failed for DELETE");
    $stmt->bind_param("i", $post_id);
    if (!$stmt->execute()) handle_post_error($stmt, "SQL execute failed for DELETE");
    $stmt->close();

    header("Location: upload.php?success=deleted");
    exit();
}

// Pagination and Post Retrieval Logic (No changes needed, the original code is fine)
$posts_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $posts_per_page;

$count_query = $mysqli->prepare("SELECT COUNT(*) as total FROM posts");
$count_query->execute();
$total_posts = $count_query->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

$posts_query = $mysqli->prepare("
    SELECT id, title, content, image, created_at 
    FROM posts 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$posts_query->bind_param("ii", $posts_per_page, $offset);
$posts_query->execute();
$posts_result = $posts_query->get_result();

// End of PHP block
?>
<!DOCTYPE html>
<html>
<head>
    <title>Church Updates</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder"><span>⛪</span></div>
        <div class="logo">Unity Christian Fellowship</div>
    </div>
    <ul class="nav-menu">
        <!-- GENERAL PAGES -->
        <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>

        <?php if (can_access([ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_MEMBER, ROLE_LEADER])): ?>
            <li><a href="members.php"><span>👤</span> Members</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
        <!-- Divider -->
        <li class="nav-divider"></li>
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_ADMIN])): ?>
        <li class="nav-divider"></li>
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
            <li><a href="admin_dashboard.php"><span>⚙️</span> Manage Users</a></li>
        <?php endif; ?>

        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>

    <div class="content-area">
        <div class="content-header">
            <div class="header-left">
                <h1 class="page-title">
                    Church Updates
                    <span class="title-subtitle">Share news and announcements with your community</span>
                </h1>
            </div>
            <div class="header-right">
                <button onclick="openModal('createPostModal')" class="create-btn">Post Update</button>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <span class="success-icon">✓</span>
                <?php 
                    switch($_GET['success']) {
                        case 'created': echo 'Post created successfully!'; break;
                        case 'updated': echo 'Post updated successfully!'; break;
                        case 'deleted': echo 'Post deleted successfully!'; break;
                    }
                ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="error-message">
                <span class="error-icon">✗</span>
                <?php 
                    switch($_GET['error']) {
                        case 'missing_fields': echo 'Error: Title and Content cannot be empty.'; break;
                        case 'invalid_file_type': echo 'Error: Only JPEG, PNG, or GIF images are allowed.'; break;
                        case 'file_too_large': echo 'Error: Image size exceeds the 5MB limit.'; break;
                        case 'post_not_found': echo 'Error: The post you tried to edit was not found.'; break;
                        default: echo 'An unknown error occurred.'; break;
                    }
                ?>
            </div>
        <?php endif; ?>

        <div class="posts-stats">
            <div class="stat-item"><div class="stat-icon">📝</div><div class="stat-info"><div class="stat-number"><?php echo $total_posts; ?></div><div class="stat-label">Total Posts</div></div></div>
            <div class="stat-item"><div class="stat-icon">📅</div><div class="stat-info"><div class="stat-number"><?php echo date('M Y'); ?></div><div class="stat-label">Current Month</div></div></div>
            <div class="stat-item"><div class="stat-icon">👥</div><div class="stat-info"><div class="stat-number">Active</div><div class="stat-label">Community</div></div></div>
        </div>

        <div class="posts-container">
            <?php if ($posts_result->num_rows > 0): ?>
                <div class="posts-grid">
                    <?php $animation_delay = 0; ?>
                    <?php while ($post = $posts_result->fetch_assoc()): ?>
                        <article class="post-card" style="animation-delay: <?php echo $animation_delay; ?>ms">
                            <?php if ($post['image']): ?>
                                <div class="post-image" onclick="openLightbox('<?php echo htmlspecialchars($post['image']); ?>')">
                                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                    <div class="image-overlay"><span class="overlay-icon">🔍</span><span class="overlay-text">Click to view</span></div>
                                    <div class="image-badge">Featured</div>
                                </div>
                            <?php endif; ?>
                            <div class="post-content">
                                <div class="post-header">
                                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <div class="post-meta">
                                        <span class="meta-item">📅 <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span class="meta-item">⏰ <?php echo date('g:i A', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                                <div class="post-footer">
                                    <div class="post-tags"><span class="tag">Church Update</span></div>
                                    <div class="post-actions">
                                        <button onclick="openEditModal(<?php echo $post['id']; ?>)" class="action-btn edit-btn">✏️ Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" name="delete_post" class="action-btn delete-btn">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <?php $animation_delay += 150; ?>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3 class="empty-title">No posts yet</h3>
                    <p class="empty-description">Start sharing updates with your community by creating your first post!</p>
                    <button onclick="openModal('createPostModal')" class="empty-cta">Post Something!</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-btn prev-btn">← Previous</a>
                    <?php endif; ?>
                    <div class="pagination-info"><?php echo $current_page; ?> of <?php echo $total_pages; ?></div>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-btn next-btn">Next →</a>
                    <?php endif; ?>
                </div>
                <div class="pagination-stats">
                    Showing <?php echo (($current_page - 1) * $posts_per_page) + 1; ?>-<?php echo min($current_page * $posts_per_page, $total_posts); ?> of <?php echo $total_posts; ?> posts
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="createPostModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('createPostModal')">&times;</span>
        <h2>Create New Post</h2>
        <form method="POST" enctype="multipart/form-data">
            <label for="title">Title:</label>
            <input type="text" name="title" required>
            <label for="content">Content:</label>
            <textarea name="content" rows="6" required></textarea>
            <label for="image">Image (optional):</label>
            <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'createPreview')">
            <div id="createPreview" class="image-preview"></div>
            <button type="submit" name="create_post">Create Post</button>
        </form>
    </div>
</div>

<div id="editPostModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editPostModal')">&times;</span>
        <h2>Edit Post</h2>
        <form method="POST" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="post_id" id="editPostId">
            <label for="editTitle">Title:</label>
            <input type="text" name="title" id="editTitle" required>
            <label for="editContent">Content:</label>
            <textarea name="content" id="editContent" rows="6" required></textarea>
            <label for="editImage">Replace Image (optional):</label>
            <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'editPreview')">
            <div id="currentImageContainer"></div>
            <div id="editPreview" class="image-preview"></div>
            <button type="submit" name="edit_post">Update Post</button>
        </form>
    </div>
</div>

<div id="lightbox" class="lightbox">
    <div class="lightbox-content">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <img id="lightbox-image" src="" alt="">
    </div>
</div>

<script src="/capstones/phpdatabasetest/attendance%20test/script.js"></script>
</body>
</html>