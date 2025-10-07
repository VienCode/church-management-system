<?php
$mysqli = include 'database.php';

session_start();

$is_invalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mysqli = require __DIR__ . "/database.php";

    $sql = "SELECT * FROM admin_accounts WHERE admin_username = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $_POST["email"]); // username is stored in admin_username
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // If your admin_password is plain text in DB
    if ($user && $_POST["pwd"] === $user["admin_password"]) {
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["admin_id"];
        $_SESSION["role"] = "Admin"; //  Added role for role-based access system

        header("Location: dashboard.php"); // Admin landing page
        exit;
    }

    $is_invalid = true;
}
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
    <title>Login</title>
</head>
<body class="login_body">
    
    <div class="topnav">
        <img class= "ucf-topnav" src="images/ucf.png" alt="UCF Logo Top Nav">
        <a href="login.php" class="active">Back to Login</a>        
    </div>

    <div class="container-login">
        <div class="login">

            <img class= "ucf-logo" src="images/ucf.png" alt="Unity Christian Fellowship Logo">
            
            <h3>Admin Login</h3>

            <?php if ($is_invalid): ?>
                <em> Invalid Login </em>
            <?php endif; ?> 

            <form method="post">
                <input required class="customInput" type="text" name="email" placeholder="Username" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
                <br> <br>
                <input required class="customInput" type="password" name="pwd" placeholder="Password">
                <br> <br>
                <button class="customButton" name="role" value="admin">Login as Admin</button>
                <button class="customButton" name="role" value="accountant">Login as Accountant</button>
                <button class="customButton" name="role" value="pastor">Login as Pastor</button>
            </form>
        </div>
    </div>

</body>
</html>