<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
restrict_to_roles([ROLE_ADMIN]);
global $mysqli;

$leader_id = intval($_GET['leader_id'] ?? 0);
$message = "";

// =======================================================
// VERIFY LEADER EXISTS
// =======================================================
$leader_query = $mysqli->prepare("
    SELECT l.leader_id, l.leader_name, l.email, cg.id AS group_id, cg.group_name
    FROM leaders l
    LEFT JOIN cell_groups cg ON cg.leader_id = l.leader_id
    WHERE l.leader_id = ?
");
$leader_query->bind_param("i", $leader_id);
$leader_query->execute();
$leader = $leader_query->get_result()->fetch_assoc();

if (!$leader) {
    echo "<h2 style='padding:40px;text-align:center;'>⚠️ Leader not found.</h2>";
    exit;
}

$group_id = $leader['group_id'];

// =======================================================
// FETCH MEMBERS UNDER THIS LEADER
// =======================================================
$members_query = $mysqli->prepare("
    SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name, u.email
    FROM cell_group_members m
    JOIN users u ON u.id = m.member_id
    WHERE m.cell_group_id = ?
");
$members_query->bind_param("i", $group_id);
$members_query->execute();
$members = $members_query->get_result()->fetch_all(MYSQLI_ASSOC);

// =======================================================
// FETCH OTHER ACTIVE GROUPS (TARGETS)
// =======================================================
$target_groups = $mysqli->query("
    SELECT cg.id, cg.group_name, CONCAT(l.leader_name, ' (', l.email, ')') AS leader_name
    FROM cell_groups cg
    JOIN leaders l ON l.leader_id = cg.leader_id
    WHERE cg.status = 'active' AND cg.leader_id <> $leader_id
    ORDER BY cg.group_name
")->fetch_all(MYSQLI_ASSOC);

// =======================================================
// HANDLE REASSIGNMENT
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source_group = intval($_POST['source_group']);
    $target_group = intval($_POST['target_group']);
    $selected_members = $_POST['members'] ?? [];

    if (count($selected_members) === 0) {
        $message = "<div class='cellgroup-message info'>⚠️ Please select at least one member to reassign.</div>";
    } else {
        $mysqli->begin_transaction();
        try {
            foreach ($selected_members as $member_id) {
                $member_id = intval($member_id);

                // Remove from old group
                $del = $mysqli->prepare("DELETE FROM cell_group_members WHERE cell_group_id=? AND member_id=?");
                $del->bind_param("ii", $source_group, $member_id);
                $del->execute();

                // Add to new group
                $ins = $mysqli->prepare("
                    INSERT IGNORE INTO cell_group_members (cell_group_id, member_id, joined_at)
                    VALUES (?, ?, NOW())
                ");
                $ins->bind_param("ii", $target_group, $member_id);
                $ins->execute();
            }

            log_action($mysqli, $_SESSION['user_id'], 'Admin', 'REASSIGN_MEMBERS',
                "Reassigned " . count($selected_members) . " members from group #$source_group to group #$target_group", 'High');

            $mysqli->commit();
            $message = "<div class='cellgroup-message success'>✅ Members successfully reassigned!</div>";
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "<div class='cellgroup-message error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reassign Members — Cell Group Management</title>
<link rel="stylesheet" href="styles_system.css">
<style>
/* Local enhancements */
.reassign-section {
  background: #fff;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  margin-bottom: 25px;
  border: 1px solid #e9ecef;
}
.reassign-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.reassign-header h2 {
  margin: 0;
  color: #1e3a8a;
  font-size: 1.5em;
}
.members-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
.members-table th {
  background: linear-gradient(135deg, #1e3a8a, #357abd);
  color: white;
  padding: 10px;
  text-align: left;
}
.members-table td {
  padding: 10px;
  border-bottom: 1px solid #eee;
}
.members-table tr:hover {
  background-color: #f9fafb;
}
.reassign-actions {
  margin-top: 20px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.selected-count {
  color: #1e3a8a;
  font-weight: 600;
  margin-left: 10px;
}
.back-btn {
  background: #f8f9fa;
  border: 1px solid #cbd5e1;
  color: #1e3a8a;
  padding: 10px 15px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
}
.back-btn:hover {
  background: #e0e7ff;
  color: #1e3a8a;
}
.confirm-modal {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  display: none;
  justify-content: center;
  align-items: center;
  background: rgba(0,0,0,0.4);
  z-index: 999;
}
.confirm-box {
  background: white;
  border-radius: 10px;
  padding: 30px;
  text-align: center;
  box-shadow: 0 5px 25px rgba(0,0,0,0.2);
}
.confirm-box h3 {
  margin-top: 0;
  color: #1e3a8a;
}
.confirm-buttons {
  margin-top: 20px;
  display: flex;
  justify-content: center;
  gap: 10px;
}
</style>
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>

  <div class="content-area">
    <h1>🔁 Reassign Members</h1>
    <?= $message ?>

    <div class="reassign-section">
      <div class="reassign-header">
        <h2>📋 Current Group: <?= htmlspecialchars($leader['group_name'] ?? 'No Group') ?></h2>
      </div>
      <p><strong>Leader:</strong> <?= htmlspecialchars($leader['leader_name']) ?> (<?= htmlspecialchars($leader['email']) ?>)</p>

      <?php if (count($members) === 0): ?>
        <p><em>No members assigned to this leader.</em></p>
      <?php else: ?>
      <form method="POST" id="reassignForm">
        <input type="hidden" name="source_group" value="<?= $group_id ?>">

        <table class="members-table">
          <thead>
            <tr><th>Select</th><th>Member Name</th><th>Email</th></tr>
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

        <div class="reassign-actions">
          <label>Reassign to Group:</label>
          <select name="target_group" required>
            <option value="">Select Target Group</option>
            <?php foreach($target_groups as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['group_name']) ?> — <?= htmlspecialchars($t['leader_name']) ?></option>
            <?php endforeach; ?>
          </select>

          <button type="button" id="confirmButton" class="primary-btn">Confirm Reassignment</button>
          <span class="selected-count">0 members selected</span>
          <a href="admin_cell_groups.php" class="back-btn">← Back to Groups</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="confirm-modal" id="confirmModal">
  <div class="confirm-box">
    <h3>Confirm Reassignment</h3>
    <p>Are you sure you want to reassign the selected members?</p>
    <div class="confirm-buttons">
      <button class="primary-btn" id="yesConfirm">Yes, Proceed</button>
      <button class="secondary-btn" id="cancelConfirm">Cancel</button>
    </div>
  </div>
</div>

<script>
const form = document.getElementById('reassignForm');
const confirmModal = document.getElementById('confirmModal');
const confirmButton = document.getElementById('confirmButton');
const yesConfirm = document.getElementById('yesConfirm');
const cancelConfirm = document.getElementById('cancelConfirm');
const checkboxes = document.querySelectorAll('input[type="checkbox"][name="members[]"]');
const countSpan = document.querySelector('.selected-count');

checkboxes.forEach(cb => cb.addEventListener('change', () => {
  const count = document.querySelectorAll('input[type="checkbox"][name="members[]"]:checked').length;
  countSpan.textContent = `${count} member${count !== 1 ? 's' : ''} selected`;
}));

confirmButton.addEventListener('click', () => {
  const count = document.querySelectorAll('input[type="checkbox"][name="members[]"]:checked').length;
  if (count === 0) {
    alert('⚠️ Please select at least one member to reassign.');
    return;
  }
  confirmModal.style.display = 'flex';
});

cancelConfirm.addEventListener('click', () => confirmModal.style.display = 'none');
yesConfirm.addEventListener('click', () => form.submit());
</script>
</body>
</html>
    