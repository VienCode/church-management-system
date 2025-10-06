<?php
session_start();
$mysqli = include 'database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch non-member info
$stmt = $mysqli->prepare("SELECT firstname, lastname, email FROM non_members WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch attendance list (correct columns)
$attendance = $mysqli->prepare("
    SELECT attendance_date, status, time_in 
    FROM attendance 
    WHERE user_id = ? 
    ORDER BY attendance_date DESC
");
$attendance->bind_param("i", $user_id);
$attendance->execute();
$attendance_result = $attendance->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Non-Member Profile | Unity Christian Fellowship</title>
    <link rel="stylesheet" href="style_system.css">
</head>
<body>
    <div class="main-layout">
        <nav class="sidebar">
            <div class="logo-section">
                <div class="logo-placeholder"><span>⛪</span></div>
                <div class="logo">Unity Christian Fellowship</div>
            </div>
            <ul class="nav-menu">
                <li><a href="nonmember_profile.php" class="active"><span>👤</span> My Account</a></li>
                <li><a href="logout.php"><span>🚪</span> Logout</a></li>
            </ul>
        </nav>

        <div class="content-area">
            <div class="content-header">
                <div class="header-left">
                    <h1 class="page-title">
                        👋 Welcome, <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>!
                        <span class="title-subtitle">Non-Member Profile Page</span>
                    </h1>
                </div>
            </div>

            <div class="profile-section">
                <div class="profile-card">
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h2>
                        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>

                <div class="attendance-section">
                    <h2>📅 Attendance History</h2>
                    <div class="attendance-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Time In</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($attendance_result->num_rows > 0): ?>
                                    <?php while ($row = $attendance_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['time_in']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center;">No attendance records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
