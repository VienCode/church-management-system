<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

$is_invalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Try to find user in main users table first
    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // If not found, check non_members table
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

    // Validate user if found
    if ($user) {
        // Compare hashed password
        if (hash("sha256", $password) === $user["pwd_hash"]) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["firstname"] = $user["firstname"];
            $_SESSION["lastname"] = $user["lastname"];
            $_SESSION["role"] = strtolower($user["role"]);
            $_SESSION["role_id"] = $user["role_id"];

            // Role-based redirection
            switch ($_SESSION["role"]) {
                case "admin":
                    header("Location: admin_dashboard.php");
                    break;
                case "leader":
                case "cell_group_leader":
                    header("Location: leader_dashboard.php");
                    break;
                case "member":
                    header("Location: member_dashboard.php");
                    break;
                case "accountant":
                    header("Location: accountant_dashboard.php");
                    break;
                case "pastor":
                    header("Location: pastor_dashboard.php");
                    break;
                case "editor":
                    header("Location: editor_dashboard.php");
                    break;
                case "non-member":
                    header("Location: nonmember_dashboard.php");
                    break;
                default:
                    header("Location: unauthorized.php");
            }
            exit;
        }
    }

    $is_invalid = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Login</title>
</head>
<body class="login_body">

    <div class="topnav">
        <img class="ucf-topnav" src="images/ucf.png" alt="UCF Logo Top Nav">
        <a href="pre-index.php" class="active">Home</a>
        <a href="register.php" class="active">Register</a>          
    </div>

    <div class="container-login">
        <div class="login">
            <h2>Login to Your Account</h2>

            <?php if ($is_invalid): ?>
                <div class="error-message" style="color:red; margin-bottom:10px;">
                    ❌ Invalid email or password.
                </div>
            <?php endif; ?>

            <form method="POST">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>

                <button type="submit" class="customButton">Login</button>
            </form>

            <p style="margin-top:10px;">
                <a href="register.php" style="color:#333; text-decoration:none;">Don’t have an account? Register here.</a>
            </p>
        </div>
    </div>

</body>
</html>
