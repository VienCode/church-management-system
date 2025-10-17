<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php'; // Include centralized logging helper
restrict_to_roles([ROLE_PASTOR]);

$pastor_code = $_SESSION['user_code'] ?? 'Unknown';

// Handle approval or rejection (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $expense_id = intval($_POST['expense_id']);
    $action = $_POST['action'];
    $remarks = $_POST['remarks'] ?? '';

    if (in_array($action, ['Approved', 'Rejected'])) {
        $stmt = $mysqli->prepare("
            UPDATE expenses 
            SET status = ?, remarks = ?, approved_by = ?, approved_at = NOW()
            WHERE expense_id = ?
        ");
        $stmt->bind_param("sssi", $action, $remarks, $pastor_code, $expense_id);
        $stmt->execute();
        $stmt->close();

        // Log action to centralized log
        if ($action === 'Approved') {
            log_action(
                $mysqli,
                $_SESSION['user_id'],
                $_SESSION['role'],
                'APPROVE_EXPENSE',
                "Approved expense request #{$expense_id}",
                'High'
            );
        } elseif ($action === 'Rejected') {
            log_action(
                $mysqli,
                $_SESSION['user_id'],
                $_SESSION['role'],
                'REJECT_EXPENSE',
                "Rejected expense request #{$expense_id}",
                'High'
            );
        }

        echo json_encode(["success" => true, "message" => "Expense $action successfully."]);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Invalid action."]);
    exit;
}

// Fetch pending expenses
$result = $mysqli->query("
    SELECT * FROM expenses 
    WHERE status = 'Pending' 
    ORDER BY submitted_at DESC
");
$expenses = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Expense Approval | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.container {
    background:#fff; padding:25px; border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1); max-width:1000px;
    margin:30px auto;
}
.action-btn {
    border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600;
}
.approve-btn { background:#28a745; color:white; }
.reject-btn { background:#dc3545; color:white; }
textarea { width:100%; padding:6px; border-radius:6px; border:1px solid #ccc; resize:none; }
.receipt-thumb { max-width:90px; border-radius:6px; cursor:pointer; transition:0.2s; }
.receipt-thumb:hover { transform:scale(1.1); }
.status-pending { color:orange; font-weight:bold; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { padding:10px; text-align:center; border-bottom:1px solid #e6e6e6; }
th { background:#0271c0; color:white; }
.success-msg {
    background:#e6ffed; color:#256029; padding:10px; border-radius:8px;
    text-align:center; margin-bottom:15px; display:none;
}
</style>
</head>
<body>
<div class="main-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-area">
    <div class="container">
        <h1>✅ Expense Approval Panel</h1>
        <p>Approve or reject pending expense submissions below.</p>

        <div id="msgBox" class="success-msg"></div>

        <?php if (empty($expenses)): ?>
            <p>No pending expense submissions.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Submitted By</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr id="row_<?= $e['expense_id'] ?>">
                            <td><?= date('M d, Y', strtotime($e['submitted_at'])) ?></td>
                            <td><?= htmlspecialchars($e['submitted_by']) ?></td>
                            <td><?= htmlspecialchars($e['category']) ?></td>
                            <td><?= htmlspecialchars($e['description']) ?></td>
                            <td>₱<?= number_format($e['amount'], 2) ?></td>
                            <td>
                                <?php if ($e['receipt_image']): ?>
                                    <img src="<?= $e['receipt_image'] ?>" class="receipt-thumb" onclick="window.open(this.src)">
                                <?php else: ?>
                                    <i>No Receipt</i>
                                <?php endif; ?>
                            </td>
                            <td><textarea id="remarks_<?= $e['expense_id'] ?>" rows="2" placeholder="Optional remarks..."></textarea></td>
                            <td>
                                <button class="action-btn approve-btn" onclick="processAction(<?= $e['expense_id'] ?>, 'Approved')">Approve</button>
                                <button class="action-btn reject-btn" onclick="processAction(<?= $e['expense_id'] ?>, 'Rejected')">Reject</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
async function processAction(id, action) {
    const remarks = document.getElementById('remarks_' + id).value;
    const msgBox = document.getElementById('msgBox');

    const formData = new FormData();
    formData.append('action', action);
    formData.append('expense_id', id);
    formData.append('remarks', remarks);

    const res = await fetch('expenses_approval.php', { method:'POST', body:formData });
    const result = await res.json();

    msgBox.textContent = result.message;
    msgBox.style.display = 'block';

    if (result.success) {
        const row = document.getElementById('row_' + id);
        row.style.opacity = '0.5';
        setTimeout(() => row.remove(), 700);
    }
}
</script>
</body>
</html>
