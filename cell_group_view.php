<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

$group_id = $_GET['group_id'] ?? 0;
$search = trim($_GET['search'] ?? '');

// ✅ Handle member unassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign_member'])) {
    $member_id = intval($_POST['member_id']);
    $member = $mysqli->query("SELECT CONCAT(firstname, ' ', lastname) AS full_name FROM users WHERE id = $member_id")->fetch_assoc();
    $member_name = $member['full_name'] ?? 'Unknown Member';

    $update = $mysqli->prepare("
        UPDATE users 
        SET leader_id = NULL,
            cell_group_id = NULL,
            last_unassigned_at = NOW(),
            last_leader_name = (
                SELECT l.leader_name FROM leaders l 
                JOIN cell_groups cg ON cg.leader_id = l.leader_id 
                WHERE cg.id = ?
            )
        WHERE id = ?
    ");
    $update->bind_param("ii", $group_id, $member_id);
    $update->execute();

    if ($update->affected_rows > 0) {
        $success = "✅ $member_name was successfully unassigned from this group.";
    } else {
        $error = "⚠️ Failed to unassign $member_name.";
    }
    $update->close();
}

// ✅ Handle member reassignment (move)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_member'])) {
    $member_id = intval($_POST['member_id']);
    $new_group_id = intval($_POST['new_group_id']);

    // Fetch new leader ID from target group
    $leader_stmt = $mysqli->prepare("SELECT leader_id FROM cell_groups WHERE id = ?");
    $leader_stmt->bind_param("i", $new_group_id);
    $leader_stmt->execute();
    $new_leader = $leader_stmt->get_result()->fetch_assoc();
    $leader_stmt->close();

    if ($new_leader) {
        $leader_id = $new_leader['leader_id'];
        $move = $mysqli->prepare("UPDATE users SET leader_id = ?, cell_group_id = ? WHERE id = ?");
        $move->bind_param("iii", $leader_id, $new_group_id, $member_id);
        $move->execute();

        if ($move->affected_rows > 0) {
            $success = "✅ Member successfully moved to the new group.";
        } else {
            $error = "⚠️ Failed to move the member.";
        }
        $move->close();
    } else {
        $error = "❌ Invalid target group selected.";
    }
}

// ✅ Fetch group and leader info
$stmt = $mysqli->prepare("
    SELECT cg.group_name, l.leader_name, l.email AS leader_email, l.contact AS leader_contact
    FROM cell_groups cg
    LEFT JOIN leaders l ON cg.leader_id = l.leader_id
    WHERE cg.id = ?
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ✅ Fetch all members of this group
$search_condition = '';
if (!empty($search)) {
    $search_condition = "AND (
        u.firstname LIKE '%$search%' OR 
        u.lastname LIKE '%$search%' OR 
        u.email LIKE '%$search%' OR 
        u.user_code LIKE '%$search%'
    )";
}

$members = $mysqli->query("
    SELECT 
        u.id,
        u.user_code, 
        CONCAT(u.firstname, ' ', u.lastname) AS member_name, 
        u.email, 
        r.role_name
    FROM cell_group_members cgm
    JOIN users u ON cgm.member_id = u.id
    JOIN roles r ON u.role_id = r.role_id
    WHERE cgm.cell_group_id = $group_id
    $search_condition
    ORDER BY u.lastname ASC
");

// ✅ Fetch all cell groups for move dropdown
$all_groups = $mysqli->query("
    SELECT id, group_name 
    FROM cell_groups 
    WHERE id != $group_id 
    ORDER BY group_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cell Group Details | <?= htmlspecialchars($group['group_name']) ?></title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container { background:#fff; padding:24px; border-radius:12px; max-width:1000px; margin:30px auto; box-shadow:0 2px 10px rgba(0,0,0,.08); }
h1 { color:#0271c0; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6e6e6; text-align:center; }
th { background:#0271c0; color:white; }
.success, .error { padding:10px; border-radius:8px; margin-bottom:15px; font-weight:600; }
.success { background:#e6ffed; color:#1b6b33; }
.error { background:#ffe6e6; color:#a11b1b; }
.remove-btn, .move-btn {
    border:none; border-radius:6px; padding:6px 12px; cursor:pointer; font-weight:600;
}
.remove-btn { background:#dc3545; color:white; }
.remove-btn:hover { background:#a72828; }
.move-btn { background:#28a745; color:white; margin-left:5px; }
.move-btn:hover { background:#1e7e34; }
select.move-select { padding:6px; border-radius:6px; border:1px solid #ccc; }
.back-btn {
    background:#0271c0; color:white; padding:10px 16px; border-radius:8px;
    text-decoration:none; display:inline-block; margin-top:20px;
}
.back-btn:hover { background:#02589b; }
.search-bar {
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:15px;
}
.search-bar input {
    flex:1;
    padding:8px;
    border-radius:6px;
    border:1px solid #ccc;
}
.search-bar button {
    background:#0271c0;
    color:white;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
}
.search-bar button:hover {
    background:#02589b;
}
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>👥 <?= htmlspecialchars($group['group_name']) ?> - Members</h1>
            <p><strong>Leader:</strong> <?= htmlspecialchars($group['leader_name']) ?><br>
               <strong>Email:</strong> <?= htmlspecialchars($group['leader_email']) ?><br>
               <strong>Contact:</strong> <?= htmlspecialchars($group['leader_contact']) ?></p>

            <?php if(isset($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
            <?php if(isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>

            <!-- 🔍 Search Bar -->
            <form method="GET" class="search-bar">
                <input type="hidden" name="group_id" value="<?= htmlspecialchars($group_id) ?>">
                <input type="text" name="search" placeholder="Search by name, email, or user code..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="cell_group_view.php?group_id=<?= $group_id ?>" style="text-decoration:none; background:#ccc; color:black; padding:8px 12px; border-radius:8px;">Reset</a>
                <?php endif; ?>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>User Code</th>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members->num_rows === 0): ?>
                        <tr><td colspan="5">No members found in this group.</td></tr>
                    <?php else: while ($m = $members->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['user_code']) ?></td>
                            <td><?= htmlspecialchars($m['member_name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['role_name']) ?></td>
                            <td>
                                <!-- Unassign Member -->
                                <form method="POST" onsubmit="return confirm('Remove this member from the group?');" style="display:inline;">
                                    <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                    <button type="submit" name="unassign_member" class="remove-btn">Remove</button>
                                </form>

                                <!-- Move Member -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                    <select name="new_group_id" class="move-select" required>
                                        <option value="" disabled selected>Move to...</option>
                                        <?php
                                        $all_groups->data_seek(0);
                                        while ($g = $all_groups->fetch_assoc()):
                                        ?>
                                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="submit" name="move_member" class="move-btn">Move</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <a href="cell_groups_admin.php" class="back-btn">⬅ Back</a>
        </div>
    </div>
</div>
</body>
</html>
