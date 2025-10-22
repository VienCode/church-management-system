<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_ADMIN]); // Admin-only access

$message = '';

// ==========================================================
// HANDLE MEMBER ASSIGNMENT FORM
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['members']) && isset($_POST['target_group'])) {
    $members = $_POST['members'];
    $target_group = intval($_POST['target_group']);

    if (count($members) === 0) {
        $message = "<div class='cellgroup-message info'>⚠️ Please select at least one member to assign.</div>";
    } else {
        $mysqli->begin_transaction();
        try {
            foreach ($members as $member_id) {
                $member_id = intval($member_id);

                // 1️⃣ Update user's cell_group_id
                $stmt = $mysqli->prepare("UPDATE users SET cell_group_id=? WHERE id=?");
                $stmt->bind_param("ii", $target_group, $member_id);
                $stmt->execute();

                // 2️⃣ Add entry to cell_group_members
                $stmt2 = $mysqli->prepare("INSERT IGNORE INTO cell_group_members (cell_group_id, member_id) VALUES (?, ?)");
                $stmt2->bind_param("ii", $target_group, $member_id);
                $stmt2->execute();
            }

            $mysqli->commit();
            log_action($mysqli, $_SESSION['user_id'], 'Admin', 'ASSIGN_TO_GROUP', "Assigned ".count($members)." members to group #$target_group", 'Normal');
            $message = "<div class='cellgroup-message success'>✅ Members successfully assigned to selected group.</div>";
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "<div class='cellgroup-message error'>❌ Assignment failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// ==========================================================
// FETCH UNASSIGNED MEMBERS
// ==========================================================
$unassigned_query = $mysqli->query("
    SELECT id, firstname, lastname, email
    FROM users
    WHERE role_id = 3 AND (cell_group_id IS NULL OR cell_group_id = 0)
    ORDER BY firstname
");
$unassigned_members = $unassigned_query->fetch_all(MYSQLI_ASSOC);

// ==========================================================
// FETCH ACTIVE CELL GROUPS
// ==========================================================
$groups_query = $mysqli->query("
    SELECT id, group_name
    FROM cell_groups
    WHERE status='active'
    ORDER BY group_name
");
$groups = $groups_query->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>📜 Unassigned Members</title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">

    <h1>📜 Unassigned Members</h1>
    <p>These members currently have no cell group assigned. Select one or more and assign them to a group.</p>
    <?= $message ?>

    <!-- MEMBER ASSIGNMENT FORM -->
    <form method="POST" class="cellgroup-form">
      <div class="cellgroup-section">
        <h2>👥 Unassigned Members</h2>
        <?php if (count($unassigned_members) > 0): ?>
        <table class="cellgroup-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all"></th>
              <th>Name</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($unassigned_members as $member): ?>
            <tr>
              <td><input type="checkbox" name="members[]" value="<?= $member['id'] ?>"></td>
              <td><?= htmlspecialchars($member['firstname'].' '.$member['lastname']) ?></td>
              <td><?= htmlspecialchars($member['email']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="assign-controls">
          <label><strong>📦 Assign to Group:</strong></label>
          <select name="target_group" required>
            <option value="">Select a Cell Group</option>
            <?php foreach($groups as $g): ?>
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="primary-btn">Assign Selected Members</button>
        </div>

        <?php else: ?>
        <div class="cellgroup-message info">✅ All members are currently assigned to groups.</div>
        <?php endif; ?>
      </div>
    </form>

  </div>
</div>

<!-- Select All Checkbox Script -->
<script>
document.getElementById('select-all')?.addEventListener('change', function(e) {
  const checkboxes = document.querySelectorAll('input[name="members[]"]');
  checkboxes.forEach(cb => cb.checked = e.target.checked);
});
</script>

</body>
</html>
