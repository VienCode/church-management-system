<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admin only

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php?msg=⚠️ Invalid user ID.");
    exit;
}

$user_id = intval($_GET['id']);
$result = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$result->bind_param("i", $user_id);
$result->execute();
$user = $result->get_result()->fetch_assoc();

if (!$user) {
    header("Location: admin_dashboard.php?msg=⚠️ User not found.");
    exit;
}

// Fetch all roles
$roles = $mysqli->query("SELECT * FROM roles ORDER BY role_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="content-area">
    <h2>Edit User Account</h2>
    <form action="edit_user.php" method="POST" style="max-width:500px; background:#fff; padding:20px; border-radius:10px;">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">

        <label>First Name</label>
        <input type="text" name="firstname" value="<?= htmlspecialchars($user['firstname']) ?>" required>

        <label>Last Name</label>
        <input type="text" name="lastname" value="<?= htmlspecialchars($user['lastname']) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Role</label>
        <select name="role_id" required>
            <?php while ($role = $roles->fetch_assoc()): ?>
                <option value="<?= $role['role_id'] ?>" <?= $role['role_id'] == $user['role_id'] ? 'selected' : '' ?>>
                    <?= ucfirst($role['role_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <div style="margin-top:15px;">
            <button type="submit" class="primary-btn">💾 Save Changes</button>
            <a href="admin_dashboard.php" class="secondary-btn">↩ Back</a>
        </div>
    </form>
</div>
</body>
</html>
