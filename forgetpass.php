<?php
session_start();
$success_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'Connection.php';
    $email = $_POST['email'];
    // Check if email exists in adminaccount or sadminaccount
    $stmt = $conn->prepare("SELECT Username FROM adminaccount WHERE Username = ? UNION SELECT Username FROM sadminaccount WHERE Username = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Email exists, generate token and store it (in a new table if needed)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        // Create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS password_resets (email VARCHAR(255), token VARCHAR(64), expires DATETIME, PRIMARY KEY(email))");
        // Insert or update token
        $stmt2 = $conn->prepare("REPLACE INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
        $stmt2->bind_param("sss", $email, $token, $expires);
        $stmt2->execute();
        $stmt2->close();
        // Send email with reset link
        require_once 'send_email.php';
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
        $mailSent = send_password_reset_email($email, $reset_link);
        if ($mailSent) {
            $success_message = "A password reset link has been sent to your email!";
        } else {
            $success_message = "Failed to send email. Please try again later or contact support.";
        }
    } else {
        $success_message = "Email address not found!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Barberu</title>
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
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            max-width: 100vw;
            overflow-x: hidden;
        }
        body {
            min-height: 100vh;
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
            box-sizing: border-box;
            max-width: 100vw;
            overflow-x: hidden;
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
        .login-box input[type="email"] {
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
        .login-box p[style*="color: green"] {
            margin: 0 0 8px 0;
            font-size: 0.98em;
        }
    </style>
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <div class="container">
    <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Forgot Password</h1>
                <form method="POST" action="forgetpass.php">
                    <div style="text-align:left;">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <?php if (!empty($success_message)) { ?>
                        <p style="color: green; text-align:left;"> <?php echo $success_message; ?> </p>
                    <?php } ?>
                    <button type="submit">Send Reset Link</button>
                    <p class="signup-link">Remember your password? <a href="login.php">Login</a></p>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

