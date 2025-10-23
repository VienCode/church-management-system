<?php
include_once 'database.php';
include_once 'auth_check.php';
global $mysqli;

restrict_to_roles([ROLE_LEADER]); // Only leaders can view

if (!isset($_GET['meeting_id']) || !is_numeric($_GET['meeting_id'])) {
    echo "<p style='color:#dc2626;'>❌ Invalid meeting ID.</p>";
    exit;
}

$meeting_id = intval($_GET['meeting_id']);

// Fetch meeting attendance
$stmt = $mysqli->prepare("
    SELECT u.firstname, u.lastname, a.status
    FROM cell_group_attendance a
    JOIN users u ON u.id = a.member_id
    WHERE a.meeting_id = ?
    ORDER BY FIELD(a.status, 'Present', 'Late', 'Absent'), u.firstname
");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($records)) {
    echo "<p style='color:#666;'>No attendance records found for this meeting.</p>";
    exit;
}

// Group records by status
$present = array_filter($records, fn($r) => $r['status'] === 'Present');
$absent  = array_filter($records, fn($r) => $r['status'] === 'Absent');
$late    = array_filter($records, fn($r) => $r['status'] === 'Late');
?>

<style>
.attendance-section {
    margin-top: 10px;
    padding: 10px;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}
.status-header {
    font-weight: 600;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: white;
}
.badge.present { background: #16a34a; }
.badge.absent { background: #dc2626; }
.badge.late { background: #facc15; color: #333; }

.member-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 6px;
    margin-top: 6px;
}
.member-item {
    background: white;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    font-size: 14px;
}
</style>

<div class="attendance-section">
    <div class="status-header">
        <span class="badge present">Present</span>
        <span>(<?= count($present) ?>)</span>
    </div>
    <div class="member-grid">
        <?php if (empty($present)): ?>
            <p style="color:#777;">No members marked present.</p>
        <?php else: ?>
            <?php foreach ($present as $p): ?>
                <div class="member-item"><?= htmlspecialchars($p['firstname'] . ' ' . $p['lastname']) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="status-header">
        <span class="badge late">Late</span>
        <span>(<?= count($late) ?>)</span>
    </div>
    <div class="member-grid">
        <?php if (empty($late)): ?>
            <p style="color:#777;">No late members recorded.</p>
        <?php else: ?>
            <?php foreach ($late as $l): ?>
                <div class="member-item"><?= htmlspecialchars($l['firstname'] . ' ' . $l['lastname']) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="status-header">
        <span class="badge absent">Absent</span>
        <span>(<?= count($absent) ?>)</span>
    </div>
    <div class="member-grid">
        <?php if (empty($absent)): ?>
            <p style="color:#777;">No absentees recorded.</p>
        <?php else: ?>
            <?php foreach ($absent as $a): ?>
                <div class="member-item"><?= htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
