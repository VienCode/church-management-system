<?php
session_start();
$mysqli = require __DIR__ . "/db.php";

$is_invalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Search main users table
    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // If not found, search non_members
    if (!$user) {
        $sql = "SELECT * FROM non_members WHERE email = ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user) {
            $user["role"] = "non-member";
            $user["role_id"] = 4;
        }
    }

    // Validate password
    if ($user && hash("sha256", $password) === $user["pwd_hash"]) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["firstname"] = $user["firstname"];
        $_SESSION["lastname"] = $user["lastname"];
        $_SESSION["role"] = strtolower($user["role"]);
        $_SESSION["role_id"] = $user["role_id"];

        switch ($_SESSION["role"]) {
            case "admin":
                header("Location: admin_dashboard.php");
                break;
            case "leader":
            case "cell_group_leader":
                header("Location: cellgroup.php");
                break;
            case "member":
                header("Location: dashboard.php");
                break;
            case "accountant":
                header("Location: donations.php");
                break;
            case "pastor":
                header("Location: dashboard.php");
                break;
            case "editor":
                header("Location: upload.php");
                break;
            case "non-member":
                header("Location: nonmember_profile.php");
                break;
            default:
                header("Location: unauthorized.php");
        }
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
    <title>UCF Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login_body">
    <div class="topnav">
        <img class="ucf-topnav" src="images/ucf.png" alt="UCF Logo">
        <a href="pre-index.php" class="active">Home</a>
        <a href="register.php" class="active">Register</a>
    </div>

    <div class="container-login">
        <div class="login">
            <img class="ucf-logo" src="images/ucf.png" alt="UCF Logo" style="width:80px; margin-bottom:10px;">
            <h2 style="color:white; text-shadow:2px 2px 3px black;">Welcome to Unity Christian Fellowship</h2>
            <h3 style="color:#fff;">Please sign in to continue</h3>

            <?php if ($is_invalid): ?>
                <div style="
                    background-color: rgba(255, 0, 0, 0.2);
                    border: 1px solid red;
                    color: white;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    font-weight: bold;">
                    Invalid email or password.
                </div>
            <?php endif; ?>

            <form method="POST" style="display:flex; flex-direction:column; align-items:center;">
                <input class="customInput" type="email" name="email" placeholder="Enter your email" required>
                <input class="customInput" type="password" name="password" placeholder="Enter your password" required>
                <button type="submit" class="customButton">Login</button>
            </form>

            <p style="margin-top:15px; color:white; text-shadow:1px 1px 2px black;">
                Don’t have an account?
                <a href="register.php" style="color: #e0f7ff; text-decoration: underline;">Register here</a>
            </p>
        </div>
    </div>

    <div class="footer">
        © <?= date("Y") ?> Unity Christian Fellowship | All Rights Reserved
    </div>
</body>
</html>
