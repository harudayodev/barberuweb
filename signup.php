<?php
session_start();
if (!isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$success_message = "";
$error_message = "";


include 'Connection.php'; // Use shared DB connection


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];  
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long!";
    } else if (!preg_match('/[A-Z]/', $password)) {
        $error_message = "Password must contain at least one uppercase letter!";
    } else if (!preg_match('/[a-z]/', $password)) {
        $error_message = "Password must contain at least one lowercase letter!";
    } else if (!preg_match('/[0-9]/', $password)) {
        $error_message = "Password must contain at least one number!";
    } else if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if username already exists
        $stmt = $mysqli->prepare("SELECT AdminID FROM adminaccount WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_message = "Username already exists!";
        } else {
            // Insert new admin
            $stmt = $mysqli->prepare("INSERT INTO adminaccount (Username, Password, DateCreated) VALUES (?, ?, CURDATE())");
            $stmt->bind_param("ss", $username, $hashed_password);
            if ($stmt->execute()) {
                $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <link rel="icon" type="image/x-icon" href='Resources/icon.png'>
    <title>Signup</title>
    <script>
        // If the page is loaded from the back/forward cache, force a reload
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</head>
<body>
    <button onclick="window.location.href='sadmin.php'" style="position: absolute; top: 20px; left: 20px; padding: 8px 16px; background: #3498db; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Back</button>
    <div class="login-container">
        <h2><img src="Resources/brand.png" alt="Brand Logo" class="brand-logo"> BARBERU MANAGEMENT</h2>
       
        <form method="POST" action="signup.php" id="signup-form">
            <div class="input-group">
                <input type="text" id="username" name="username" required>
                <label for="username">Username</label>
            </div>
           
            <div class="input-group">
                <input type="password" id="password" name="password" required>
                <label for="password">Password</label>
            </div>
           
            <div class="input-group">
                <input type="password" id="confirm_password" name="confirm_password" required>
                <label for="confirm_password">Confirm Password</label>
            </div>


            <?php if (!empty($error_message)) { ?>
                <p style="color: red;"> <?php echo $error_message; ?> </p>
            <?php } ?>
            <?php if (!empty($success_message)) { ?>
                <p style="color: green;"> <?php echo $success_message; ?> </p>
            <?php } ?>


            <button type="submit" class="login-btn">Sign Up</button>
            <p class="signup-link">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</body>
</html>
