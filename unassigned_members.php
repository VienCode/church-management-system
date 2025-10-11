<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

// Fetch unassigned members (members without a leader)
$sql = "
    SELECT u.id, u.user_code, u.firstname, u.lastname, u.email, u.contact
    FROM users u
    LEFT JOIN users l ON u.leader_id = l.id
    WHERE (u.role_id = 3 OR u.is_cell_member = 1)
      AND (u.leader_id IS NULL OR l.role_id != 2 OR l.id IS NULL)
    ORDER BY u.lastname ASC
";


$result = $mysqli->query($sql);
$members = $result->fetch_all(MYSQLI_ASSOC);

// Fetch leaders for assignment
$leaders = $mysqli->query("SELECT leader_id, leader_name FROM leaders ORDER BY leader_name ASC")->fetch_all(MYSQLI_ASSOC);
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
    transition: all 0.5s ease;
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
.fade-out {
    opacity: 0;
    transition: opacity 0.8s ease;
}
</style>
</head>

<body>
<div class="main-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="content-area">
        <div class="container">
            <h1>🔄 Reassign Unassigned Members</h1>
            <p>These members currently have no assigned leader. Select one or more and assign them below.</p>

            <div class="success" id="successMessage">✅ Members reassigned successfully!</div>

            <!-- Bulk Assignment Section -->
            <div style="margin-bottom:10px; display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <label><strong>Select Leader:</strong></label>
                <select id="bulkLeaderSelect">
                    <option value="" disabled selected>Select Leader</option>
                    <?php foreach ($leaders as $l): ?>
                        <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="assign-btn" id="bulkAssignBtn">Assign Selected Members</button>
            </div>

            <!-- Members Table -->
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
                                <th>Unassigned Since</th>
                                <th>Assign</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($members as $m): ?>
                                <tr id="row_<?= $m['id'] ?>">
                                    <td><input type="checkbox" class="member-checkbox checkbox" value="<?= $m['id'] ?>"></td>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($m['user_code']) ?></td>
                                    <td><?= htmlspecialchars($m['firstname'].' '.$m['lastname']) ?></td>
                                    <td><?= htmlspecialchars($m['last_unassigned_at'] ?? '-') ?></td>
                                    <td>
                                        <select class="singleLeaderSelect">
                                            <option value="" disabled selected>Select Leader</option>
                                            <?php foreach ($leaders as $l): ?>
                                                <option value="<?= $l['leader_id'] ?>"><?= htmlspecialchars($l['leader_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="assign-btn singleAssignBtn" data-member="<?= $m['id'] ?>">Assign</button>
                                    </td>
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
// ✅ Select all checkboxes
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = checked);
});

// ✅ AJAX Assign function
async function assignMembers(leaderId, memberIds) {
    const msgBox = document.getElementById('successMessage');
    const formData = new FormData();
    formData.append('leader_id', leaderId);
    formData.append('member_ids', JSON.stringify(memberIds));

    const response = await fetch('unassigned_members_assign.php', { method: 'POST', body: formData });
    const result = await response.json();

    if (result.success) {
        msgBox.textContent = '✅ ' + result.message;
        msgBox.style.display = 'block';
        memberIds.forEach(id => {
            const row = document.getElementById('row_' + id);
            if (row) {
                row.classList.add('fade-out');
                setTimeout(() => row.remove(), 800);
            }
        });
    } else alert('❌ ' + result.message);
}

// ✅ Bulk assignment
document.getElementById('bulkAssignBtn')?.addEventListener('click', () => {
    const leaderId = document.getElementById('bulkLeaderSelect').value;
    const selected = [...document.querySelectorAll('.member-checkbox:checked')].map(cb => cb.value);
    if (!leaderId) return alert('⚠️ Select a leader first.');
    if (selected.length === 0) return alert('⚠️ Select at least one member.');
    assignMembers(leaderId, selected);
});

// ✅ Single row assignment
document.querySelectorAll('.singleAssignBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const leaderId = btn.previousElementSibling.value;
        const memberId = btn.dataset.member;
        if (!leaderId) return alert('⚠️ Select a leader.');
        assignMembers(leaderId, [memberId]);
    });
});
</script>
</body>
</html>
