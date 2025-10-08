<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admins only

// Fetch roles
$roles_result = $mysqli->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");

// Fetch leaders
$leaders_result = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User - Admin</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="logo-section">
            <div class="logo-placeholder"><span>⛪</span></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <ul class="nav-menu">
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>

            <li class="nav-divider"></li>
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
            <li><a href="admin_dashboard.php"><span>⚙️</span> Manage Users</a></li>
            <li><a href="logout.php"><span>🚪</span> Logout</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="content-area">
        <div class="content-header">
            <h1 class="page-title">➕ Add New User</h1>
        </div>

        <div class="form-container" style="background:white; padding:25px; border-radius:10px; max-width:700px;">
            <form action="add_user.php" method="POST">
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>First Name</label>
                        <input type="text" name="firstname" required>
                    </div>
                    <div style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="lastname" required>
                    </div>
                </div>

                <label>Email</label>
                <input type="email" name="email" required>

                <label>Password</label>
                <input type="password" name="pwd" required placeholder="At least 8 characters">

                <label>Contact</label>
                <input type="text" name="contact" placeholder="09XXXXXXXXX">

                <label>Age</label>
                <input type="number" name="age" min="0" max="120">

                <label>Address</label>
                <input type="text" name="user_address">

                <label>Role</label>
                <select name="role_id" id="roleSelect" required>
                    <option value="" disabled selected>Select Role</option>
                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                        <option value="<?= $role['role_id'] ?>"><?= ucfirst($role['role_name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <div id="leaderSelectContainer" style="display:none; margin-top:15px;">
                    <label>Select Leader (For Members Only)</label>
                    <select name="leader_id">
                        <option value="" disabled selected>Select Leader</option>
                        <?php while ($leader = $leaders_result->fetch_assoc()): ?>
                            <option value="<?= $leader['leader_id'] ?>"><?= htmlspecialchars($leader['leader_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <br>
                <button type="submit" class="primary-btn">Add User</button>
                <a href="admin_dashboard.php" class="secondary-btn" style="margin-left:10px;">⬅ Back</a>
            </form>
        </div>
    </div>
</div>

<script>
// Show Leader dropdown only if role_id == 3 (Member)
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('roleSelect');
    const leaderSelectContainer = document.getElementById('leaderSelectContainer');
    roleSelect.addEventListener('change', function() {
        leaderSelectContainer.style.display = (this.value == '3') ? 'block' : 'none';
    });
});
</script>
</body>
</html>
