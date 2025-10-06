<?php
session_start();

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    header("Location: pre-index.php");
    exit;
}

$mysqli = require __DIR__ . "/database.php";

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

if ($role === "non_member") {
    $sql = "SELECT * FROM non_members WHERE id = ?";
} elseif ($role === "member") {
    $sql = "SELECT * FROM members WHERE id = ?";
}
  elseif ($role === "admin") {
    $sql = "SELECT * FROM admin_accounts WHERE admin_id = ?";
} else {
    die("Invalid role in session: " . htmlspecialchars($role));
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&family=Signika:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Unity Christian Fellowship</title>
</head>
<body class="index_body">
    <div class="welcome">
        <div class="topnav">
            <a href="logout.php">Logout</a> 
            <img class= "ucf-topnav" src="images/ucf.png" alt="UCF Logo Top Nav">
        </div>

        <?php if (isset($user)): ?>
            <div class="welcome_text">
                <h1>
                    <?php
                    if ($_SESSION["role"] === "admin") {
                        echo "Hello, Admin " . htmlspecialchars($user["admin_username"]) . " 👑";
                    } else {
                        echo "Hello, " . htmlspecialchars($user["firstname"] . " " . $user["lastname"]);
                    }
                    ?>
                </h1>
            </div>
        <?php else: ?>
            <?php
            header("Location: pre-index.php");
            exit;
            ?>
        <?php endif; ?>   

        <div class="button_container">
            <button class="customButton">ABOUT US</button>
            <button class="customButton">WHERE TO FIND US</button>
        </div>
    </div>
</body>
</html>