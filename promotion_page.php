<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // Include centralized logging helper
restrict_to_roles([ROLE_ADMIN]); // Admin-only access

// Handle Promotion Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_ids'])) {
    $promote_ids = $_POST['promote_ids'];
    $leader_ids = $_POST['leader_id'];

    foreach ($promote_ids as $id) {
        $id = intval($id);
        $leader_id = intval($leader_ids[$id]);

        // Fetch non-member info
        $nm_stmt = $mysqli->prepare("SELECT * FROM non_members WHERE id = ?");
        $nm_stmt->bind_param("i", $id);
        $nm_stmt->execute();
        $non_member = $nm_stmt->get_result()->fetch_assoc();
        $nm_stmt->close();

        if ($non_member) {
            $fullname = $non_member['firstname'] . ' ' . $non_member['lastname'];

            // Move record to users table
            $insert = $mysqli->prepare("
                INSERT INTO users (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id, leader_id, is_cell_member)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3, ?, 1)
            ");
            $insert->bind_param(
                "sssissssi",
                $non_member['firstname'],
                $non_member['lastname'],
                $non_member['suffix'],
                $non_member['contact'],
                $non_member['age'],
                $non_member['user_address'],
                $non_member['email'],
                $non_member['pwd_hash'],
                $leader_id
            );
            $insert->execute();
            $insert->close();

            // Delete from non_members
            $del_stmt = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
            $del_stmt->bind_param("i", $id);
            $del_stmt->execute();
            $del_stmt->close();

            // ✅ Log the promotion in the centralized system log
            log_role_change(
                $mysqli,
                $_SESSION['user_id'],     // who performed the action
                $_SESSION['role'],        // role of the one promoting
                $fullname,                // name of promoted user
                'Member',                 // new role
                'PROMOTE'                 // action type
            );
        }
    }

    $_SESSION['promotion_result'] = "🎉 Selected non-members have been successfully promoted to Members!";
    header("Location: promotion_panel.php");
    exit();
}

// Fetch eligible for promotion (non-members only)
$eligible = $mysqli->query("
    SELECT id, user_code, firstname, lastname, email, contact, attendances_count
    FROM non_members
    WHERE attendances_count >= 10
    ORDER BY lastname ASC
");

// Fetch all leaders
$leaders = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promotion Panel | Unity Christian Fellowship</title>
    <link rel="stylesheet" href="styles_system.css">
    <style>
        .promotion-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: 40px auto;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: center; }
        th { background-color: #007bff; color: white; }

        .primary-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .primary-btn:hover { background-color: #0056b3; }

        .secondary-btn {
            background-color: #ccc;
            color: #333;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .secondary-btn:hover { background-color: #aaa; }

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        h1 { margin-bottom: 20px; color: #222; }
    </style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="promotion-container">
            <h1>🕊️ Endorsement Panel</h1>
            <p>These Non-Members have reached 10 or more attendances. Assign them a leader and promote them to Members.</p>

            <?php if (isset($_SESSION['promotion_result'])): ?>
                <div class="success-message" style="margin:15px 0;">
                    <?= $_SESSION['promotion_result'] ?>
                </div>
                <?php unset($_SESSION['promotion_result']); ?>
            <?php endif; ?>

            <form action="" method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>User Code</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Attendances</th>
                            <th>Assign Leader</th>
                            <th>Endorse</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($eligible->num_rows === 0): ?>
                            <tr><td colspan="7">No non-members eligible for promotion.</td></tr>
                        <?php else: ?>
                            <?php while ($nm = $eligible->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($nm['user_code']) ?></td>
                                    <td><?= htmlspecialchars($nm['firstname'] . ' ' . $nm['lastname']) ?></td>
                                    <td><?= htmlspecialchars($nm['email']) ?></td>
                                    <td><?= htmlspecialchars($nm['contact']) ?></td>
                                    <td><?= $nm['attendances_count'] ?></td>
                                    <td>
                                        <select name="leader_id[<?= $nm['id'] ?>]" required>
                                            <option value="" disabled selected>Select Leader</option>
                                            <?php
                                            $leaders->data_seek(0);
                                            while ($leader = $leaders->fetch_assoc()): ?>
                                                <option value="<?= $leader['leader_id'] ?>">
                                                    <?= htmlspecialchars($leader['leader_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td><input type="checkbox" name="promote_ids[]" value="<?= $nm['id'] ?>"></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px;">
                    <button type="submit" class="primary-btn">🚀 Promote Selected</button>
                    <a href="evangelism.php" class="secondary-btn">⬅ Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
