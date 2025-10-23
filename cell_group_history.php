<?php
include_once 'database.php';
include_once 'auth_check.php';
include_once 'includes/log_helper.php';
global $mysqli;

restrict_to_roles([ROLE_LEADER]); // Leaders only

$user_id = $_SESSION['user_id'];
$message = '';

// ----------------------------------------------------
// GET LEADER'S GROUP
// ----------------------------------------------------
$stmt = $mysqli->prepare("
    SELECT cg.id AS group_id, cg.group_name, l.leader_name
    FROM users u
    JOIN leaders l ON l.email = u.email
    JOIN cell_groups cg ON cg.leader_id = l.leader_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo "<h2>⚠️ You are not assigned to any cell group.</h2>";
    exit;
}

$group_id = $group['group_id'];

// ----------------------------------------------------
// FETCH ARCHIVED / PAST MEETINGS
// ----------------------------------------------------
$meetings_stmt = $mysqli->prepare("
    SELECT m.id, m.title, m.description, m.meeting_date,
           SUM(a.status = 'Present') AS present_count,
           SUM(a.status = 'Absent') AS absent_count,
           SUM(a.status = 'Late') AS late_count,
           COUNT(a.id) AS total_records
    FROM cell_group_meetings m
    LEFT JOIN cell_group_attendance a ON a.meeting_id = m.id
    WHERE m.cell_group_id = ? AND m.meeting_date < CURDATE()
    GROUP BY m.id
    ORDER BY m.meeting_date DESC
");
$meetings_stmt->bind_param("i", $group_id);
$meetings_stmt->execute();
$meetings = $meetings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$meetings_stmt->close();

// ----------------------------------------------------
// FETCH ACTIVE MEMBERS
// ----------------------------------------------------
$members_stmt = $mysqli->prepare("
    SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name, u.email, u.contact
    FROM cell_group_members cgm
    JOIN users u ON u.id = cgm.member_id
    WHERE cgm.cell_group_id = ?
    ORDER BY u.firstname
");
$members_stmt->bind_param("i", $group_id);
$members_stmt->execute();
$members = $members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$members_stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>📅 Cell Group History</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.details-row {
    background-color: #f9fafb;
    display: none;
}
.details-row td {
    padding: 15px;
    border-top: none;
}
.details-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}
.details-table th, .details-table td {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}
.details-table th {
    background: #e0e7ff;
    font-weight: 600;
}
.toggle-btn {
    background: #3b82f6;
    color: white;
    padding: 6px 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.2s;
}
.toggle-btn:hover { background: #2563eb; }
</style>
</head>
<body>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="content-area">
        <h1>📋 <?= htmlspecialchars($group['group_name']) ?> — History & Members</h1>

        <!-- 📜 Past Meetings Section -->
        <div class="cellgroup-section">
            <h2>🕓 Past Meetings / Archived Attendance</h2>
            <?php if (empty($meetings)): ?>
                <p>No past meetings found.</p>
            <?php else: ?>
                <table class="cellgroup-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Total</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meetings as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['meeting_date']) ?></td>
                            <td><?= htmlspecialchars($m['title']) ?></td>
                            <td><?= htmlspecialchars($m['description']) ?></td>
                            <td style="color:#22c55e;"><?= $m['present_count'] ?? 0 ?></td>
                            <td style="color:#dc2626;"><?= $m['absent_count'] ?? 0 ?></td>
                            <td style="color:#facc15;"><?= $m['late_count'] ?? 0 ?></td>
                            <td><?= $m['total_records'] ?? 0 ?></td>
                            <td>
                                <button class="toggle-btn" data-meeting-id="<?= $m['id'] ?>">View Details</button>
                            </td>
                        </tr>
                        <tr class="details-row" id="details-<?= $m['id'] ?>">
                            <td colspan="8">
                                <div id="content-<?= $m['id'] ?>" class="details-content">
                                    <p>Loading attendance details...</p>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 👥 Active Members Section -->
        <div class="cellgroup-section" style="margin-top:40px;">
            <h2>👥 Current Members</h2>
            <?php if (empty($members)): ?>
                <p>No members currently assigned.</p>
            <?php else: ?>
                <table class="cellgroup-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars($m['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($m['contact'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toggle view for attendance details
document.querySelectorAll('.toggle-btn').forEach(button => {
    button.addEventListener('click', async function () {
        const meetingId = this.dataset.meetingId;
        const detailsRow = document.getElementById('details-' + meetingId);
        const contentDiv = document.getElementById('content-' + meetingId);

        if (detailsRow.style.display === 'table-row') {
            detailsRow.style.display = 'none';
            this.textContent = 'View Details';
        } else {
            detailsRow.style.display = 'table-row';
            this.textContent = 'Hide Details';

            // Fetch attendance details via AJAX
            contentDiv.innerHTML = "<p>Loading attendance...</p>";

            const response = await fetch(`fetch_meeting_attendance.php?meeting_id=${meetingId}`);
            const data = await response.text();
            contentDiv.innerHTML = data;
        }
    });
});
</script>
</body>
</html>
