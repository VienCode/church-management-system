<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_PASTOR, ROLE_LEADER]);

$user_code = $_SESSION['user_code'] ?? 'Unknown';

// ✅ Handle Expense Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    $category = $_POST['category'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $receipt_image = null;

    // Upload receipt if available
    if (!empty($_FILES['receipt']['name'])) {
        $upload_dir = __DIR__ . "/uploads/receipts/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . "_" . basename($_FILES['receipt']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_path)) {
            $receipt_image = "uploads/receipts/" . $file_name;
        }
    }

    $stmt = $mysqli->prepare("
        INSERT INTO expenses (submitted_by, category, description, amount, receipt_image)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssds", $user_code, $category, $description, $amount, $receipt_image);
    $stmt->execute();

    $success = "✅ Expense submitted successfully and pending pastor approval.";
}

// ✅ Fetch Pending Submissions by Current User
$stmt = $mysqli->prepare("SELECT * FROM expenses WHERE submitted_by = ? AND status = 'Pending' ORDER BY submitted_at DESC");
$stmt->bind_param("s", $user_code);
$stmt->execute();
$pending_expenses = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Submit Expense | UCF</title>
<link rel="stylesheet" href="styles_system.css">
<style>
.expense-container {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 1000px;
    margin: 30px auto;
}
input, select, textarea {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-bottom: 12px;
}
.save-btn {
    background: #0271c0;
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}
.save-btn:hover { background: #02589b; }
.success {
    background: #e6ffed;
    color: #256029;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
}
.preview-img {
    max-width: 150px;
    border-radius: 8px;
    margin-top: 8px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #ddd;
}
th { background: #0271c0; color: white; }
</style>
</head>

<script src="scripts/sidebar_badges.js"></script>
<body>
<div class="main-layout">
   <?php include __DIR__ . '/includes/sidebar.php'; ?>
   <div class="content-area">
       <div class="expense-container">
           <h1>💼 Submit Expense</h1>
           <p>Record operational, ministry, or event expenses here for approval.</p>

           <?php if (isset($success)): ?>
               <div class="success"><?= $success ?></div>
           <?php endif; ?>

           <form method="POST" enctype="multipart/form-data">
               <label><strong>Category:</strong></label>
               <select name="category" required>
                   <option value="" disabled selected>Select Category</option>
                   <option value="Maintenance">Maintenance</option>
                   <option value="Ministry">Ministry</option>
                   <option value="Supplies">Supplies</option>
                   <option value="Event">Event</option>
                   <option value="Outreach">Outreach</option>
                   <option value="Other">Other</option>
               </select>

               <label><strong>Description:</strong></label>
               <textarea name="description" rows="3" required placeholder="Describe the expense..."></textarea>

               <label><strong>Amount (₱):</strong></label>
               <input type="number" name="amount" step="0.01" min="1" required>

               <label><strong>Attach Receipt (optional):</strong></label>
               <input type="file" name="receipt" accept="image/*" onchange="previewImage(event)">
               <img id="receiptPreview" class="preview-img" style="display:none;">

               <button type="submit" name="save_expense" class="save-btn">💾 Submit Expense</button>
           </form>

           <hr style="margin:25px 0;">

           <h2>🕒 Your Pending Submissions</h2>
           <?php if ($pending_expenses->num_rows === 0): ?>
               <p>No pending expenses.</p>
           <?php else: ?>
               <table>
                   <thead>
                       <tr>
                           <th>Date</th>
                           <th>Category</th>
                           <th>Description</th>
                           <th>Amount</th>
                           <th>Status</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php while ($row = $pending_expenses->fetch_assoc()): ?>
                           <tr>
                               <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                               <td><?= htmlspecialchars($row['category']) ?></td>
                               <td><?= htmlspecialchars($row['description']) ?></td>
                               <td>₱<?= number_format($row['amount'], 2) ?></td>
                               <td><span style="color:orange;">🕒 Pending</span></td>
                           </tr>
                       <?php endwhile; ?>
                   </tbody>
               </table>
           <?php endif; ?>
       </div>
   </div>
</div>

<script>
function previewImage(event) {
    const output = document.getElementById('receiptPreview');
    output.src = URL.createObjectURL(event.target.files[0]);
    output.style.display = 'block';
}
</script>
</body>
</html>
