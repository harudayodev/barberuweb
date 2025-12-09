<?php
session_start();
include 'Connection.php';

// Generate a random 8-character password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

$error_message = "";
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : (isset($_POST['app_id']) ? intval($_POST['app_id']) : 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $app_id = intval($_POST['app_id']);

    // Check super admin credentials
    $stmt = $conn->prepare("SELECT SAdminID, Password FROM sadminaccount WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($sadminID, $hashed_password);
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            // Approve the application and create admin account only if email is sent
            $result = $conn->query("SELECT * FROM application WHERE shopID = $app_id");
            if ($result && $app = $result->fetch_assoc()) {
                // Use owner email as admin username for multi-tenant support
                $admin_username = $app['business_email'];
                $plain_password = generateRandomPassword(8);
                // Store only the hashed password in the database, send the plain password in the email
                $admin_hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

                require_once 'send_email.php';
                // Send only the randomly generated plain password in the email
                $email_sent = send_account_email($app['business_email'], $app['business_name'], $admin_username, $plain_password);
                if (!$email_sent) {
                    error_log('Failed to send acceptance email to ' . $app['business_email']);
                    $error_message = 'Failed to send acceptance email. Account was not created.';
                } else {
                    // Check if an admin account for this email already exists
                    if ($conn->ping() === false) {
                        require 'Connection.php'; // Re-establish connection
                    }
                    $stmt_check = $conn->prepare("SELECT AdminID, Username FROM adminaccount WHERE Username = ?");
                    if (!$stmt_check) {
                        die("Database error (adminaccount SELECT): " . $conn->error);
                    }
                    $stmt_check->bind_param("s", $admin_username);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    $admin_id = null;
                    if ($stmt_check->num_rows > 0) {
                        // Admin exists, get their AdminID
                        $stmt_check->bind_result($existing_admin_id, $existing_username);
                        $stmt_check->fetch();
                        $admin_id = $existing_admin_id;
                        // Send alternate email for second shop
                        require_once 'send_email.php';
                        if (function_exists('other_shop_application')) {
                            other_shop_application($app['business_email'], $app['business_name'], $admin_username);
                        }
                        // Insert new barbershop for this admin, with dti and police clearance columns
                        if (isset($app['latitude']) && isset($app['longitude'])) {
                            $stmt2 = $conn->prepare("INSERT INTO barbershops (admin_id, name, owner, email, address, contact, status, business_permit_image, valid_id_image, latitude, longitude, dti_clearance, police_clearance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $status = 'active';
                            $stmt2->bind_param("issssssssdsss", $admin_id, $app['business_name'], $app['owner_name'], $app['business_email'], $app['business_address'], $app['contact'], $status, $app['business_permit_image'], $app['valid_id_image'], $app['latitude'], $app['longitude'], $app['dti_clearance'], $app['police_clearance']);
                            $stmt2->execute();
                            $shop_id = $stmt2->insert_id;
                            $stmt2->close();
                        } else {
                            $stmt2 = $conn->prepare("INSERT INTO barbershops (admin_id, name, owner, email, address, contact, status, business_permit_image, valid_id_image, dti_clearance, police_clearance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $status = 'active';
                            $stmt2->bind_param("isssssssssss", $admin_id, $app['business_name'], $app['owner_name'], $app['business_email'], $app['business_address'], $app['contact'], $status, $app['business_permit_image'], $app['valid_id_image'], $app['dti_clearance'], $app['police_clearance']);
                            $stmt2->execute();
                            $shop_id = $stmt2->insert_id;
                            $stmt2->close();
                        }
                        // Link admin and shop in admin_shop table
                        $stmt_link = $conn->prepare("INSERT INTO admin_shop (AdminID, shopID) VALUES (?, ?)");
                        $stmt_link->bind_param("ii", $admin_id, $shop_id);
                        $stmt_link->execute();
                        $stmt_link->close();
                        // Delete from application
                        $conn->query("DELETE FROM application WHERE shopID = $app_id");
                        // Redirect after successful creation and email
                        header("Location: approval.php");
                        exit();
                    } else {
                        // Create new admin account
                        $stmt_admin = $conn->prepare("INSERT INTO adminaccount (Username, Password, DateCreated) VALUES (?, ?, CURDATE())");
                        if (!$stmt_admin) {
                            die("Database error (adminaccount INSERT): " . $conn->error);
                        }
                        // Store only the hashed password in adminaccount
                        $stmt_admin->bind_param("ss", $admin_username, $admin_hashed_password);
                        $stmt_admin->execute();
                        $admin_id = $stmt_admin->insert_id;
                        $stmt_admin->close();
                        // Insert new barbershop for this admin, with dti and police clearance columns
                        if (isset($app['latitude']) && isset($app['longitude'])) {
                            $stmt2 = $conn->prepare("INSERT INTO barbershops (admin_id, name, owner, email, address, contact, status, business_permit_image, valid_id_image, latitude, longitude, dti_clearance, police_clearance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $status = 'active';
                            $stmt2->bind_param("issssssssdsss", $admin_id, $app['business_name'], $app['owner_name'], $app['business_email'], $app['business_address'], $app['contact'], $status, $app['business_permit_image'], $app['valid_id_image'], $app['latitude'], $app['longitude'], $app['dti_clearance'], $app['police_clearance']);
                            $stmt2->execute();
                            $shop_id = $stmt2->insert_id;
                            $stmt2->close();
                        } else {
                            $stmt2 = $conn->prepare("INSERT INTO barbershops (admin_id, name, owner, email, address, contact, status, business_permit_image, valid_id_image, dti_clearance, police_clearance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $status = 'active';
                            $stmt2->bind_param("issssssssssss", $admin_id, $app['business_name'], $app['owner_name'], $app['business_email'], $app['business_address'], $app['contact'], $status, $app['business_permit_image'], $app['valid_id_image'], $app['dti_clearance'], $app['police_clearance']);
                            $stmt2->execute();
                            $shop_id = $stmt2->insert_id;
                            $stmt2->close();
                        }
                        // Always link admin and shop in admin_shop table
                        $stmt_link = $conn->prepare("INSERT INTO admin_shop (AdminID, shopID) VALUES (?, ?)" );
                        $stmt_link->bind_param("ii", $admin_id, $shop_id);
                        $stmt_link->execute();
                        $stmt_link->close();
                        // Delete from application
                        $conn->query("DELETE FROM application WHERE shopID = $app_id");
                        // Redirect after successful creation and email
                        header("Location: approval.php");
                        exit();
                    }
                    $stmt_check->close();
                }
            } else {
                $error_message = "Application not found or query failed.";
            }
        } else {
            $error_message = "Incorrect password! Try again!";
        }
    } else {
        $error_message = "Super admin account not found!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Verification | Barberu</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href='Resources/icon.png'>
    <link rel="shortcut icon" type="image/png" href="Resources/brand.png">
</head>
<body>
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
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <div class="container">
    <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Super Admin Verification</h1>
                <form method="POST" action="check.php">
                    <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($app_id); ?>">
                    <div style="text-align:left;">
                        <label for="username">Super Admin Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div style="text-align:left;">
                        <label for="password">Super Admin Password</label>
                            <div style="position:relative;display:flex;align-items:center;">
                                <input type="password" id="password" name="password" required style="flex:1;">
                                <button type="button" id="togglePassword" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:0 6px;">
                                    <span id="toggleIcon" style="display:flex;align-items:center;">
                                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg>
                                        <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6 0-10-7-10-7a21.81 21.81 0 0 1 5.06-5.94"/><path d="M1 1l22 22"/><path d="M9.53 9.53A3 3 0 0 0 12 15a3 3 0 0 0 2.47-5.47"/><path d="M14.47 14.47A3 3 0 0 1 12 9a3 3 0 0 1-2.47 5.47"/></svg>
                                    </span>
                                </button>
                            </div>
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
                        <p style="color: red; text-align:left;"> <?php echo $error_message; ?> </p>
                    <?php } ?>
                    <button type="submit">Verify & Accept</button>
                </form>
            </div>
        </main>
    </div>
    <!-- Top-left back button -->
<div class="global-back-button">
    <a href="approval.php" class="btn-outline">← Back</a>
</div>

</body>
</html>