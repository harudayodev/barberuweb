<?php
session_start();
include 'Connection.php';
require_once 'send_email.php';

$error_message = "";
$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : (isset($_POST['app_id']) ? intval($_POST['app_id']) : 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $app_id = intval($_POST['app_id']);

    // Check admin credentials
    $stmt = $conn->prepare("SELECT AdminID, Password FROM adminaccount WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($adminID, $hashed_password);
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            // Get application details
            $result = $conn->query("SELECT * FROM employee_applications WHERE applicationID = $app_id");
            if ($result && $app = $result->fetch_assoc()) {
                $shopID = $app['shopID'];
                // Check if admin belongs to this shop
                $admin_shop_stmt = $conn->prepare("SELECT * FROM admin_shop WHERE AdminID = ? AND shopID = ?");
                $admin_shop_stmt->bind_param("ii", $adminID, $shopID);
                $admin_shop_stmt->execute();
                $admin_shop_stmt->store_result();
                if ($admin_shop_stmt->num_rows == 1) {
                    $applicant_email = $app['app_emailadd'];
                    $applicant_name = $app['app_firstname'] . ' ' . $app['app_lastname'];
                    // Check if employee already exists
                    $emp_check_stmt = $conn->prepare("SELECT EmployeeID FROM employee WHERE Email = ?");
                    $emp_check_stmt->bind_param("s", $applicant_email);
                    $emp_check_stmt->execute();
                    $emp_check_stmt->store_result();
                    $already_exists = $emp_check_stmt->num_rows > 0;
                    $emp_check_stmt->close();

                    // Insert into employee table if not exists
                    if (!$already_exists) {
                        $insert_stmt = $conn->prepare("INSERT INTO employee (FirstName, LastName, Resume, ContactNo, Address, Email, Password, Status, AdminID, shopID) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
                        $insert_stmt->bind_param(
                            "sssssssii",
                            $app['app_firstname'],
                            $app['app_lastname'],
                            $app['app_resume'],
                            $app['app_contact'],
                            $app['app_address'],
                            $applicant_email,
                            $app['app_contact'],
                            $adminID,
                            $shopID
                        );
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }

                    // Insert into appusers if not exists
                    $appusers_check_stmt = $conn->prepare("SELECT 1 FROM appusers WHERE email = ?");
                    $appusers_check_stmt->bind_param("s", $applicant_email);
                    $appusers_check_stmt->execute();
                    $appusers_check_stmt->store_result();
                    $user_exists = $appusers_check_stmt->num_rows > 0;
                    $appusers_check_stmt->close();
                    if (!$user_exists) {
                        $appusers_stmt = $conn->prepare("INSERT INTO appusers (firstname, lastname, email, password, profilephoto, role) VALUES (?, ?, ?, ?, '', 'employee')");
                        $appusers_stmt->bind_param(
                            "ssss",
                            $app['app_firstname'],
                            $app['app_lastname'],
                            $applicant_email,
                            $app['app_contact']
                        );
                        $appusers_stmt->execute();
                        $appusers_stmt->close();
                    }

                    // Always send acceptance email using send_employee_acceptance_email_2
                    $email_sent = false;
                    if (function_exists('send_employee_acceptance_email_2')) {
                        $email_sent = send_employee_acceptance_email_2($applicant_email, $applicant_name);
                        if (!$email_sent) {
                            error_log('Failed to send acceptance notification to ' . $applicant_email);
                            $error_message = 'Failed to send acceptance notification.';
                        }
                    } else {
                        error_log('send_employee_acceptance_email_2 function not found in send_email.php');
                        $error_message = 'Notification function not found.';
                    }

                    // If email sent, update application status and redirect
                    if ($email_sent) {
                        $stmt_upd = $conn->prepare("UPDATE employee_applications SET status = 'approved' WHERE applicationID = ?");
                        $stmt_upd->bind_param("i", $app_id);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                        // Delete the application from employee_applications table
                        $stmt_del = $conn->prepare("DELETE FROM employee_applications WHERE applicationID = ?");
                        $stmt_del->bind_param("i", $app_id);
                        $stmt_del->execute();
                        $stmt_del->close();
                        // Redirect to employees.php for the correct shop
                        header("Location: employees.php?shopID=" . urlencode($shopID) . "&success=1");
                        exit();
                    } else {
                        $error_message = !empty($error_message) ? $error_message : 'Failed to send acceptance notification.';
                    }
                } else {
                    $error_message = "Admin does not belong to this shop.";
                }
                $admin_shop_stmt->close();
            }
        } else {
            $error_message = "Incorrect password.";
        }
    } else {
        $error_message = "Invalid admin credentials.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Verification | Employee Application</title>
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
    </style>
    <div class="bg-shape shape1"></div>
    <div class="bg-shape shape2"></div>
    <div class="container">
    <main style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box;">
            <div class="login-box">
                <img src="Resources/ab.png" alt="Brand Logo">
                <h1>Admin Verification</h1>
                <form method="POST" action="adcheck.php">
                    <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($app_id); ?>">
                    <div style="text-align:left;">
                        <label for="username">Admin Email</label>
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
</body>
</html>
