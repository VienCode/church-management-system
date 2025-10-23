<?php
include 'database.php';
include 'auth_check.php';
include 'includes/log_helper.php';
global $mysqli;

restrict_to_roles([ROLE_ADMIN]);

$user_id = intval($_GET['id'] ?? 0);

// Fetch user info
$stmt = $mysqli->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: admin_dashboard.php?msg=❌ User not found.");
    exit;
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password']);
    if (strlen($new_password) < 8) {
        $msg = "⚠️ Password must be at least 8 characters long.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $mysqli->prepare("UPDATE users SET pwd_hash=? WHERE id=?");
        $update->bind_param("si", $hashed, $user_id);
        $update->execute();
        $update->close();

        log_action(
            $mysqli,
            $_SESSION['user_id'],
            'Admin',
            'UPDATE_PASSWORD',
            "Manually updated password for {$user['firstname']} {$user['lastname']} ({$user['email']})",
            'High'
        );

        header("Location: admin_dashboard.php?msg=✅ Password updated successfully for {$user['firstname']} {$user['lastname']}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Password - <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></title>
<link rel="stylesheet" href="styles_system.css">
<style>
.form-container {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 500px;
    margin-top: 30px;
}
label {
    font-weight: 600;
    color: #333;
}
.password-wrapper {
    position: relative;
    width: 100%;
}
.password-wrapper input[type="password"],
.password-wrapper input[type="text"] {
    width: 100%;
    padding: 10px 40px 10px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 15px;
    margin-bottom: 15px;
}
.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: #555;
}
.toggle-password:hover { color: #222; }

button {
    background: linear-gradient(135deg, #4a90e2, #357abd);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
button:hover { background: linear-gradient(135deg, #1e3a8a, #2c5aa0); }
.msg { margin-bottom: 15px; padding: 10px; border-radius: 8px; font-weight: 600; }
.msg.error { background: #ffeaea; color: #b3261e; }

.strength-meter {
    height: 8px;
    width: 100%;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}
.strength-meter-fill {
    height: 100%;
    width: 0%;
    background: red;
    border-radius: 4px;
    transition: width 0.3s, background 0.3s;
}
.strength-text {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
}
</style>
</head>
<body>
<div class="main-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="content-area">
    <h1>🔑 Edit Password for <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></h1>

    <?php if (!empty($msg)): ?>
        <div class="msg error"><?= $msg ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" id="passwordForm">
            <label>New Password:</label>
            <div class="password-wrapper">
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                <button type="button" class="toggle-password" id="togglePassword" title="Show/Hide Password">👁️</button>
            </div>

            <div class="strength-meter"><div id="strength-fill" class="strength-meter-fill"></div></div>
            <div id="strength-text" class="strength-text">Strength: Weak</div>

            <button type="submit">💾 Update Password</button>
            <a href="admin_dashboard.php" class="secondary-btn" style="margin-left:10px;">⬅ Back</a>
        </form>
    </div>
</div>
</div>

<script>
const passwordInput = document.getElementById('new_password');
const toggleButton = document.getElementById('togglePassword');
const fill = document.getElementById('strength-fill');
const text = document.getElementById('strength-text');

// 👁️ Toggle visibility
toggleButton.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    toggleButton.textContent = isPassword ? '🙈' : '👁️';
});

// 💪 Strength meter logic
passwordInput.addEventListener('input', function() {
    const val = passwordInput.value;
    let score = 0;

    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/\d/.test(val)) score++;
    if (/[@$!%*?&#^()_\-+=]/.test(val)) score++;

    const percent = (score / 5) * 100;
    fill.style.width = percent + '%';

    if (score <= 2) {
        fill.style.background = 'red';
        text.textContent = 'Strength: Weak';
    } else if (score === 3) {
        fill.style.background = 'orange';
        text.textContent = 'Strength: Moderate';
    } else if (score === 4) {
        fill.style.background = '#fbc02d';
        text.textContent = 'Strength: Strong';
    } else {
        fill.style.background = 'green';
        text.textContent = 'Strength: Very Strong';
    }
});
</script>
</body>
</html>
<!DOCTYPE html>