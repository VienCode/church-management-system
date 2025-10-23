<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admins only

// Fetch all roles except Non-Member (role_id = 4)
$roles_result = $mysqli->query("
    SELECT role_id, role_name 
    FROM roles 
    WHERE role_id != 4 
    ORDER BY role_name ASC
");

// Fetch all leaders
$leaders_result = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User</title>
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

        .msg {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .msg.success {
            background: #e6f9ec;
            color: #1a7f37;
            border: 1px solid #9ae2af;
        }

        .msg.error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f5c2c0;
        }
    </style>
</head>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Content -->
    <div class="content-area">
        <div class="content-header">
            <h1 class="page-title">➕ Add New User</h1>
            <p class="note">
                Fill out the details below to manually create a user account.  
                <strong>Note:</strong> “Non-Member” accounts must register themselves through the public registration page.
            </p>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="msg <?= str_starts_with($_SESSION['msg'], '✅') ? 'success' : 'error' ?>">
                <?= htmlspecialchars($_SESSION['msg']) ?>
            </div>
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        <div class="form-container">
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
                <input type="password" name="password" required placeholder="Enter temporary password">

                <label>Contact</label>
                <input type="text" name="contact" placeholder="Optional">

                <label>Age</label>
                <input type="number" name="age" min="0" max="120" placeholder="Optional">

                <label>Address</label>
                <input type="text" name="user_address" placeholder="Optional">

                <label>Role</label>
                <select name="role_id" id="roleSelect" required>
                    <option value="" disabled selected>Select Role</option>
                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                        <option value="<?= $role['role_id'] ?>"><?= ucfirst($role['role_name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <div id="leaderSelectContainer" style="display:none; margin-top:15px;">
                    <label>Select Leader (for Members only)</label>
                    <select name="leader_id">
                        <option value="" disabled selected>Select Leader</option>
                        <?php while ($leader = $leaders_result->fetch_assoc()): ?>
                            <option value="<?= $leader['leader_id'] ?>"><?= htmlspecialchars($leader['leader_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="primary-btn">💾 Save User</button>
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
        leaderSelectContainer.style.display = (this.value == '3') ? 'block' : 'none'; // Only show for Members
    });
});
</script>
</body>
</html>
