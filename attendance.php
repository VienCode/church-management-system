<?php
$mysqli = include 'database.php';
session_start();

// Ensure attendance_date is always defined
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_date'])) {
    if (!empty($_POST['attendance_date'])) {
        // Prevent selecting future dates
        $selected_date = $_POST['attendance_date'];
        if ($selected_date <= date('Y-m-d')) {
            $_SESSION['attendance_date'] = $selected_date;
        }
    }
    header("Location: attendance.php");
    exit();
}

$attendance_date = isset($_SESSION['attendance_date']) ? $_SESSION['attendance_date'] : date('Y-m-d');

// Safe defaults for search form
$search_name = isset($_GET['search_name']) ? htmlspecialchars($_GET['search_name']) : '';
$search_date = isset($_GET['search_date']) ? htmlspecialchars($_GET['search_date']) : '';

// Attendance submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
        foreach ($_POST['attendance'] as $member_id => $data) {
            $status = $data['status'] ?? null;
            $arrival_time = $data['arrival_time'] ?? null;

            if ($status) {
                $stmt = $mysqli->prepare("
                    INSERT INTO attendance (member_id, attendance_date, status, arrival_time)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status), arrival_time=VALUES(arrival_time)
                ");
                $stmt->bind_param("isss", $member_id, $attendance_date, $status, $arrival_time);
                $stmt->execute();
            }
        }
        $success = "✅ Attendance successfully recorded!";
    } else {
        $success = "⚠️ No attendance data submitted.";
    }
}

// Fetch members with their attendance for the selected date
$sql = "
    SELECT 
        m.id AS id, 
        CONCAT(m.firstname, ' ', m.lastname, ' ', COALESCE(m.suffix, '')) AS name,
        a.status, 
        a.arrival_time
    FROM members m
    LEFT JOIN attendance a 
      ON m.id = a.member_id AND a.attendance_date = ?
    ORDER BY m.firstname, m.lastname
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$members_result = $stmt->get_result();

// Stats
$presentCount = 0;
$absentCount = 0;
$totalMembers = $members_result->num_rows;

$members_data = [];
while ($row = $members_result->fetch_assoc()) {
    $members_data[] = $row;
    if ($row['status'] === "Present") $presentCount++;
    if ($row['status'] === "Absent") $absentCount++;
}
$notMarked = $totalMembers - ($presentCount + $absentCount);

