<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Admin-only access

// Fetch eligible non-members (10+ attendances)
$eligible = $mysqli->query("
    SELECT id, user_code, firstname, lastname, email, contact, attendances_count
    FROM non_members
    WHERE attendances_count >= 10
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        .primary-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .primary-btn:hover {
            background-color: #0056b3;
        }

        .secondary-btn {
            background-color: #ccc;
            color: #333;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .secondary-btn:hover {
            background-color: #aaa;
        }

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        h1 {
            margin-bottom: 20px;
            color: #222;
        }
    </style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Content Area -->
    <div class="content-area">
        <div class="promotion-container">
            <h1>🕊️ Promotion Panel</h1>
            
            <form method="POST" action="promotion_auto.php" style="margin-top: 20px;">
                <button type="submit" class="primary-btn">🚀 Promote All Eligible Members</button>
            </form>

            <?php if (isset($_SESSION['promotion_result'])): ?>
                <div class="success-message" style="margin:15px 0;">
                    <?= $_SESSION['promotion_result'] ?>
                </div>
                <?php unset($_SESSION['promotion_result']); ?>
            <?php endif; ?>

            <form action="promote_nonmembers.php" method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>User Code</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Attendances</th>
                            <th>Assign Leader</th>
                            <th>Promote</th>
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
                                    <td>
                                        <input type="checkbox" name="promote_ids[]" value="<?= $nm['id'] ?>">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px;">
                    <button type="submit" class="primary-btn">🚀 Promote Selected</button>
                    <a href="admin_dashboard.php" class="secondary-btn">⬅ Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
