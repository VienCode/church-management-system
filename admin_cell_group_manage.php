<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;
restrict_to_roles([ROLE_ADMIN]);

if (!isset($_GET['id'])) {
    header("Location: admin_cell_groups.php");
    exit;
}

$group_id = intval($_GET['id']);
$message = '';

// FETCH GROUP DETAILS
$stmt = $mysqli->prepare("
    SELECT cg.*, CONCAT(l.leader_name,' (',l.email,')') AS leader_name
    FROM cell_groups cg
    LEFT JOIN leaders l ON l.leader_id=cg.leader_id
    WHERE cg.id=?
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();

if (!$group) {
    echo "<div class='cellgroup-message error'>❌ Group not found.</div>";
    exit;
}

// HANDLE DISPERSAL ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'disperse_members') {
        $source = intval($_POST['source_group']);
        $target = ($_POST['target_group'] === 'unassigned') ? null : intval($_POST['target_group']);
        $members = $_POST['members'] ?? [];

        if ($source && count($members) > 0) {
            $mysqli->begin_transaction();
            try {
                $snapshot = [];
                foreach ($members as $member_id) {
                    $member_id = intval($member_id);
                    $mysqli->query("DELETE FROM cell_group_members WHERE cell_group_id=$source AND member_id=$member_id");
                    if ($target) {
                        $mysqli->query("INSERT IGNORE INTO cell_group_members (cell_group_id, member_id) VALUES ($target, $member_id)");
                    }
                    $snapshot[] = ['member_id'=>$member_id,'old_group'=>$source,'new_group'=>$target];
                }

                $json = json_encode($snapshot);
                $note = $_POST['note'] ?? '';
                $mysqli->query("INSERT INTO dispersal_log (source_group_id, performed_by_user_id, note, snapshot_json) VALUES ($source, {$_SESSION['user_id']}, '$note', '$json')");

                log_action($mysqli, $_SESSION['user_id'], 'Admin', 'DISPERSE_MEMBERS', "Dispersed members from Cell Group #$source", 'High');
                $mysqli->commit();
                $message = "<div class='cellgroup-message success'>✅ Members dispersed successfully.</div>";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "<div class='cellgroup-message error'>❌ Error dispersing members: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            $message = "<div class='cellgroup-message info'>⚠️ Select at least one member to disperse.</div>";
        }
    }

    elseif ($action === 'undo_dispersal') {
        $result = $mysqli->query("SELECT * FROM dispersal_log WHERE source_group_id=$group_id AND reverted=0 ORDER BY performed_at DESC LIMIT 1");
        if ($row = $result->fetch_assoc()) {
            $snapshot = json_decode($row['snapshot_json'], true);
            $mysqli->begin_transaction();
            try {
                foreach ($snapshot as $item) {
                    $mid = intval($item['member_id']);
                    $old = $item['old_group'];
                    $new = $item['new_group'];

                    if ($new) $mysqli->query("DELETE FROM cell_group_members WHERE cell_group_id=$new AND member_id=$mid");
                    if ($old) $mysqli->query("INSERT IGNORE INTO cell_group_members (cell_group_id, member_id) VALUES ($old, $mid)");
                }

                $mysqli->query("UPDATE dispersal_log SET reverted=1 WHERE id={$row['id']}");
                log_action($mysqli, $_SESSION['user_id'], 'Admin', 'UNDO_DISPERSAL', "Undid dispersal for Cell Group #$group_id", 'High');
                $mysqli->commit();
                $message = "<div class='cellgroup-message success'>✅ Undo successful.</div>";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "<div class='cellgroup-message error'>❌ Undo failed: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            $message = "<div class='cellgroup-message info'>⚠️ No dispersal record to undo.</div>";
        }
    }
}

// FETCH MEMBERS
$members = $mysqli->query("
    SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name
    FROM cell_group_members m
    JOIN users u ON m.member_id=u.id
    WHERE m.cell_group_id=$group_id
")->fetch_all(MYSQLI_ASSOC);

// FETCH OTHER ACTIVE GROUPS
$targets = $mysqli->query("SELECT id, group_name FROM cell_groups WHERE id<>$group_id AND status='active'")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Cell Group — <?= htmlspecialchars($group['group_name']) ?></title>
<link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="content-area">

    <h1>⚙️ Manage Group — <?= htmlspecialchars($group['group_name']) ?></h1>
    <p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?></p>
    <?= $message ?>

    <div class="cellgroup-section">
      <h2>👥 Members</h2>
      <form method="POST" class="cellgroup-form">
        <input type="hidden" name="action" value="disperse_members">
        <input type="hidden" name="source_group" value="<?= $group_id ?>">

        <div class="member-list">
          <?php foreach($members as $m): ?>
          <div class="member-card">
            <label><input type="checkbox" name="members[]" value="<?= $m['id'] ?>"> <?= htmlspecialchars($m['name']) ?></label>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="dispersal-controls">
          <label>Target Group:</label>
          <select name="target_group">
            <option value="unassigned">Unassigned (Remove)</option>
            <?php foreach($targets as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['group_name']) ?></option>
            <?php endforeach; ?>
          </select>

          <label>Admin Note:</label>
          <textarea name="note" rows="3"></textarea>

          <button type="submit" class="primary-btn">Disperse Selected</button>
        </div>
      </form>

      <form method="POST" style="margin-top:15px;">
        <input type="hidden" name="action" value="undo_dispersal">
        <button type="submit" class="secondary-btn">Undo Last Dispersal</button>
      </form>
    </div>

    <a href="admin_cell_groups.php" class="secondary-btn" style="margin-top:20px; display:inline-block;">⬅ Back to All Groups</a>

  </div>
</div>
</body>
</html>
