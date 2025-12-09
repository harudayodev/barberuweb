<?php
session_start();
$error_message = "";

include 'Connection.php'; // Use shared DB connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];  
    $password = $_POST['password'];

    // 1. Try admin login
    $stmt = $conn->prepare("SELECT AdminID, Password FROM adminaccount WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($adminID, $hashed_password);
        $stmt->fetch();
        if (!password_verify($password, $hashed_password)) {
            $error_message = "Incorrect password! Try again!";
        } else {
            // Check how many barbershops this admin owns
            $stmt2 = $conn->prepare("SELECT shopID, status FROM barbershops WHERE admin_id = ?");
            $stmt2->bind_param("i", $adminID);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $barbershops = $result2->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();

            // If any shop is not active, block access
            $inactiveShop = false;
            foreach ($barbershops as $shop) {
                if (trim(strtolower($shop['status'])) !== 'active') {
                    $inactiveShop = true;
                    break;
                }
            }
            if ($inactiveShop) {
                $error_message = "Your barbershop is not active. Please contact support.";
            } else if (count($barbershops) > 1) {
                $_SESSION['adminID'] = $adminID;
                $_SESSION['username'] = $username;
                // Redirect to barbershop selection page
                header("Location: barbershop_select.php");
                exit();
            } else if (count($barbershops) === 1) {
                $_SESSION['adminID'] = $adminID;
                $_SESSION['username'] = $username;
                $_SESSION['barbershopID'] = $barbershops[0]['shopID'];
                header("Location: management.php");
                exit();
            } else {
                $_SESSION['adminID'] = $adminID;
                $_SESSION['username'] = $username;
                // No barbershops found, go to dashboard or show error
                header("Location: management.php");
                exit();
            }
        }
    } else {
        // 2. Try barbershop login
    $stmt = $conn->prepare("SELECT shopID, password, status FROM barbershops WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($shopID, $hashed_password, $shop_status);
        $stmt->fetch();
        if (!password_verify($password, $hashed_password)) {
            $error_message = "Incorrect password! Try again!";
        } elseif (trim(strtolower($shop_status)) !== 'active') {
            // Debug: log status for troubleshooting
            error_log('Barbershop login blocked. Status: ' . $shop_status);
            $error_message = "Barbershop account is discontinued. Please contact admin.";
        } else {
            $_SESSION['barberID'] = $shopID;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        }
    } else {
        $error_message = "Incorrect username or password!";
    }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Barberu</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href='Resources/icon.png'>
    <link rel="shortcut icon" type="image/png" href="Resources/brand.png">
    <script>
        // Prevent back button from working
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
    </script>
</head>
<body>
    <!-- Blue background shapes for visual effect -->
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        body {
            min-height: 100vh;
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-sizing: border-box;
        }
        .container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
        }
        main {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 40px 30px;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .login-box img {
            width: 70px;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .login-box h1 {
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .login-box form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Poppins', sans-serif;
        }
        .login-box label {
            display: block;
            text-align: left;
            margin-bottom: 4px;
            font-size: 0.98em;
            color: #555;
        }
        .login-box .forgot-password {
            text-align: right;
            margin-top: 12px;
            margin-bottom: 12px;
        }
        .login-box .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-size: 0.95em;
        }
        .login-box .forgot-password a:hover {
            text-decoration: underline;
        }
        .login-box button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-box button[type="submit"]:hover {
            background: #1a242f;
        }
        .login-box .signup-link {
            margin-top: 10px;
            font-size: 0.98em;
        }
        .login-box .signup-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-box .signup-link a:hover {
            text-decoration: underline;
        }
        .login-box p[style*="color: red"] {
            margin: 0 0 8px 0;
            font-size: 0.98em;
        }
        .global-back-button {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
}

.btn-outline {
    font-family: 'Poppins', sans-serif;
    display: inline-block;
    padding: 10px 20px;
    border: 2px solid #3498db;
    background-color: transparent;
    color: #3498db;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.btn-outline:hover {
    background-color: #3498db;
    color: white;
}

    </style>
    <div class="bg-shape bg-shape-top-left"></div>
    <div class="bg-shape bg-shape-top-right"></div>
    <div class="container">
    <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Login to Barberu</h1>
                <form method="POST" action="login.php">
                    <div style="text-align:left;">
                        <label for="username">Email</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="password">Password</label>
                        <div style="position:relative;display:flex;align-items:center;">
                            <input type="password" id="password" name="password" required style="flex:1;">
                            <button type="button" id="togglePassword" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:0 6px;">
                                <span id="toggleIcon" style="display:flex;align-items:center;">
                                    <!-- Eye icon SVG (show) -->
                                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg>
                                    <!-- Eye-slash icon SVG (hide), hidden by default -->
                                    <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6 0-10-7-10-7a21.81 21.81 0 0 1 5.06-5.94"/><path d="M1 1l22 22"/><path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47"/><path d="M14.47 14.47A3 3 0 0 1 12 9a3 3 0 0 1-2.47 5.47"/></svg>
                                </span>
                            </button>
                        </div>
                     <!-- Top-left back button -->
<div class="global-back-button">
    <a href="roles.php" class="btn-outline">← Back</a>
</div>

</body>
<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeSlashIcon = document.getElementById('eyeSlashIcon');
    togglePassword.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        eyeIcon.style.display = isPassword ? 'none' : 'inline';
        eyeSlashIcon.style.display = isPassword ? 'inline' : 'none';
    });
</script>
                    <?php if (!empty($error_message)) { ?>
                        <p style="color: red; text-align:left;"><?php echo $error_message; ?></p>
                    <?php } ?>
                    <div class="forgot-password">
                        <a href="forgetpass.php">Forgot Password?</a>
                    </div>
                    <button type="submit">Login</button>
                    <p class="signup-link">Don't have an account? <a href="apply.php">Apply Now</a></p>
                </form>
            </div>
        </main>
    </div>
</body>
</html>