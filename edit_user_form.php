<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php?msg=Invalid user ID");
    exit;
}

$user_id = intval($_GET['id']);

// Fetch user data
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: admin_dashboard.php?msg=User not found");
    exit;
}

// Fetch roles
$roles_result = $mysqli->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");

// Fetch leaders
$leaders_result = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User - Admin</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        /* --- Form Styling --- */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 700px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        form label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-top: 15px;
            margin-bottom: 6px;
        }

        form input[type="text"],
        form input[type="email"],
        form input[type="password"],
        form input[type="number"],
        form select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9fafb;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        form input:focus,
        form select:focus {
            outline: none;
            border-color: #007bff;
            background-color: #fff;
            box-shadow: 0 0 4px rgba(0, 123, 255, 0.3);
        }

        form input:hover,
        form select:hover {
            border-color: #999;
        }

        .primary-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .primary-btn:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .secondary-btn {
            background-color: #ccc;
            color: #333;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .secondary-btn:hover {
            background-color: #bbb;
            transform: translateY(-1px);
        }

        .form-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .content-header h1 {
            color: #222;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .note {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
    </style>
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
            <h1 class="page-title">✏️ Edit User</h1>
            <p class="note">Modify the fields below to update this user’s details.</p>
        </div>

        <div class="form-container">
            <form action="edit_user.php" method="POST">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>First Name</label>
                        <input type="text" name="firstname" value="<?= htmlspecialchars($user['firstname']) ?>" required>
                    </div>
                    <div style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="lastname" value="<?= htmlspecialchars($user['lastname']) ?>" required>
                    </div>
                </div>

                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                <label>Contact</label>
                <input type="text" name="contact" value="<?= htmlspecialchars($user['contact']) ?>">

                <label>Age</label>
                <input type="number" name="age" min="0" max="120" value="<?= htmlspecialchars($user['age']) ?>">

                <label>Address</label>
                <input type="text" name="user_address" value="<?= htmlspecialchars($user['user_address']) ?>">

                <label>Role</label>
                <select name="role_id" id="roleSelect" required>
                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                        <option value="<?= $role['role_id'] ?>" <?= ($role['role_id'] == $user['role_id']) ? 'selected' : '' ?>>
                            <?= ucfirst($role['role_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div id="leaderSelectContainer" style="display:<?= ($user['role_id'] == 3) ? 'block' : 'none' ?>; margin-top:15px;">
                    <label>Select Leader (For Members Only)</label>
                    <select name="leader_id">
                        <option value="" disabled>Select Leader</option>
                        <?php while ($leader = $leaders_result->fetch_assoc()): ?>
                            <option value="<?= $leader['leader_id'] ?>" <?= ($user['leader_id'] == $leader['leader_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($leader['leader_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="primary-btn">💾 Update User</button>
                    <a href="admin_dashboard.php" class="secondary-btn">⬅ Back</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide leader dropdown dynamically
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
