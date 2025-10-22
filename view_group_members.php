<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_ADMIN]);

if (!isset($_GET['group_id'])) {
    header("Location: admin_cell_groups.php");
    exit;
}

$group_id = intval($_GET['group_id']);
$message = '';

// ==================================================
// FETCH GROUP DETAILS
// ==================================================
$stmt = $mysqli->prepare("
    SELECT cg.*, CONCAT(l.leader_name,' (',l.email,')') AS leader_name
    FROM cell_groups cg
    LEFT JOIN leaders l ON l.leader_id = cg.leader_id
    WHERE cg.id = ?
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
    echo "<div class='cellgroup-message error'>❌ Group not found.</div>";
    exit;
}

// ==================================================
// HANDLE MEMBER REMOVAL
// ==================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_members'])) {
    $members = $_POST['members'] ?? [];

    if (count($members) === 0) {
        $message = "<div class='cellgroup-message info'>⚠️ Please select at least one member to remove.</div>";
    } else {
        $mysqli->begin_transaction();
        try {
            foreach ($members as $member_id) {
                $mid = intval($member_id);

                // Remove from cell_group_members
                $stmt = $mysqli->prepare("DELETE FROM cell_group_members WHERE member_id=? AND cell_group_id=?");
                $stmt->bind_param("ii", $mid, $group_id);
                $stmt->execute();

                // Set users.cell_group_id to NULL
                $stmt2 = $mysqli->prepare("UPDATE users SET cell_group_id=NULL WHERE id=?");
                $stmt2->bind_param("i", $mid);
                $stmt2->execute();
            }

            $mysqli->commit();
            log_action($mysqli, $_SESSION['user_id'], 'Admin', 'REMOVE_FROM_GROUP', "Removed ".count($members)." members from group #$group_id", 'High');
            $message = "<div class='cellgroup-message success'>✅ Members removed successfully.</div>";
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "<div class='cellgroup-message error'>❌ Removal failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// ==================================================
// FETCH GROUP MEMBERS
// ==================================================
$stmt = $mysqli->prepare("
    SELECT u.id, CONCAT(u.firstname,' ',u.lastname) AS name, u.email
    FROM cell_group_members m
    JOIN users u ON u.id = m.member_id
    WHERE m.cell_group_id = ?
    ORDER BY u.firstname
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Log page access
log_action($mysqli, $_SESSION['user_id'], 'Admin', 'VIEW_GROUP_MEMBERS', "Viewed members of group #$group_id", 'Low');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>View Members — <?= htmlspecialchars($group['group_name']) ?></title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">

    <h1>👥 Members of <?= htmlspecialchars($group['group_name']) ?></h1>
    <p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name'] ?? 'N/A') ?></p>
    <?= $message ?>

    <form method="POST">
      <?php if (count($members) > 0): ?>
      <table class="cellgroup-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Name</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($members as $m): ?>
          <tr>
            <td><input type="checkbox" name="members[]" value="<?= $m['id'] ?>"></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['email']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="assign-controls">
        <button type="submit" name="remove_members" class="secondary-btn">Remove Selected Members</button>
        <a href="admin_cell_groups.php" class="primary-btn">⬅ Back to Cell Groups</a>
      </div>
      <?php else: ?>
      <div class="cellgroup-message info">✅ No members found in this group.</div>
      <a href="admin_cell_groups.php" class="primary-btn">⬅ Back to Cell Groups</a>
      <?php endif; ?>
    </form>

  </div>
</div>

<script>
document.getElementById('select-all')?.addEventListener('change', function(e) {
  const checkboxes = document.querySelectorAll('input[name="members[]"]');
  checkboxes.forEach(cb => cb.checked = e.target.checked);
});
</script>

</body>
</html>
