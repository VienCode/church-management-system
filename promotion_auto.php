<?php
include 'database.php';
include 'auth_check.php';
restrict_to_roles([ROLE_ADMIN]); // Only Admin can run automatic promotions

// Fetch all non-members with 10+ attendances
$query = "SELECT * FROM non_members WHERE attendances_count >= 10";
$result = $mysqli->query($query);

if (!$result) {
    die("Query failed: " . $mysqli->error);
}

$promoted = 0;
$logs = [];

while ($nonMember = $result->fetch_assoc()) {

    // Generate unique Member user_code
    do {
        $new_code = 'M' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $mysqli->prepare("SELECT 1 FROM users WHERE user_code = ?");
        $check->bind_param("s", $new_code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
    } while ($exists);

    // Prepare full name for logs
    $fullname = trim($nonMember['firstname'] . ' ' . $nonMember['lastname']);

    // Insert into `users`
    $stmt = $mysqli->prepare("
        INSERT INTO users 
        (user_code, firstname, lastname, suffix, contact, age, user_address, email, pwd_hash, created_at, role_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 3)
    ");
    $stmt->bind_param(
        "sssisssss",
        $new_code,
        $nonMember['firstname'],
        $nonMember['lastname'],
        $nonMember['suffix'],
        $nonMember['contact'],
        $nonMember['age'],
        $nonMember['user_address'],
        $nonMember['email'],
        $nonMember['pwd_hash']
    );

    if ($stmt->execute()) {
        // Delete from non_members
        $delete = $mysqli->prepare("DELETE FROM non_members WHERE id = ?");
        $delete->bind_param("i", $nonMember['id']);
        $delete->execute();

        // Optional logging
        $logs[] = "✅ Promoted: $fullname → Member (Code: $new_code)";
        $promoted++;
    }
}

$_SESSION['promotion_result'] = 
    ($promoted > 0)
    ? "🎉 Successfully promoted $promoted non-member(s): <br>" . implode("<br>", $logs)
    : "No non-members eligible for promotion yet.";

header("Location: promotion_page.php");
exit;
?>
