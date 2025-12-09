
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

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Record number functions
$barbershopCount = 0;
$applicationCount = 0;
$adminCount = 0;
$employeeCount = 0;
require_once "Connection.php";
if (!$conn->connect_error) {
    // Count barbershops (exclude archived)
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM barbershops WHERE status != 'archived'");
    if ($res && $row = $res->fetch_assoc()) { $barbershopCount = $row['cnt']; }
    // Count business applications (exclude declined)
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM application WHERE LOWER(status) != 'declined'");
    if ($res && $row = $res->fetch_assoc()) { $applicationCount = $row['cnt']; }
    // Count admin accounts
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM adminaccount");
    if ($res && $row = $res->fetch_assoc()) { $adminCount = $row['cnt']; }
    // Count employees
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM employee");
    if ($res && $row = $res->fetch_assoc()) { $employeeCount = $row['cnt']; }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['super_admin_panel'] ?> | Super Admin Dashboard</title>
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
    <div style="display:flex;max-width:1800px;width:98vw;margin:100px auto 0 auto;gap:32px;min-height:80vh;align-items:stretch;">
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
                    padding:6px 18px; border-radius:8px; display:block;
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
    <main style="flex:1;background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:32px 32px 40px 32px;min-width:0;display:flex;flex-direction:column;justify-content:stretch;min-height:80vh;">
            <section style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:32px;width:100%;flex:1;align-content:start;">
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-store" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Registered Barbershops</h2>
                    <p style="color:#555;font-size:1em;">View all barbershops</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $barbershopCount ?>" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button onclick="window.location.href='barbershop_list.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-inbox" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Business Applications</h2>
                    <p style="color:#555;font-size:1em;">Review business applications</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $applicationCount ?>" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button onclick="window.location.href='approval.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <!-- Admin Accounts and Employees cards removed as requested -->
            </section>
        </main>
    </div>
    <style>
        .circular-progress {
            width: 60px;
            height: 60px;
            position: relative;
            display: inline-block;
        }
        .circular-progress svg {
            transform: rotate(-90deg);
        }
        .circular-progress .progress-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.1em;
            font-weight: 600;
            color: #007bff;
        }
        /* --- Card Design Improvements (from management.php) --- */
        section[style*="display:grid"] > div {
            transition: box-shadow 0.25s, transform 0.22s, background 0.22s;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        section[style*="display:grid"] > div:hover {
            box-shadow: 0 6px 24px rgba(0,170,255,0.13), 0 1.5px 8px rgba(0,0,0,0.07);
            transform: translateY(-4px) scale(1.03);
            background: linear-gradient(120deg, #e6f7ff 60%, #cceeff 100%);
            z-index: 2;
        }
        section[style*="display:grid"] > div:hover i {
            color: #0077b6;
            transform: scale(1.13) rotate(-6deg);
            transition: color 0.22s, transform 0.22s;
        }
        section[style*="display:grid"] > div i {
            transition: color 0.22s, transform 0.22s;
        }
        section[style*="display:grid"] > div button {
            transition: background 0.18s, box-shadow 0.18s, color 0.18s, transform 0.18s;
            box-shadow: 0 1px 4px rgba(0,170,255,0.09);
        }
        section[style*="display:grid"] > div button:hover {
            background: #0077b6;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,170,255,0.18);
            transform: scale(1.06);
        }
        section[style*="display:grid"] > div:active {
            transform: scale(0.99);
        }
        section[style*="display:grid"] > div::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 12px;
            pointer-events: none;
            transition: opacity 0.22s;
            opacity: 0;
            border: 2px solid #00aaff;
        }
        section[style*="display:grid"] > div:hover::before {
            opacity: 0.18;
        }
        /* --- End Card Design Improvements --- */
    </style>
    <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        // Circular progress bar rendering
        document.querySelectorAll('.circular-progress').forEach(function(el) {
            var value = parseInt(el.getAttribute('data-value')) || 0;
            var max = parseInt(el.getAttribute('data-max')) || 100;
            var percent = Math.min(100, Math.round((value / max) * 100));
            var color = el.getAttribute('data-color') || '#00aaff';
            var radius = 26;
            var stroke = 7;
            var circ = 2 * Math.PI * radius;
            var progress = (percent / 100) * circ;
            el.innerHTML = `
                <svg width="60" height="60">
                    <circle cx="30" cy="30" r="${radius}" stroke="#e0e0e0" stroke-width="${stroke}" fill="none" />
                    <circle cx="30" cy="30" r="${radius}" stroke="${color}" stroke-width="${stroke}" fill="none" stroke-dasharray="${circ}" stroke-dashoffset="${circ - progress}" stroke-linecap="round" />
                </svg>
                <span class="progress-value">${value}</span>
            `;
        });
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
        var toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = localStorage.getItem('darkMode') === 'enabled';
            toggle.addEventListener('change', function() {
                if (toggle.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        }
    });
    </script>
</body>
</html>

