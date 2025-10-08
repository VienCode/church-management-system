<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admin only

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize and validate
    $firstname = trim($_POST["firstname"]);
    $lastname  = trim($_POST["lastname"]);
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $role_id   = intval($_POST["role_id"]);

    if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        header("Location: admin_dashboard.php?msg=❌ Please fill in all fields");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_dashboard.php?msg=❌ Invalid email format");
        exit;
    }

    // Check for duplicate email
    $check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        header("Location: admin_dashboard.php?msg=❌ Email already exists");
        exit;
    }

    // Hash password
    $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $sql = "INSERT INTO users (firstname, lastname, email, pwd_hash, role_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssi", $firstname, $lastname, $email, $pwd_hash, $role_id);

    if ($stmt->execute()) {
        header("Location: admin_dashboard.php?msg=✅ User added successfully");
        exit;
    } else {
        header("Location: admin_dashboard.php?msg=❌ Database error: " . $mysqli->error);
        exit;
    }
}

// Display Add User Form
$rolesResult = $mysqli->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User</title>
    <link rel="stylesheet" href="styles_system.css">
</head>
<body>
<div class="content-area" style="max-width:600px; margin:auto; padding:40px;">
    <h1>➕ Add New User</h1>
    <form method="POST">
        <label>First Name</label>
        <input type="text" name="firstname" required>

        <label>Last Name</label>
        <input type="text" name="lastname" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Role</label>
        <select name="role_id" required>
            <option value="" disabled selected>Select a role</option>
            <?php while ($r = $rolesResult->fetch_assoc()): ?>
                <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars(ucfirst($r['role_name'])) ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="primary-btn" style="margin-top:15px;">Save User</button>
    </form>

    <a href="admin_dashboard.php" class="secondary-btn" style="margin-top:20px; display:inline-block;">⬅ Back to Dashboard</a>
</div>
</body>
</html>
