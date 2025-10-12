<?php
$posts = $mysqli->query("
  SELECT title, image_path, created_at 
  FROM church_updates 
  WHERE is_archived = 0 
  ORDER BY is_pinned DESC, created_at DESC 
  LIMIT 3
");
?>
<div class="card">
  <h2>📰 Latest Announcements</h2>
  <?php if ($posts->num_rows === 0): ?>
    <p>No recent posts.</p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;">
      <?php while ($p = $posts->fetch_assoc()): ?>
        <div style="background:#f7f9fc;border-radius:10px;padding:10px;">
          <img src="<?= htmlspecialchars($p['image_path']) ?>" style="width:100%;border-radius:8px;">
          <h3><?= htmlspecialchars($p['title']) ?></h3>
          <small>📅 <?= date('F j, Y', strtotime($p['created_at'])) ?></small>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>
