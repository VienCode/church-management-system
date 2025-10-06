<?php
//FIELD VALIDATION
if (
    empty($_POST["firstname"]) ||
    empty($_POST["lastname"]) ||
    empty($_POST["contact"]) ||
    empty($_POST["age"]) ||
    empty($_POST["user_address"]) ||
    empty($_POST["email"]) ||
    empty($_POST["pwd"]) ||
    empty($_POST["confirm_pwd"])
) {
    echo "<script>
        alert('Please fill in all required fields.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    echo "<script>
        alert('Invalid Email Address');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if (strlen($_POST["pwd"]) < 8) {
    echo "<script>
        alert('Password must be at least 8 characters long.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if (!preg_match("/[a-z]/i", $_POST["pwd"])) {
    echo "<script>
        alert('Password must contain at least one letter.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if (!preg_match("/[0-9]/", $_POST["pwd"])) {
    echo "<script>
        alert('Password must contain at least one number.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if (!preg_match("/^09\d{9}$/", $_POST["contact"])) {
    echo "<script>
        alert('Invalid contact number!\\nFormat: 09XXXXXXXXX');
        window.location.href = 'register.php';
    </script>";
    exit;
}

if ($_POST["pwd"] !== $_POST["confirm_pwd"]) {
    echo "<script>
        alert('Passwords do not match.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

// PASSWORD HASHING
$pwd_hash = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

// DATABASE CONNECTION
$mysqli = require __DIR__ . "/database.php";

// CHECK FOR DUPLICATE EMAIL
$check_sql = "SELECT email FROM non_members WHERE email = ?";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("s", $_POST["email"]);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "<script>
        alert('Email already exists! Please use a different one.');
        window.location.href = 'register.php';
    </script>";
    exit;
}

// INSERT INTO non_members TABLE
$sql = "INSERT INTO non_members 
        (firstname, lastname, suffix, contact, age, user_address, email, pwd_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->stmt_init();
if (!$stmt->prepare($sql)) {
    die('SQL error: ' . $mysqli->error);
}

$stmt->bind_param("sssissss",
    $_POST["firstname"],
    $_POST["lastname"],
    $_POST["suffix"],
    $_POST["contact"],
    $_POST["age"],
    $_POST["user_address"],
    $_POST["email"],
    $pwd_hash
);

if ($stmt->execute()) {
    echo "<script>
        alert('Registration successful! You are now registered as a Non-Member. Once you reach 10 attendances, you’ll automatically become a Member.');
        window.location.href = 'login.php';
    </script>";
    exit;
} else {
    die('Error: ' . $mysqli->error);
}
?>
