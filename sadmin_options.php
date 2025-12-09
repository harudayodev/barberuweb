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

$lang = 'en';
$translations = include("languages/{$lang}.php");
$current_page = basename($_SERVER['PHP_SELF']);

$change_pass_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_SESSION['sadminID'])) {
        $change_pass_message = 'You must be logged in as a super admin to change your password.';
    } else {
        $sadminID = (int)$_SESSION['sadminID'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        require_once "Connection.php";
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $result = $conn->query("SELECT Password FROM sadminaccount WHERE SAdminID = $sadminID");
        if ($result && $row = $result->fetch_assoc()) {
            if (!password_verify($current_password, $row['Password'])) {
                $change_pass_message = 'Current password is incorrect!';
            } elseif (strlen($new_password) < 8) {
                $change_pass_message = 'New password must be at least 8 characters long!';
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $change_pass_message = 'New password must contain at least one uppercase letter!';
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $change_pass_message = 'New password must contain at least one lowercase letter!';
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $change_pass_message = 'New password must contain at least one number!';
            } elseif ($new_password !== $confirm_password) {
                $change_pass_message = 'New passwords do not match!';
            } else {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                if ($conn->query("UPDATE sadminaccount SET Password = '$hashed_new_password' WHERE SAdminID = $sadminID")) {
                    $change_pass_message = 'Password changed successfully!';
                    // Send email notification to super admin
                    $result_email = $conn->query("SELECT Username FROM sadminaccount WHERE SAdminID = $sadminID");
                    if ($result_email && $row_email = $result_email->fetch_assoc()) {
                        require_once "send_email.php";
                        send_sadmin_password_change_success($row_email['Username'], 'Super Admin');
                    }
                } else {
                    $change_pass_message = 'Error updating password.';
                }
            }
        } else {
            $change_pass_message = 'Super admin not found.';
        }
        $conn->close();
    }
}
?>


<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['options'] ?> | Super Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="background-color: #e6f7ff; font-family: 'Poppins', sans-serif;">
    <header class="main-header" style="background: linear-gradient(90deg, #00aaff 60%, #cceeff 100%); padding: 10px 0 8px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position:fixed; top:0; left:0; width:100vw; z-index:1000;">
            <div style="max-width:1800px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;width:100%;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <img src="Resources/ab.png" alt="Logo" style="height:50px;width:50px;">
                    <span style="font-size:2.3rem;font-weight:700;color:#fff;letter-spacing:1px;">BARBERU</span>
                </div>
            </div>
    </header>
    <div style="display:flex;max-width:1800px;width:98vw;margin:100px auto 0 auto;gap:32px;">
    <aside style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:32px 24px;min-width:220px;max-width:260px;flex:0 0 220px;display:flex;flex-direction:column;align-items:stretch;">
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;width:100%;">
                <img src="Resources/cd.png" alt="" style="height:60px;display:block;margin:0 auto;" >
                <div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">Super Admin</div>
            </div>
            <ul class="nav-links">
            <style>
                .nav-links {
                    list-style:none; padding:0; margin:32px 0 0 0; display:flex; flex-direction:column; gap:6px;
                }
                .nav-links li a {
                    color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s;
                    padding:6px 18px; border-radius:8px;
                    display:block;
                }
                .nav-links li a.active, .nav-links li a:hover {
                    background:#e6f7ff; color:#0077b6;
                }
                .nav-links .logout a { color:#dc3545; }
            </style>
                <li><a href="sadmin.php" class="<?= $current_page === 'sadmin.php' ? 'active' : '' ?>">
                    <i class="fa fa-tachometer-alt" style="margin-right:10px;"></i>Dashboard
                </a></li>
                <li><a href="sadmin_options.php" class="<?= $current_page === 'sadmin_options.php' ? 'active' : '' ?>">
                    <i class="fa fa-cogs" style="margin-right:10px;"></i>Options
                </a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php">
                    <i class="fa fa-sign-out-alt" style="margin-right:10px;"></i>Logout
                </a></li>
            </ul>
        </aside>
        <main style="flex:1;background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:32px 32px 40px 32px;min-width:0;">
            <section style="width:100%;max-width:1400px;margin:0 auto;">
                <div style="display:flex;flex-direction:column;gap:32px;width:100%;align-items:center;">
                    <div style="width:98%;max-width:1300px;background:#e6f7ff;border-radius:18px;padding:36px 36px 24px 36px;box-shadow:0 1px 10px rgba(0,0,0,0.04);">
                        <h2 style="font-size:1.2rem;font-weight:600;margin-bottom:10px;">Change Password</h2>
                        <form method="POST" action="sadmin_options.php" style="display: flex; flex-direction: column; gap: 18px; align-items: flex-start;">
                                <!-- Current Password -->
                                <div style="display: flex; flex-direction: column; gap: 6px; width:320px;max-width:90vw;">
                                    <label for="current_password">Current Password:</label>
                                    <div style="position: relative;">
                                        <input type="password" id="current_password" name="current_password" required style="width:100%;padding:8px 38px 8px 10px;border-radius:6px;border:1px solid #b3e0ff;">
                                        <span class="toggle-password" data-target="current_password" style="position:absolute; top: 50%; right: 10px; transform: translateY(-50%); width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.3em;color:#00aaff;z-index:2;"><i class="fa-regular fa-eye"></i></span>
                                    </div>
                                </div>
                                <!-- New Password -->
                                <div style="display: flex; flex-direction: column; gap: 6px; width:320px;max-width:90vw;">
                                    <label for="new_password">New Password:</label>
                                    <div style="position: relative;">
                                        <input type="password" id="new_password" name="new_password" required style="width:100%;padding:8px 38px 8px 10px;border-radius:6px;border:1px solid #b3e0ff;">
                                        <span class="toggle-password" data-target="new_password" style="position:absolute; top: 50%; right: 10px; transform: translateY(-50%); width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.3em;color:#00aaff;z-index:2;"><i class="fa-regular fa-eye"></i></span>
                                    </div>
                                </div>
                                <!-- Confirm New Password -->
                                <div style="display: flex; flex-direction: column; gap: 6px; width:320px;max-width:90vw;">
                                    <label for="confirm_password">Confirm New Password:</label>
                                    <div style="position: relative;">
                                        <input type="password" id="confirm_password" name="confirm_password" required style="width:100%;padding:8px 38px 8px 10px;border-radius:6px;border:1px solid #b3e0ff;">
                                        <span class="toggle-password" data-target="confirm_password" style="position:absolute; top: 50%; right: 10px; transform: translateY(-50%); width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.3em;color:#00aaff;z-index:2;"><i class="fa-regular fa-eye"></i></span>
                                    </div>
                                </div>
                            <button type="submit" name="change_password" style="margin-top: 12px;background:#00aaff;color:#fff;border:none;padding:10px 0;width:320px;max-width:90vw;border-radius:8px;font-weight:500;cursor:pointer;">Change Password</button>
                        </form>
                        <?php if (!empty($change_pass_message)) {
                            $is_success = strpos(strtolower($change_pass_message), 'success') !== false;
                            echo '<p style="color: ' . ($is_success ? 'green' : 'red') . ';">' . $change_pass_message . '</p>';
                        } ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        // Show/hide password toggles
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const targetId = toggle.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = toggle.querySelector('i');
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            });
        });
    });
    </script>
</body>
</html>