<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch barbershop name for sidebar
// Fetch barbershop name and counts for dashboard
$barbershopName = '';
$employeeCount = 0;
$haircutCount = 0;
$productCount = 0;
$applicationCount = 0;
// --- DB connection and counts ---
require_once "Connection.php";
if ($conn->connect_error) {
    $barbershopName = '';
} else {
    if (isset($_SESSION['barbershopID'])) {
        $barbershopID = (int)$_SESSION['barbershopID'];
        $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barbershopID");
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
        // Count employees (exclude archived)
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM employee WHERE shopID = $barbershopID AND Status != 'archived'");
        if ($res && $row = $res->fetch_assoc()) { $employeeCount = $row['cnt']; }
        // Count haircuts (exclude archived)
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM haircut WHERE shopID = $barbershopID AND Status != 'archived'");
        if ($res && $row = $res->fetch_assoc()) { $haircutCount = $row['cnt']; }
        // Count products (exclude archived) and check for low stock
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE shopID = $barbershopID AND Status != 'archived'");
        if ($res && $row = $res->fetch_assoc()) { $productCount = $row['cnt']; }
        $lowStock = false;
        $res = $conn->query("SELECT Quantity, CriticalLevel FROM inventory WHERE shopID = $barbershopID AND Status != 'archived'");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                if ($row['Quantity'] <= $row['CriticalLevel']) {
                    $lowStock = true;
                    break;
                }
            }
        }
        // Count employee applications
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM employee_applications WHERE shopID = $barbershopID");
        if ($res && $row = $res->fetch_assoc()) { $applicationCount = $row['cnt']; }
    }
    // --- Barbershop count for admin ---
    $barbershopCount = 0;
    if (isset($_SESSION['adminID'])) {
        $adminID = (int)$_SESSION['adminID'];
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM barbershops WHERE admin_id = $adminID AND status != 'archived'");
        if ($res && $row = $res->fetch_assoc()) {
            $barbershopCount = (int)$row['cnt'];
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['management'] ?> | Admin Dashboard</title>
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
                <?php
                // Notification logic for queue count
                $showQueueNotification = isset($_SESSION['notifications_enabled']) ? $_SESSION['notifications_enabled'] : false;
                $queueCount = 0;
                if ($showQueueNotification) {
                    require_once "Connection.php";
                    // Use correct variable names from Connection.php
                    $notifConn = new mysqli($host, $username, $password, $database);
                    if (!$notifConn->connect_error) {
                        $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
                        $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
                        if ($adminID && $barbershopID) {
                            $result = $notifConn->query("SELECT COUNT(*) as cnt FROM queue WHERE adminID = $adminID AND shopID = $barbershopID");
                            if ($result && $row = $result->fetch_assoc()) {
                                $queueCount = (int)$row['cnt'];
                            }
                            if ($result) $result->free();
                        }
                        $notifConn->close();
                    }
                }
                ?>
                <li style="position:relative;display:flex;align-items:center;">
                    <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-list"></i> Queue
                        <?php if ($showQueueNotification && $queueCount > 0): ?>
                            <span style="min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:#dc3545;color:#fff;border-radius:50%;font-size:1em;font-weight:600;box-shadow:0 2px 8px rgba(220,53,69,0.12);margin-left:8px;"> <?= $queueCount ?> </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="management.php" class="<?= $current_page === 'management.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Management</a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li><a href="options.php" class="<?= $current_page === 'options.php' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> Options</a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            </ul>
        </aside>
        <main style="flex:1;background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:32px 32px 40px 32px;min-width:0;">
            <section style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:32px;width:100%;">
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-user-tie" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Employees</h2>
                    <p style="color:#555;font-size:1em;">Manage staff & schedules</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $employeeCount ?>" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button onclick="window.location.href='employees.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <!-- Barbershops Card -->
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-store" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Barbershops</h2>
                    <p style="color:#555;font-size:1em;">Manage your barbershop branches</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= isset($barbershopCount) ? $barbershopCount : 0 ?>" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button id="barbershopViewBtn" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-cut" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Services</h2>
                    <p style="color:#555;font-size:1em;">Manage services & prices</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $haircutCount ?>" data-max="100" style="margin-bottom:4px;"></div>
                        <!-- Removed text counter for Haircuts -->
                    </div>
                    <button onclick="window.location.href='haircuts.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-box" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Products</h2>
                    <p style="color:#555;font-size:1em;">Stock & inventory</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $productCount ?>" data-max="100" style="margin-bottom:4px;" data-color="<?= $lowStock ? '#dc3545' : '#00aaff' ?>"></div>
                        <!-- Removed text counter for Products -->
                        <?php if ($lowStock): ?>
                            <span style="display:block; font-size:1em; color:#dc3545; margin-top:2px;">A product is at critical quantity!</span>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.location.href='products.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Product Usage History</h2>
                    <p style="color:#555;font-size:1em;margin-bottom:75px;">View history of used products</p>
                    <button onclick="window.location.href='history.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-user-plus" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Applications</h2>
                    <p style="color:#555;font-size:1em;">Employee applications</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="<?= $applicationCount ?>" data-max="100" style="margin-bottom:4px;"></div>
                        <!-- Removed text counter for Applications -->
                    </div>
                    <button onclick="window.location.href='employee_applications.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
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

    /* --- Card Design Improvements --- */
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
    /* Optional: Add a subtle gradient border on hover */
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
    // Barbershop View Button logic
    document.addEventListener('DOMContentLoaded', function() {
        var barbershopViewBtn = document.getElementById('barbershopViewBtn');
        if (barbershopViewBtn) {
            barbershopViewBtn.addEventListener('click', function() {
                var barbershopCount = <?= isset($barbershopCount) ? $barbershopCount : 0 ?>;
                if (barbershopCount > 1) {
                    window.location.href = 'barbershop_select.php';
                } else {
                    alert('You only have 1 registered barbershop.');
                }
            });
        }
    });
    // Circular progress bar rendering
    document.addEventListener('DOMContentLoaded', function() {
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
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
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
