<?php
session_start();
if (isset($_SESSION['register_errors'])) {
    echo '<div style="color: #ff6666; font-weight: bold; margin-bottom: 15px;">';
    foreach ($_SESSION['register_errors'] as $error) {
        echo "• " . htmlspecialchars($error) . "<br>";
    }
    echo '</div>';
    unset($_SESSION['register_errors']);
}

if (isset($_SESSION['register_success'])) {
    echo '<div style="color: #00cc66; font-weight: bold; margin-bottom: 15px;">' 
        . htmlspecialchars($_SESSION['register_success']) . '</div>';
    unset($_SESSION['register_success']);
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
    <title>Register</title>
</head>
<body class="register_body">

    <div class="topnav">
        <img class="ucf-topnav" src="images/ucf.png" alt="UCF Logo Top Nav">
        <a href="login.php">Back to Login</a>
        <a href="pre-index.php" class="active">Home</a>          
    </div>

    <div class="container-register">
        <div class="register">

            <h3>Register Form</h3>

            <form action="process-signup.php" method="post" name="register" id="register" novalidate>
                <input required class="customInput" type="text" name="firstname" placeholder="First Name*"> 
                <input class="customInput" type="text" name="middlename" placeholder="Middle Name (Optional)"><br><br>
                <input required class="customInput" type="text" name="lastname" placeholder="Last Name*">

                <select class="customSelect" name="suffix" id="suffix">
                    <option value="" disabled selected hidden>Suffix</option>
                    <option value="None">None</option>
                    <option value="Sr.">Sr.</option>
                    <option value="Jr.">Jr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                </select><br><br>

                <input required class="customInput" type="number" name="contact" placeholder="Contact Number*"> 
                <input required class="customInput" type="number" name="age" placeholder="Age*" min="0"><br><br>
                <input required class="customAddressInput" type="text" name="user_address" placeholder="Address*">

                <label for="cell_leader">Cell Leader:</label>
                <select class="customLeaderInput" name="cell_leader" id="cell_leader">
                    <option value="" disabled selected hidden>Cell Leader</option>
                    <option value="None">None</option>
                    <option value="JL Taberdo">JL Taberdo</option>
                    <option value="JC Casidor">JC Casidor</option>
                    <option value="Jav Agustin">Jav Agustin</option>
                    <option value="Dave Dapitillo">Dave Dapitillo</option>
                </select><br><br>

                <h5>*Input the desired information to use</h5>
                <input required class="customInput" type="email" name="email" placeholder="Email*">
                <br>
                <h5>Password must be at least 8 characters long, with at least 1 letter or number.</h5>
                <input required class="customInput" type="password" name="pwd" placeholder="Password*"> 
                <input required class="customInput" type="password" name="confirm_pwd" placeholder="Confirm Password">
                <br><br>

                <button type="submit" name="submit" class="customButton">Submit</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        function handleSelect(selectElement) {
            if (!selectElement) return; 
            if (!selectElement.value) selectElement.style.color = "gray";
            selectElement.addEventListener("change", function () {
                this.style.color = this.value ? "black" : "gray";
            });
        }
        handleSelect(document.getElementById("suffix"));
        handleSelect(document.getElementById("cell_leader"));
    });
    </script>

</body>
</html>
