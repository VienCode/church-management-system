<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can access

// ✅ Fetch all cell groups with leader + latest meeting
$sql = "
    SELECT 
        cg.id AS group_id,
        cg.group_name,
        l.leader_name,
        l.email AS leader_email,
        l.contact AS leader_contact,
        COUNT(cgm.member_id) AS member_count,
        MAX(m.meeting_date) AS last_meeting_date,
        (
            SELECT m2.title
            FROM cell_group_meetings m2
            WHERE m2.cell_group_id = cg.id
            ORDER BY m2.meeting_date DESC
            LIMIT 1
        ) AS last_meeting_title
    FROM cell_groups cg
    JOIN leaders l ON cg.leader_id = l.leader_id
    LEFT JOIN cell_group_members cgm ON cg.id = cgm.cell_group_id
    LEFT JOIN cell_group_meetings m ON cg.id = m.cell_group_id
    GROUP BY cg.id
    ORDER BY cg.group_name ASC
";

$result = $mysqli->query($sql);
$groups = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📊 Cell Groups Overview | UCF Admin</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1200px;
    margin: 30px auto;
}
h1 { color: #0271c0; margin-bottom: 15px; }
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}
th {
    background: #0271c0;
    color: white;
}
tr:hover {
    background: #f4f9ff;
}
a.view-link {
    color: #0271c0;
    text-decoration: none;
    font-weight: bold;
}
a.view-link:hover {
    text-decoration: underline;
}
.summary {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
}
.summary div {
    background: #f6f8fb;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
}
</style>
</head>

<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>

   <div class="content-area">
      <div class="container">
         <h1>📊 Cell Group Overview</h1>
         <p>Monitor all active cell groups, their leaders, members, and meeting activity.</p>

         <table>
            <thead>
               <tr>
                  <th>#</th>
                  <th>Group Name</th>
                  <th>Leader</th>
                  <th>Contact</th>
                  <th>Email</th>
                  <th>Members</th>
                  <th>Last Meeting</th>
                  <th>Meeting Title</th>
                  <th>Actions</th>
               </tr>
            </thead>
            <tbody>
               <?php if (empty($groups)): ?>
                  <tr><td colspan="9" style="color:#666;">No cell groups found.</td></tr>
               <?php else: $i = 1; foreach ($groups as $g): ?>
                  <tr>
                     <td><?= $i++ ?></td>
                     <td><strong><?= htmlspecialchars($g['group_name']) ?></strong></td>
                     <td><?= htmlspecialchars($g['leader_name']) ?></td>
                     <td><?= htmlspecialchars($g['leader_contact']) ?></td>
                     <td><?= htmlspecialchars($g['leader_email']) ?></td>
                     <td><?= $g['member_count'] ?></td>
                     <td>
                        <?= $g['last_meeting_date']
                            ? date('F j, Y', strtotime($g['last_meeting_date']))
                            : '<em>No meetings yet</em>' ?>
                     </td>
                     <td><?= htmlspecialchars($g['last_meeting_title'] ?? '-') ?></td>
                     <td><a href="cell_groups_admin.php?leader_id=<?= urlencode($g['group_id']) ?>" class="view-link">View Details</a></td>
                  </tr>
               <?php endforeach; endif; ?>
            </tbody>
         </table>

         <div class="summary">
            <div>📊 Total Groups: <?= count($groups) ?></div>
            <div>👥 Total Members: 
                <?= array_sum(array_column($groups, 'member_count')) ?>
            </div>
         </div>
      </div>
   </div>
</div>
</body>
</html>
