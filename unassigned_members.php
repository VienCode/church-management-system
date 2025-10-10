<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

// Fetch all unassigned members (role_id = 3, leader_id IS NULL, but have attendance records)
$sql = "
    SELECT id, user_code, firstname, lastname, email, contact 
    FROM users
    WHERE role_id = 3 
    AND leader_id IS NULL
    AND user_code IN (SELECT DISTINCT user_code FROM attendance)
    ORDER BY lastname ASC
";
$result = $mysqli->query($sql);
$members = $result->fetch_all(MYSQLI_ASSOC);

// Fetch available leaders
$leaders = $mysqli->query("
    SELECT leader_id, leader_name 
    FROM leaders 
    ORDER BY leader_name ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Unassigned Members | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
.success {
    background: #e6ffed;
    color: #256029;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
    display: none;
}
select, button {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.assign-btn {
    background: #0271c0;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 16px;
    cursor: pointer;
    font-weight: 600;
}
.assign-btn:hover { background: #02589b; }
.bulk-assign-container {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 10px;
    gap: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #e6e6e6;
    text-align: center;
}
th {
    background: #0271c0;
    color: white;
}
.checkbox {
    transform: scale(1.3);
    cursor: pointer;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-area">
        <div class="container">
            <h1>🔄 Reassign Unassigned Members</h1>
            <p>These are members who lost their leaders and need reassignment.</p>

            <div class="success" id="successMessage">✅ Members reassigned successfully!</div>

            <!-- Bulk Assignment Section -->
            <div class="bulk-assign-container">
                <label><strong>Select Leader:</strong></label>
                <select id="bulkLeaderSelect">
                    <option value="" disabled selected>Select Leader</option>
                    <?php foreach ($leaders as $l): ?>
                        <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="assign-btn" id="bulkAssignBtn">Assign Selected Members</button>
            </div>

            <!-- Table -->
            <?php if (empty($members)): ?>
                <p>No unassigned members found.</p>
            <?php else: ?>
                <form id="bulkAssignForm">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" class="checkbox"></th>
                                <th>#</th>
                                <th>User Code</th>
                                <th>Full Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($members as $m): ?>
                                <tr>
                                    <td><input type="checkbox" name="member_ids[]" value="<?= $m['id'] ?>" class="member-checkbox checkbox"></td>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($m['user_code']) ?></td>
                                    <td><?= htmlspecialchars($m['firstname'].' '.$m['lastname']) ?></td>
                                    <td><?= htmlspecialchars($m['contact']) ?></td>
                                    <td><?= htmlspecialchars($m['email']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Select all checkboxes
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = checked);
});

// Handle bulk assign
document.getElementById('bulkAssignBtn')?.addEventListener('click', async () => {
    const selectedMembers = [...document.querySelectorAll('.member-checkbox:checked')].map(cb => cb.value);
    const leaderId = document.getElementById('bulkLeaderSelect').value;
    const msgBox = document.getElementById('successMessage');

    if (!leaderId) {
        alert('⚠️ Please select a leader first.');
        return;
    }
    if (selectedMembers.length === 0) {
        alert('⚠️ Please select at least one member to assign.');
        return;
    }

    const formData = new FormData();
    formData.append('leader_id', leaderId);
    formData.append('member_ids', JSON.stringify(selectedMembers));

    const response = await fetch('unassigned_members_assign.php', {
        method: 'POST',
        body: formData
    });

    const result = await response.json();

    if (result.success) {
        msgBox.textContent = `✅ ${result.message}`;
        msgBox.style.display = 'block';
        setTimeout(() => location.reload(), 1500);
    } else {
        alert('❌ ' + result.message);
    }
});
</script>
</body>
</html>
