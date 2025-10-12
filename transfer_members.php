<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = intval($_GET['group_id'] ?? 0);
if ($group_id <= 0) die("<h2>❌ Invalid group ID.</h2>");

// Fetch all members in this group
$members = $mysqli->query("
    SELECT u.id, u.user_code, CONCAT(u.firstname, ' ', u.lastname) AS fullname
    FROM cell_group_members cgm
    JOIN users u ON u.user_code = cgm.user_code
    WHERE cgm.cell_group_id = $group_id
    ORDER BY u.lastname ASC
");

// Fetch all other groups
$groups = $mysqli->query("
    SELECT id, group_name FROM cell_groups WHERE status='active' AND id != $group_id ORDER BY group_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>🔄 Transfer Members | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.1); max-width: 900px; margin: 30px auto; }
h1 { color:#0271c0; }
button { padding:8px 12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; background:#0271c0; color:white; }
button:hover { background:#02589b; }
select, input[type=checkbox] { padding:6px; border-radius:6px; border:1px solid #ccc; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<div class="content-area">
<div class="container">
<h1>🔄 Transfer Members</h1>
<form method="POST" action="transfer_members_action.php">
    <input type="hidden" name="from_group_id" value="<?= $group_id ?>">
    <label><strong>Select New Group:</strong></label><br>
    <select name="to_group_id" required>
        <option value="">Select Destination Group</option>
        <?php while ($g = $groups->fetch_assoc()): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
        <?php endwhile; ?>
    </select>

    <table>
        <thead><tr><th><input type="checkbox" id="selectAll"></th><th>User Code</th><th>Full Name</th></tr></thead>
        <tbody>
            <?php while ($m = $members->fetch_assoc()): ?>
            <tr>
                <td><input type="checkbox" name="member_ids[]" value="<?= $m['user_code'] ?>"></td>
                <td><?= htmlspecialchars($m['user_code']) ?></td>
                <td><?= htmlspecialchars($m['fullname']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <br>
    <button type="submit" name="transfer_members">🔄 Transfer Selected Members</button>
</form>
</div>
</div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('input[name="member_ids[]"]').forEach(cb => cb.checked = this.checked);
});
</script>
</body>
</html>
