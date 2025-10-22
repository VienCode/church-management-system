<?php
$posts = $mysqli->query("
  SELECT title, image_path, created_at, is_pinned
  FROM church_updates
  WHERE is_archived = 0
  ORDER BY is_pinned DESC, created_at DESC
  LIMIT 5
");
?>

<div class="card latest-posts-card">
  <h2>📰 Latest Announcements</h2>

  <?php if ($posts->num_rows === 0): ?>
    <p>No recent posts.</p>
  <?php else: ?>
    <div class="latest-posts-row">
      <?php while ($p = $posts->fetch_assoc()): ?>
        <div class="latest-post-card <?= $p['is_pinned'] ? 'pinned' : '' ?>">
          <div class="post-image">
            <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="Post image">
            <?php if ($p['is_pinned']): ?>
              <div class="pinned-badge">📌 Pinned</div>
            <?php endif; ?>
          </div>
          <div class="post-content">
            <h3><?= htmlspecialchars($p['title']) ?></h3>
            <small>📅 <?= date('F j, Y', strtotime($p['created_at'])) ?></small>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<style>
/* --- Dashboard Latest Posts Widget --- */
.latest-posts-card {
  margin-top: 20px;
}

.latest-posts-row {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 8px;
  scrollbar-width: thin;
  scrollbar-color: #d0d0d0 transparent;
}

.latest-posts-row::-webkit-scrollbar {
  height: 8px;
}
.latest-posts-row::-webkit-scrollbar-thumb {
  background-color: #d0d0d0;
  border-radius: 10px;
}

.latest-post-card {
  min-width: 230px;
  flex: 0 0 auto;
  background: #f8fafc;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}
.latest-post-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.12);
}

/* 📌 Highlighted pinned posts */
.latest-post-card.pinned {
  border: 2px solid #f5c518;
  background: #fffaf2;
}
.latest-post-card.pinned .pinned-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: linear-gradient(135deg, #f8d63a, #f5c518);
  color: #3b2f05;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: bold;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Post images */
.latest-post-card img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
}

/* Content */
.latest-post-card .post-content {
  padding: 10px 12px 15px 12px;
}
.latest-post-card h3 {
  font-size: 15px;
  color: #1e3a8a;
  margin: 0 0 6px 0;
}
.latest-post-card small {
  color: #6c757d;
  font-size: 13px;
}

/* Responsive tweak */
@media (max-width: 768px) {
  .latest-post-card {
    min-width: 200px;
  }
}
</style>
