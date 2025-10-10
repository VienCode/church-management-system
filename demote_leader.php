<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only admin can demote leaders

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $user_id = intval($_POST["user_id"]);

    // Fetch leader info
    $stmt = $mysqli->prepare("SELECT firstname, lastname, email, role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = $user["firstname"] . ' ' . $user["lastname"];
    $email = $user["email"];

    // Ensure they are a leader
    if ($user["role_id"] != 2) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not a leader.");
        exit();
    }

    // 1️⃣ Find the leader record (if exists)
    $getLeader = $mysqli->prepare("SELECT leader_id, leader_name FROM leaders WHERE email = ?");
    $getLeader->bind_param("s", $email);
    $getLeader->execute();
    $leaderResult = $getLeader->get_result();
    $leader = $leaderResult->fetch_assoc();
    $getLeader->close();

    // 2️⃣ Demote the leader (set role_id = 3)
    $update = $mysqli->prepare("UPDATE users SET role_id = 3 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();

    // 3️⃣ If they exist in the leaders table, unassign their members and record last_leader_name
    if ($leader) {
        $leader_id = $leader["leader_id"];
        $leader_name = $leader["leader_name"];

        // 🧩 Unassign members and store their last leader info
        $unassign = $mysqli->prepare("
            UPDATE users 
            SET leader_id = NULL, 
                last_unassigned_at = NOW(), 
                last_leader_name = ?
            WHERE leader_id = ?
        ");
        $unassign->bind_param("si", $leader_name, $leader_id);
        $unassign->execute();
        $affected = $unassign->affected_rows;
        $unassign->close();

        // 🧩 Delete the leader record
        $delete = $mysqli->prepare("DELETE FROM leaders WHERE leader_id = ?");
        $delete->bind_param("i", $leader_id);
        $delete->execute();
        $delete->close();

        $msg = "✅ $fullname has been demoted and $affected member(s) were unassigned from their cell group.";
    } else {
        $msg = "✅ $fullname has been demoted (no members found under them).";
    }

    // 4️⃣ Log demotion
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $admin_id = $_SESSION['user_id'];
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    // 5️⃣ Redirect with confirmation
    header("Location: admin_dashboard.php?msg=" . urlencode($msg));
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
