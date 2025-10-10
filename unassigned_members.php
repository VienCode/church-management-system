<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

// Fetch all unassigned members (role_id = 3, leader_id IS NULL)
$sql = "
    SELECT id, user_code, firstname, lastname, email, contact 
    FROM users
    WHERE role_id = 3 
    AND leader_id IS NULL
    AND id IN (SELECT DISTINCT user_id FROM attendance) -- ensures they’ve had activity before
    ORDER BY lastname ASC
";
$result = $mysqli->query($sql);
$members = $result->fetch_all(MYSQLI_ASSOC);

// Fetch available leaders
$leaders = $mysqli->query("
    SELECT leader_id, leader_name 
    FROM leaders 
    ORDER BY leader_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Handle reassignment form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $member_id = $_POST['member_id'];
    $leader_id = $_POST['leader_id'];

    $stmt = $mysqli->prepare("UPDATE users SET leader_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $leader_id, $member_id);
    $stmt->execute();
    $stmt->close();

    header("Location: unassigned_members.php?msg=✅ Member reassigned successfully!");
    exit();
}

$successMessage = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Unassigned Members | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
.success {
    background: #e6ffed;
    color: #256029;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
}
select, button {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.assign-btn {
    background: #0271c0;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 600;
}
.assign-btn:hover { background: #02589b; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>🔄 Reassign Unassigned Members</h1>
            <p>These are members who lost their leaders and need reassignment.</p>

            <?php if ($successMessage): ?>
                <div class="success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <?php if (empty($members)): ?>
                <p>No unassigned members found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User Code</th>
                            <th>Full Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Assign New Leader</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; foreach ($members as $m): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($m['user_code']) ?></td>
                                <td><?= htmlspecialchars($m['firstname'].' '.$m['lastname']) ?></td>
                                <td><?= htmlspecialchars($m['contact']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                        <select name="leader_id" required>
                                            <option value="" disabled selected>Select Leader</option>
                                            <?php foreach ($leaders as $l): ?>
                                                <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign" class="assign-btn">Assign</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