$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Management PAGE</title>
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
            <!-- General Pages -->
            <li><a href="dashboard.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="attendance.php" class="active"><span>👥</span> Attendance</a></li>
            <li><a href="members.php"><span>👤</span> Members</a></li>
            <li><a href="upload.php"><span>📢</span> Church Updates</a></li>
            <li><a href="donations.php"><span>💰</span> Donations</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- Expenses Section -->
            <li class="nav-section">💼 Expenses</li>
            <li><a href="expenses_submit.php"><span>🧾</span> Submit Expense</a></li>
            <li><a href="expenses_approval.php"><span>✅</span> Approvals</a></li>
            <li><a href="expenses_history.php"><span>📊</span> History</a></li>

            <!-- Divider -->
            <li class="nav-divider"></li>

            <!-- System Section -->
            <li class="nav-section">🧩 System</li>
            <li><a href="logs.php"><span>🗂️</span> Activity Logs</a></li>
        </ul>
    </nav>
        <div class="content-area">
            <h1>Attendance Management Module</h1>

            <!-- Auto-submit Date Selector -->
            <div class="date-selector">
                <form method="POST" id="dateForm" style="margin: 0;">
                    <label style="margin: 0; font-weight: 600;">Attendance Date:</label>
                    <input type="date" 
                           name="attendance_date" 
                           value="<?php echo $attendance_date; ?>" 
                           max="<?php echo date('Y-m-d'); ?>" 
                           required>
                </form>
                <p style="margin: 10px 0 0 0; color: #666;">
                    Currently viewing: <strong><?php echo date("l, F j, Y", strtotime($attendance_date)); ?></strong>
                </p>
            </div>

            <!-- Stats -->
            <div class="stats-container">
                <div class="stat-card present"><h3>Present</h3><div class="number"><?php echo $presentCount; ?></div></div>
                <div class="stat-card absent"><h3>Absent</h3><div class="number"><?php echo $absentCount; ?></div></div>
                <div class="stat-card total"><h3>Total Members</h3><div class="number"><?php echo $totalMembers; ?></div></div>
                <div class="stat-card"><h3>Not Marked</h3><div class="number"><?php echo $notMarked; ?></div></div>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <button onclick="openModal('searchRecordsModal')" class="secondary-btn">Search & Records</button>
                <button onclick="window.location.href='export_attendance.php'" class="export-btn">Export to Excel</button>
            </div>

            <!-- Attendance Table -->
            <div class="attendance-table">
                <form method="POST" id="attendanceForm">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Attendance Status</th>
                                <th>Arrival Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($members_data) > 0): ?>
                                <?php foreach ($members_data as $index => $m): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                                        <td>
                                            <div class="radio-group">
                                                <label class="present-label">
                                                    <input type="radio" name="attendance[<?php echo $m['id']; ?>][status]" 
                                                           value="Present" 
                                                           <?php if ($m['status'] === "Present") echo "checked"; ?>
                                                           onchange="toggleTimeInput(<?php echo $m['id']; ?>, 'Present'); updateButtonStyle(this)">
                                                    Present
                                                </label>
                                                <label class="absent-label">
                                                    <input type="radio" name="attendance[<?php echo $m['id']; ?>][status]" 
                                                           value="Absent" 
                                                           <?php if ($m['status'] === "Absent") echo "checked"; ?>
                                                           onchange="toggleTimeInput(<?php echo $m['id']; ?>, 'Absent'); updateButtonStyle(this)">
                                                    Absent
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="time" class="time-input" 
                                                   id="time_<?php echo $m['id']; ?>" 
                                                   name="attendance[<?php echo $m['id']; ?>][arrival_time]" 
                                                   value="<?php echo $m['arrival_time'] ?? ''; ?>" 
                                                   <?php echo ($m['status'] === "Present") ? "" : "disabled"; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <center><button type="submit" name="save_attendance" class="save-btn">Save Attendance</button></center>
                </form>
            </div>
        </div>
    </div>

    <!-- ✅ Search & Records Modal -->
    <div id="searchRecordsModal" class="modal">
        <div class="modal-content large">
            <span class="close" onclick="closeModal('searchRecordsModal')">&times;</span>
            <h2>Search Attendance & Records</h2>
            
            <form method="GET" id="searchForm">
                <label for="name">Member Name:</label>
                <input type="text" name="search_name" value="<?php echo $search_name; ?>">

                <label for="date">Date:</label>
                <input type="date" 
                       name="search_date" 
                       value="<?php echo $search_date; ?>" 
                       max="<?php echo date('Y-m-d'); ?>">

                <input type="hidden" name="search_submitted" value="1">
                <button type="submit">Search</button>
            </form>

            <table>
                <tr>
                    <th>Member Name</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Arrival Time</th>
                </tr>
                <?php
                if (isset($_GET['search_submitted'])) {
                    // Build query with optional filters
                    $query = "
                        SELECT m.full_name, a.attendance_date, a.status, a.arrival_time
                        FROM members m
                        LEFT JOIN attendance a ON m.member_id = a.member_id
                        WHERE 1=1
                    ";
                    $params = [];
                    $types = "";

                    if (!empty($search_name)) {
                        $query .= " AND m.full_name LIKE ?";
                        $params[] = "%" . $search_name . "%";
                        $types .= "s";
                    }

                    if (!empty($search_date)) {
                        $query .= " AND a.attendance_date = ?";
                        $params[] = $search_date;
                        $types .= "s";
                    }

                    $query .= " ORDER BY a.attendance_date DESC, m.full_name ASC";

                    $stmt = $mysqli->prepare($query);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $search_result = $stmt->get_result();

                    if ($search_result->num_rows > 0) {
                        while ($row = $search_result->fetch_assoc()) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($row['full_name']) . "</td>
                                    <td>" . ($row['attendance_date'] ? htmlspecialchars($row['attendance_date']) : "Not Recorded") . "</td>
                                    <td>" . ($row['status'] ?? "Not Marked") . "</td>
                                    <td>" . ($row['arrival_time'] ?? "N/A") . "</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No records found</td></tr>";
                    }

                    $stmt->close();
                }
                ?>
            </table>
        </div>
    </div>

    <script src="script.js?v=3"></script>
    <script>
    // Auto-submit forms when date changes
    document.addEventListener("DOMContentLoaded", function() {
        // Main date selector
        const mainDateInput = document.querySelector('#dateForm input[type="date"]');
        if (mainDateInput) {
            mainDateInput.addEventListener("change", function() {
                document.getElementById("dateForm").submit();
            });
        }

        // Modal search date selector
        const searchDateInput = document.querySelector('#searchForm input[type="date"]');
        if (searchDateInput) {
            searchDateInput.addEventListener("change", function() {
                document.getElementById("searchForm").submit();
            });
        }

        // Keep modal open after search
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search_submitted')) {
            openModal('searchRecordsModal');
        }
    });
    </script>
</body>
</html>
