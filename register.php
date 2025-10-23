<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Fetch leaders dynamically
$leaders_result = $mysqli->query("SELECT leader_id, leader_name FROM leaders WHERE status = 'active' ORDER BY leader_name ASC");

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
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&family=Signika:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Register</title>
    <style>
        /* Password Visibility Toggle */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 40px !important;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #555;
        }
        .toggle-password:hover {
            color: #111;
        }

        /* Strength Meter */
        .strength-meter {
            height: 8px;
            width: 100%;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 6px 0 10px;
        }
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            background: red;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
        }
        .strength-text {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        /* Match Validation */
        #matchText {
            font-size: 13px;
            font-weight: 600;
            margin-top: 5px;
        }
        #matchText.match { color: #00cc66; }
        #matchText.nomatch { color: #ff3333; }
    </style>
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

                <h4>Are you an existing church member?</h4>
                <select required class="customLeaderInput" name="is_existing_member" id="is_existing_member">
                    <option value="" disabled selected hidden>Select Option</option>
                    <option value="no">No (I’m a new attendee)</option>
                    <option value="yes">Yes (I’m already a member)</option>
                </select>

                <div id="leaderSelectContainer" style="display:none; margin-top:10px;">
                    <label for="leader_id">Select Your Leader:</label>
                    <select class="customLeaderInput" name="leader_id" id="leader_id">
                        <option value="" disabled selected hidden>Select Leader</option>
                        <?php while ($row = $leaders_result->fetch_assoc()): ?>
                            <option value="<?= $row['leader_id'] ?>"><?= htmlspecialchars($row['leader_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <br><br>

                <h5>*Input the desired information to use</h5>
                <input required class="customInput" type="email" name="email" placeholder="Email*">
                <br>

                <h5>Password must be at least 8 characters long, with at least 1 letter or number.</h5>

                <!-- Password Field -->
                <div class="password-wrapper">
                    <input required class="customInput" type="password" name="pwd" id="pwd" placeholder="Password*">
                    <button type="button" class="toggle-password" id="togglePwd" title="Show/Hide Password">👁️</button>
                </div>

                <div class="strength-meter"><div id="strength-fill" class="strength-meter-fill"></div></div>
                <div id="strength-text" class="strength-text">Strength: Weak</div>

                <!-- Confirm Password Field -->
                <div class="password-wrapper">
                    <input required class="customInput" type="password" name="confirm_pwd" id="confirm_pwd" placeholder="Confirm Password*">
                    <button type="button" class="toggle-password" id="toggleConfirmPwd" title="Show/Hide Password">👁️</button>
                </div>

                <div id="matchText" class=""></div>

                <br><button type="submit" name="submit" class="customButton">Submit</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const memberSelect = document.getElementById("is_existing_member");
        const leaderSelectContainer = document.getElementById("leaderSelectContainer");
        const pwd = document.getElementById("pwd");
        const confirmPwd = document.getElementById("confirm_pwd");
        const matchText = document.getElementById("matchText");
        const fill = document.getElementById("strength-fill");
        const text = document.getElementById("strength-text");
        const togglePwd = document.getElementById("togglePwd");
        const toggleConfirmPwd = document.getElementById("toggleConfirmPwd");

        // Member dropdown logic
        memberSelect.addEventListener("change", function () {
            leaderSelectContainer.style.display = (this.value === "yes") ? "block" : "none";
        });

        // Password toggle buttons
        function toggleVisibility(input, button) {
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            button.textContent = isPassword ? "🙈" : "👁️";
        }
        togglePwd.addEventListener("click", () => toggleVisibility(pwd, togglePwd));
        toggleConfirmPwd.addEventListener("click", () => toggleVisibility(confirmPwd, toggleConfirmPwd));

        // Password strength meter
        pwd.addEventListener("input", function () {
            const val = pwd.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[a-z]/.test(val)) score++;
            if (/\d/.test(val)) score++;
            if (/[@$!%*?&#^()_\-+=]/.test(val)) score++;

            const percent = (score / 5) * 100;
            fill.style.width = percent + "%";

            if (score <= 2) {
                fill.style.background = "red";
                text.textContent = "Strength: Weak";
            } else if (score === 3) {
                fill.style.background = "orange";
                text.textContent = "Strength: Moderate";
            } else if (score === 4) {
                fill.style.background = "#fbc02d";
                text.textContent = "Strength: Strong";
            } else {
                fill.style.background = "green";
                text.textContent = "Strength: Very Strong";
            }
        });

        // Confirm password match validation
        function checkMatch() {
            if (confirmPwd.value === "") {
                matchText.textContent = "";
                return;
            }
            if (pwd.value === confirmPwd.value) {
                matchText.textContent = "✅ Passwords match";
                matchText.className = "match";
            } else {
                matchText.textContent = "❌ Passwords do not match";
                matchText.className = "nomatch";
            }
        }
        pwd.addEventListener("input", checkMatch);
        confirmPwd.addEventListener("input", checkMatch);
    });
    </script>
</body>
</html>
