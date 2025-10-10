<?php
session_start();

if (!isset($_SESSION['registered_user'])) {
    header("Location: register.php");
    exit;
}

$user = $_SESSION['registered_user'];
unset($_SESSION['registered_user']); // clear after showing once
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Successful | Unity Christian Fellowship</title>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Lexend Deca', sans-serif;
    background: #f4f7f9;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    margin: 0;
}
.success-container {
    background: #fff;
    padding: 40px 60px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
    max-width: 500px;
}
.success-container img {
    width: 90px;
    margin-bottom: 20px;
}
h1 {
    color: #0271c0;
    font-size: 26px;
    margin-bottom: 15px;
}
h2 {
    color: #222;
    margin-bottom: 10px;
}
p {
    color: #555;
    font-size: 16px;
    margin-bottom: 15px;
}
.user-code {
    background: #e8f3ff;
    color: #0056b3;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 20px;
    font-weight: 700;
    margin: 10px 0;
    display: inline-block;
}
.login-btn {
    background: #0271c0;
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.3s;
}
.login-btn:hover {
    background: #02589b;
}
</style>
</head>
<body>
    <div class="success-container">
        <img src="images/ucf.png" alt="UCF Logo">
        <h1>🎉 Registration Successful!</h1>
        <h2>Welcome, <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>!</h2>
        <p>You’ve been registered as a <strong><?= htmlspecialchars($user['role']) ?></strong>.</p>
        <p>Your unique user code is:</p>
        <div class="user-code"><?= htmlspecialchars($user['user_code']) ?></div>
        <p>Please keep this code safe — you’ll use it for attendance and record tracking.</p>
        <a href="login.php" class="login-btn">Proceed to Login →</a>
    </div>
</body>
</html>
