<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([1]); // Admin only

// Only handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Collect and sanitize form data
    $user_id   = isset($_POST["user_id"]) ? intval($_POST["user_id"]) : 0;
    $firstname = trim($_POST["firstname"] ?? '');
    $lastname  = trim($_POST["lastname"] ?? '');
    $email     = trim($_POST["email"] ?? '');
    $role_id   = intval($_POST["role_id"] ?? 0);

    // Validate input
    if ($user_id <= 0 || empty($firstname) || empty($lastname) || empty($email) || $role_id < 1 || $role_id > 8) {
        header("Location: admin_dashboard.php?msg=❌ Invalid input data.");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_dashboard.php?msg=❌ Invalid email format.");
        exit;
    }

    // Prevent duplicate emails
    $check = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        header("Location: admin_dashboard.php?msg=❌ Email already used by another user.");
        exit;
    }

    // Update user record
    $sql = "UPDATE users 
            SET firstname = ?, lastname = ?, email = ?, role_id = ?
            WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssii", $firstname, $lastname, $email, $role_id, $user_id);

    if ($stmt->execute()) {
        header("Location: admin_dashboard.php?msg=✅ User updated successfully.");
        exit;
    } else {
        header("Location: admin_dashboard.php?msg=❌ Database error: " . $mysqli->error);
        exit;
    }

} else {
    // If accessed directly (not POST)
    header("Location: admin_dashboard.php?msg=⚠️ Invalid request method.");
    exit;
}
