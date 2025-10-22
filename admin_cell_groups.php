<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_ADMIN]);

$message = '';

// =============================
// ACTION HANDLERS
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE GROUP
    if ($action === 'create_group') {
        $name = trim($_POST['group_name']);
        $leader_id = intval($_POST['leader_id']);
        if ($name !== '' && $leader_id) {
            $stmt = $mysqli->prepare("INSERT INTO cell_groups (group_name, leader_id) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $leader_id);
            $stmt->execute();
            log_action($mysqli, $_SESSION['user_id'], 'Admin', 'CREATE_GROUP', "Created Cell Group '$name' with Leader ID $leader_id", 'High');
            $message = "<div class='cellgroup-message success'>✅ Cell Group '$name' created successfully.</div>";
        } else {
            $message = "<div class='cellgroup-message info'>⚠️ Please enter a name and select a leader.</div>";
        }
    }

    // ARCHIVE GROUP
    elseif ($action === 'archive_group') {
        $group_id = intval($_POST['group_id']);
        $stmt = $mysqli->prepare("UPDATE cell_groups SET status='archived', archived_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        log_action($mysqli, $_SESSION['user_id'], 'Admin', 'ARCHIVE_GROUP', "Archived Cell Group ID #$group_id", 'Normal');
        $message = "<div class='cellgroup-message success'>✅ Group archived successfully.</div>";
    }

    // ✅ RESTORE GROUP
    elseif ($action === 'restore_group') {
        $group_id = intval($_POST['group_id']);
        $stmt = $mysqli->prepare("UPDATE cell_groups SET status='active', archived_at=NULL WHERE id=?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        log_action($mysqli, $_SESSION['user_id'], 'Admin', 'RESTORE_GROUP', "Restored Cell Group ID #$group_id", 'Normal');
        $message = "<div class='cellgroup-message success'>✅ Group restored successfully.</div>";
    }
}

// =============================
// FETCH DATA
// =============================
$groups_query = $mysqli->query("
    SELECT cg.*, CONCAT(l.leader_name,' (',l.email,')') AS leader_name,
           (SELECT COUNT(*) FROM cell_group_members m WHERE m.cell_group_id=cg.id) AS member_count
    FROM cell_groups cg
    LEFT JOIN leaders l ON l.leader_id=cg.leader_id
    ORDER BY cg.status, cg.group_name
");
$groups = $groups_query->fetch_all(MYSQLI_ASSOC);

$leaders_query = $mysqli->query("
    SELECT l.leader_id, l.leader_name
    FROM leaders l
    WHERE NOT EXISTS (SELECT 1 FROM cell_groups cg WHERE cg.leader_id=l.leader_id)
");
$leaders_without_groups = $leaders_query->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Manage Cell Groups</title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">
    <h1>📋 Cell Group Management</h1>
    <?= $message ?>

    <!-- CREATE GROUP -->
    <div class="cellgroup-section">
      <h2>🧩 Create New Cell Group</h2>
      <form method="POST" class="cellgroup-form">
        <input type="hidden" name="action" value="create_group">
        <label>Group Name:</label>
        <input type="text" name="group_name" required>
        <label>Assign Leader:</label>
        <select name="leader_id" required>
          <option value="">Select Leader</option>
          <?php foreach($leaders_without_groups as $l): ?>
          <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="primary-btn">Create Group</button>
      </form>
    </div>

    <!-- GROUP LIST -->
    <div class="cellgroup-section">
      <h2>📊 Existing Cell Groups</h2>
      <table class="cellgroup-table">
        <tr><th>ID</th><th>Name</th><th>Leader</th><th>Status</th><th>Members</th><th>Actions</th></tr>
        <?php foreach($groups as $g): ?>
        <tr>
          <td><?= $g['id'] ?></td>
          <td><?= htmlspecialchars($g['group_name']) ?></td>
          <td><?= htmlspecialchars($g['leader_name'] ?? 'N/A') ?></td>
          <td><?= ucfirst($g['status']) ?></td>
          <td><?= $g['member_count'] ?></td>
            <td style="display:flex; gap:6px; flex-wrap: wrap;">
            <!-- Archive / Restore toggle -->
            <form method="POST" style="display:inline;">
                <?php if ($g['status'] === 'archived'): ?>
                <input type="hidden" name="action" value="restore_group">
                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                <button type="submit" class="primary-btn">Restore</button>
                <?php else: ?>
                <input type="hidden" name="action" value="archive_group">
                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                <button type="submit" class="secondary-btn">Archive</button>
                <?php endif; ?>
            </form>
            <!-- Manage Members -->
            <form method="GET" action="view_group_members.php" style="display:inline;">
                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                <button type="submit" class="primary-btn">View Members</button>
            </form>

            <!-- Manage Group (Dispersal / Undo) -->
            <form method="GET" action="admin_cell_group_manage.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <button type="submit" class="secondary-btn">Manage</button>
            </form>
            </td>
            <!-- Manage Button (goes to new page) -->
            <form method="GET" action="admin_cell_group_manage.php" style="display:inline;">
              <input type="hidden" name="id" value="<?= $g['id'] ?>">
              <button type="submit" class="primary-btn">Manage</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
</body>
</html>
