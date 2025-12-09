<?php
session_start();
// Check if user is not logged in
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}
if (!isset($_SESSION['notifications_enabled'])) {
    $_SESSION['notifications_enabled'] = true;
}
// Handle notification checkbox POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notifications_toggle'])) {
    $_SESSION['notifications_enabled'] = isset($_POST['notifications_enabled']) && $_POST['notifications_enabled'] == '1';
}
if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
    header("Location: options.php");
    exit();
}
$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize variables
$change_pass_message = '';
$barbershopName = '';
$newQueueCount = 0;

// Centralized DB Connection
require_once "Connection.php";

if ($conn && !$conn->connect_error) {
    // Handle change password for adminaccounts, only if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        if (!isset($_SESSION['adminID'])) {
            $change_pass_message = 'You must be logged in as an admin to change your password.';
        } else {
            $adminID = (int)$_SESSION['adminID'];
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            $stmt = $conn->prepare("SELECT Username, Password FROM adminaccount WHERE AdminID = ?");
            $stmt->bind_param("i", $adminID);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $row = $result->fetch_assoc()) {
                if (!password_verify($current_password, $row['Password'])) {
                    $change_pass_message = 'Current password is incorrect!';
                } elseif (strlen($new_password) < 8) {
                    $change_pass_message = 'New password must be at least 8 characters long!';
                } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                    $change_pass_message = 'New password must contain at least one uppercase letter, one lowercase letter, and one number!';
                } elseif ($new_password !== $confirm_password) {
                    $change_pass_message = 'New passwords do not match!';
                } else {
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE adminaccount SET Password = ? WHERE AdminID = ?");
                    $update_stmt->bind_param("si", $hashed_new_password, $adminID);

                    if ($update_stmt->execute()) {
                        $change_pass_message = 'Password changed successfully!';
                        require_once "send_email.php";
                        // Fetch the correct email from the barbershops table
                        $email = '';
                        if (isset($_SESSION['barbershopID'])) {
                            $barbershopID = (int)$_SESSION['barbershopID'];
                            $email_stmt = $conn->prepare("SELECT email, name FROM barbershops WHERE shopID = ? LIMIT 1");
                            $email_stmt->bind_param("i", $barbershopID);
                            $email_stmt->execute();
                            $email_result = $email_stmt->get_result();
                            if ($email_result && $email_row = $email_result->fetch_assoc()) {
                                $email = $email_row['email'];
                                $business_name = $email_row['name'];
                            } else {
                                $email = $row['Username']; // fallback
                                $business_name = $row['Username'];
                            }
                            $email_stmt->close();
                        } else {
                            $email = $row['Username']; // fallback
                            $business_name = $row['Username'];
                        }
                        send_password_change_notification($email, $business_name);
                    } else {
                        $change_pass_message = 'Error updating password.';
                    }
                    $update_stmt->close();
                }
            } else {
                $change_pass_message = 'Admin not found.';
            }
            $stmt->close();
        }
    }

    // Fetch barbershop name for sidebar
    if (isset($_SESSION['barbershopID'])) {
        $barbershopID = (int)$_SESSION['barbershopID'];
        $stmt = $conn->prepare("SELECT name FROM barbershops WHERE shopID = ?");
        $stmt->bind_param("i", $barbershopID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
        $stmt->close();
    } elseif (isset($_SESSION['barberID'])) {
        $barberID = (int)$_SESSION['barberID'];
        // Note: This query seems to compare a shopID with a barberID, which may be incorrect.
        // You might need to adjust the logic to correctly find the barber's associated shop.
        $stmt = $conn->prepare("SELECT name FROM barbershops WHERE shopID = ?");
        $stmt->bind_param("i", $barberID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
        $stmt->close();
    }

    // Fetch new queue count
    $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = ? AND shopID = ?");
    $stmt->bind_param("ii", $adminID, $barbershopID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $newQueueCount = (int)$row['cnt'];
    }
    $stmt->close();

    // Close the connection at the very end of the script's DB operations
    $conn->close();
} else {
    // Optional: handle the case where the initial connection fails
    die("Database connection failed.");
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['options'] ?> | Admin Dashboard</title>
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
                <?php if (!empty($barbershopName)) { echo '<div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">' . htmlspecialchars($barbershopName) . '</div>'; } ?>
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
                <li style="position:relative;display:flex;align-items:center;">
                    <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-list"></i> Queue
                        <?php if ((isset($_SESSION['notifications_enabled']) && $_SESSION['notifications_enabled']) && isset($newQueueCount) && $newQueueCount > 0): ?>
                            <span style="min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:#dc3545;color:#fff;border-radius:50%;font-size:1em;font-weight:600;box-shadow:0 2px 8px rgba(220,53,69,0.12);margin-left:8px;"> <?= $newQueueCount ?> </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="management.php" class="<?= $current_page === 'management.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Management</a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li><a href="options.php" class="<?= $current_page === 'options.php' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> Options</a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            </ul>
            <style>
            .queue-badge {
                background: #ff3b3b;
                color: #fff;
                font-size: 0.85em;
                font-weight: 700;
                border-radius: 12px;
                padding: 2px 8px;
                margin-left: 6px;
                vertical-align: middle;
                display: inline-block;
            }
            </style>
        </aside>
        <main style="flex:1;background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:32px 32px 40px 32px;min-width:0;">
            <section style="width:100%;max-width:1400px;margin:0 auto;">
                <div style="display:flex;flex-direction:column;gap:32px;width:100%;align-items:center;">
                        <div style="width:98%;max-width:1300px;background:#e6f7ff;border-radius:18px;padding:36px 36px 24px 36px;box-shadow:0 1px 10px rgba(0,0,0,0.04);">
                            <h2 style="font-size:1.2rem;font-weight:600;margin-bottom:10px;">Notifications</h2>
                            <form method="POST" action="options.php">
                                <label for="notifications">Notifications</label>
                                <input type="hidden" name="notifications_toggle" value="1">
                                <input type="checkbox" id="notifications" name="notifications_enabled" value="1" <?= (isset($_SESSION['notifications_enabled']) && $_SESSION['notifications_enabled']) ? 'checked' : '' ?> onchange="this.form.submit()">
                            </form>
                        </div>
                        <div style="width:98%;max-width:1300px;background:#e6f7ff;border-radius:18px;padding:36px 36px 24px 36px;box-shadow:0 1px 10px rgba(0,0,0,0.04);">
                            <h2 style="font-size:1.2rem;font-weight:600;margin-bottom:10px;">Change Password</h2>
                            <form method="POST" action="options.php" style="display: flex; flex-direction: column; gap: 18px; align-items: flex-start;">
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
            </section>
        </main>
    </div>
    <style>
    body.dark-mode {
        background: #23272f !important;
        color: #f5f6fa !important;
    }
    body.dark-mode .main-header {
        background: linear-gradient(90deg, #23272f 60%, #3a3f4b 100%) !important;
    }
    body.dark-mode .sidebar, body.dark-mode aside, body.dark-mode main, body.dark-mode section, body.dark-mode .queue-list, body.dark-mode .queue-form, body.dark-mode .option-item {
        background: #23272f !important;
        color: #f5f6fa !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.18) !important;
    }
    body.dark-mode .queue-list, body.dark-mode .queue-form, body.dark-mode .option-item {
        background: #23272f !important;
        color: #f5f6fa !important;
    }
    body.dark-mode input, body.dark-mode select, body.dark-mode button {
        background: #23272f !important;
        color: #f5f6fa !important;
        border-color: #444 !important;
    }
    body.dark-mode input[type="checkbox"] {
        accent-color: #00aaff;
    }
    body.dark-mode h2, body.dark-mode label, body.dark-mode .nav-links li a, body.dark-mode select, body.dark-mode option {
        color: #f5f6fa !important;
    }
    body.dark-mode .nav-links li a {
        color: #7ecfff !important;
    }
    body.dark-mode .nav-links li a.active, body.dark-mode .nav-links li a:hover {
        background: #23272f !important;
        color: #00aaff !important;
    }
    body.dark-mode .queue-list, body.dark-mode .queue-form, body.dark-mode .option-item {
        border: 1px solid #444 !important;
    }
    body.dark-mode select, body.dark-mode option {
        background: #23272f !important;
        color: #f5f6fa !important;
    }
    </style>
    <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        // Dark mode persistence
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
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
        // Language select: submit on change
        var langSelect = document.getElementById('language');
        if (langSelect) {
            langSelect.addEventListener('change', function() {
                if (langSelect.form) {
                    langSelect.form.submit();
                }
            });
        }
    });
    </script>
</body>
</html>
