<?php
session_start();
include 'Connection.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error_message = '';
$success_message = '';
$email = '';

if ($token) {
    // Validate token
    $stmt = $conn->prepare("SELECT email, expires FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($email, $expires);
        $stmt->fetch();
        if (strtotime($expires) < time()) {
            $error_message = "This reset link has expired.";
        }
    } else {
        $error_message = "Invalid or expired reset link.";
    }
    $stmt->close();
} else {
    $success_message = "Your password has been reset successfully! You can now <a href='login.php'>login</a>.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password']) && isset($_POST['token'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($email);
        $stmt->fetch();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Update password in adminaccount if exists
        $stmt1 = $conn->prepare("SELECT Username FROM adminaccount WHERE Username = ?");
        $stmt1->bind_param("s", $email);
        $stmt1->execute();
        $stmt1->store_result();
        if ($stmt1->num_rows === 1) {
            $stmtUpdate = $conn->prepare("UPDATE adminaccount SET Password = ? WHERE Username = ?");
            $stmtUpdate->bind_param("ss", $hashed_password, $email);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
        $stmt1->close();
        // Update password in sadminaccount if exists
        $stmt2 = $conn->prepare("SELECT Username FROM sadminaccount WHERE Username = ?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $stmt2->store_result();
        if ($stmt2->num_rows === 1) {
            $stmtUpdate2 = $conn->prepare("UPDATE sadminaccount SET Password = ? WHERE Username = ?");
            $stmtUpdate2->bind_param("ss", $hashed_password, $email);
            $stmtUpdate2->execute();
            $stmtUpdate2->close();
        }
        $stmt2->close();
        // Remove the reset token
        $stmt3 = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt3->bind_param("s", $email);
        $stmt3->execute();
        $stmt3->close();
        $success_message = "Your password has been reset successfully! You can now <a href='login.php'>login</a>.";
    } else {
        $error_message = "Invalid or expired reset link.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Barberu</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href='Resources/icon.png'>
    <link rel="shortcut icon" type="image/png" href="Resources/brand.png">
</head>
<body>
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
        .login-box p[style*="color: red"], .login-box p[style*="color: green"] {
            margin: 0 0 8px 0;
            font-size: 0.98em;
        }
    </style>
    <div class="container">
    <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Reset Password</h1>
                <?php if (!empty($error_message)) { ?>
                    <p style="color: red; text-align:left;"> <?php echo $error_message; ?> </p>
                <?php } elseif (!empty($success_message)) { ?>
                    <p style="color: green; text-align:center; font-size: 1.3em; font-weight: bold;"> <?php echo $success_message; ?> </p>
                <?php } elseif ($token && empty($error_message)) { ?>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div style="text-align:left;">
                        <label for="password">New Password</label>
                        <div style="position:relative;display:flex;align-items:center;">
                            <input type="password" id="password" name="password" required style="flex:1;" placeholder="New Password">
                            <button type="button" id="togglePassword" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:0 6px;">
                                <span id="toggleIcon" style="display:flex;align-items:center;">
                                    <!-- Eye icon SVG (show) -->
                                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg>
                                    <!-- Eye-slash icon SVG (hide), hidden by default -->
                                    <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6 0-10-7-10-7a21.81 21.81 0 0 1 5.06-5.94"/><path d="M1 1l22 22"/><path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47"/><path d="M14.47 14.47A3 3 0 0 1 12 9a3 3 0 0 1-2.47 5.47"/></svg>
                                </span>
                            </button>
                        </div>
                        <div style="position:relative;display:flex;align-items:center;margin-top:10px;">
                            <input type="password" id="retype_password" name="retype_password" required style="flex:1;" placeholder="Retype Password">
                            <button type="button" id="toggleRetypePassword" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:0 6px;">
                                <span id="toggleRetypeIcon" style="display:flex;align-items:center;">
                                    <!-- Eye icon SVG (show) -->
                                    <svg id="eyeRetypeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg>
                                    <!-- Eye-slash icon SVG (hide), hidden by default -->
                                    <svg id="eyeRetypeSlashIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6 0-10-7-10-7a21.81 21.81 0 0 1 5.06-5.94"/><path d="M1 1l22 22"/><path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47"/><path d="M14.47 14.47A3 3 0 0 1 12 9a3 3 0 0 1-2.47 5.47"/></svg>
                                </span>
                            </button>
                        </div>
            <button type="submit" style="margin-top:16px;">Set New Password</button>
                </form>
                <?php } ?>
            </div>
        </main>
    </div>
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
        // Re-apply style to keep design consistent
        passwordInput.style.border = '1px solid #ccc';
        passwordInput.style.borderRadius = '8px';
        passwordInput.style.fontSize = '1em';
        passwordInput.style.fontFamily = "'Poppins', sans-serif";
        passwordInput.style.padding = '12px 10px';
    });

    const retypePasswordInput = document.getElementById('retype_password');
    const toggleRetypePassword = document.getElementById('toggleRetypePassword');
    const eyeRetypeIcon = document.getElementById('eyeRetypeIcon');
    const eyeRetypeSlashIcon = document.getElementById('eyeRetypeSlashIcon');
    toggleRetypePassword.addEventListener('click', function() {
        const isPassword = retypePasswordInput.type === 'password';
        retypePasswordInput.type = isPassword ? 'text' : 'password';
        eyeRetypeIcon.style.display = isPassword ? 'none' : 'inline';
        eyeRetypeSlashIcon.style.display = isPassword ? 'inline' : 'none';
        // Re-apply style to keep design consistent
        retypePasswordInput.style.border = '1px solid #ccc';
        retypePasswordInput.style.borderRadius = '8px';
        retypePasswordInput.style.fontSize = '1em';
        retypePasswordInput.style.fontFamily = "'Poppins', sans-serif";
        retypePasswordInput.style.padding = '12px 10px';
    });

    // Prevent form submission if passwords do not match
    document.querySelector('form').addEventListener('submit', function(e) {
        if (passwordInput.value !== retypePasswordInput.value) {
            alert('Passwords do not match!');
            e.preventDefault();
        }
    });
</script>
</body>
</html>
