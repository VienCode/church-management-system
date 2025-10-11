<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admins can promote

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch user info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, contact, email, user_code, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit;
    }

    // If already leader
    if ($user["role_id"] == ROLE_LEADER) {
        header("Location: admin_dashboard.php?msg=⚠️ User is already a leader.");
        exit;
    }

    $fullname = $user["firstname"] . " " . $user["lastname"];
    $email = $user["email"];
    $contact = $user["contact"];

    // ✅ Update role to Leader
    $stmt = $mysqli->prepare("UPDATE users SET role_id = ?, user_code = CONCAT('L', SUBSTRING(user_code, 2)) WHERE id = ?");
    $stmt->bind_param("ii", $leader_role_id = ROLE_LEADER, $user_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Add to leaders table (if not already there)
    $check = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows == 0) {
        $insert = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert->bind_param("sss", $fullname, $contact, $email);
        $insert->execute();
        $insert->close();
    }
    $check->close();

    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader!");
    exit;
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit;
?>
