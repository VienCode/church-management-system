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

    if (!$user) {
        header("Location: admin_dashboard.php?msg=❌ User not found.");
        exit();
    }

    $fullname = $user["firstname"] . ' ' . $user["lastname"];
    $email = $user["email"];

    // Check if currently a leader
    if ($user["role_id"] != 2) {
        header("Location: admin_dashboard.php?msg=⚠️ $fullname is not a leader.");
        exit();
    }

    // 1️⃣ Find the leader ID (if exists)
    $getLeader = $mysqli->prepare("SELECT leader_id FROM leaders WHERE email = ?");
    $getLeader->bind_param("s", $email);
    $getLeader->execute();
    $leaderResult = $getLeader->get_result();
    $leader = $leaderResult->fetch_assoc();
    $getLeader->close();

    // 2️⃣ Demote to member (role_id = 3)
    $update = $mysqli->prepare("UPDATE users SET role_id = 3 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();

    // 3️⃣ If the leader exists, unassign all their members
    if ($leader) {
        $leader_id = $leader["leader_id"];
        $unassign = $mysqli->prepare("UPDATE users SET leader_id = NULL WHERE leader_id = ?");
        $unassign->bind_param("i", $leader_id);
        $unassign->execute();
        $unassign->close();

        // 4️⃣ Delete leader record
        $delete = $mysqli->prepare("DELETE FROM leaders WHERE leader_id = ?");
        $delete->bind_param("i", $leader_id);
        $delete->execute();
        $delete->close();
    }

    // ✅ Optional: Log demotion
    $log = $mysqli->prepare("
        INSERT INTO role_logs (user_id, old_role, new_role, changed_by, changed_at)
        VALUES (?, 'Leader', 'Member', ?, NOW())
    ");
    $admin_id = $_SESSION['user_id'];
    $log->bind_param("ii", $user_id, $admin_id);
    $log->execute();
    $log->close();

    header("Location: admin_dashboard.php?msg=✅ $fullname has been demoted and their members unassigned.");
    exit();
}

header("Location: admin_dashboard.php?msg=❌ Invalid request.");
exit();
?>
