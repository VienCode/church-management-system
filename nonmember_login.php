<?php

session_start();

$is_invalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mysqli = require __DIR__ . "/database.php";

    $sql = "SELECT * FROM non_members WHERE email = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $_POST["email"]);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($_POST["pwd"], $user["pwd_hash"])) {

        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["role"] = "non_member";

        header("Location: nonmember_profile.php");
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
            
            <h3>Log in to your account (Non-Member)</h3>

            <?php if ($is_invalid): ?>
                <em> Invalid Login </em>
            <?php endif; ?> 

            <form method="post">
                <input required class="customInput" type="text" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
                <br> <br>
                <input required class="customInput" type="password" name="pwd" placeholder="Password">
                <br> <br>
                <button class="customButton">Login</button>
                <br> <br>
                <a href="register.php">Don't have an account? Click here to register!</a>
            </form>
        </div>
    </div>

</body>
</html>