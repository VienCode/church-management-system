<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only admin can promote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // 1️⃣ Fetch user info first
    $stmt = $mysqli->prepare("SELECT firstname, lastname, contact, email, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = $user["firstname"] . ' ' . $user["lastname"];
    $contact = $user["contact"] ?? '';
    $email = $user["email"] ?? '';

    // 2️⃣ Promote user to Leader (role_id = 2)
    $update = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();

    // 3️⃣ Check if already exists in leaders table
    $check = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;

    // 4️⃣ If not in leaders table, insert them
    if (!$exists) {
        $insert = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert->bind_param("sss", $fullname, $contact, $email);
        $insert->execute();
        $insert->close();
    }

    $stmt->close();
    $check->close();
    $update->close();

    // ✅ Success message
    header("Location: admin_dashboard.php?msg=✅ {$fullname} has been successfully promoted to Leader!");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
