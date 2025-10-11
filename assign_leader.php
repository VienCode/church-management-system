<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch user info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, contact, user_code, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = $user["firstname"] . " " . $user["lastname"];
    $email = $user["email"];
    $contact = $user["contact"];
    $old_code = $user["user_code"];
    $old_role = $user["role_id"];

    // --- 1️⃣ Update Role to Leader ---
    $update_role = $mysqli->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
    $update_role->bind_param("i", $user_id);
    $update_role->execute();
    $update_role->close();

    // --- 2️⃣ Update User Code Prefix ---
    // If code starts with M (Member), replace with L (Leader)
    $new_code = preg_replace('/^M/', 'L', $old_code);
    // If no M prefix, generate new Leader-style code
    if ($new_code === $old_code) {
        $new_code = 'L' . substr($old_code, 1);
    }

    $update_code = $mysqli->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $update_code->bind_param("si", $new_code, $user_id);
    $update_code->execute();
    $update_code->close();

    // --- 3️⃣ Add to Leaders Table if not exists ---
    $check_leader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $check_leader->bind_param("s", $email);
    $check_leader->execute();
    $check_leader->store_result();

    if ($check_leader->num_rows === 0) {
        $check_leader->close();

      $insertLeader = $mysqli->prepare("
            INSERT INTO leaders (leader_name, contact, email, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE leader_name = VALUES(leader_name), contact = VALUES(contact)
        ");
        $fullname = $user['firstname'] . ' ' . $user['lastname'];
        $insertLeader->bind_param("sss", $fullname, $user['contact'], $user['email']);
        $insertLeader->execute();
    } else {
        $check_leader->close();
    }

    // --- 4️⃣ Optional: Record role change in role_logs ---
    $admin_id = $_SESSION["user_id"];
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, ?, 'Leader', ?, NOW())
    ");
    $log->bind_param("iii", $user_id, $old_role, $admin_id);
    $log->execute();
    $log->close();

    // --- 5️⃣ Redirect with success message ---
    header("Location: admin_dashboard.php?msg=✅ $fullname has been promoted to Leader successfully!");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
