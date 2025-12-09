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

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch barbershop name for sidebar
$barbershopName = '';
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
    } elseif (isset($_SESSION['barberID'])) {
        $barberID = (int)$_SESSION['barberID'];
        $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barberID");
        if ($result && $row = $result->fetch_assoc()) {
            $barbershopName = $row['name'];
        }
    }
    // Fetch count of new queue notifications (items with status 'In Queue')
    $newQueueCount = 0;
    $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
    if ($result && $row = $result->fetch_assoc()) {
        $newQueueCount = (int)$row['cnt'];
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['reports'] ?> | Admin Dashboard</title>
    </head>
        <link rel="stylesheet" href="joinus.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
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
            <section style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:32px;width:100%;margin-top:16px;">
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-chart-line" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Income Overview</h2>
                    <p style="color:#555;font-size:1em;">Track daily, weekly, and monthly sales.</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="75" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button onclick="window.location.href='sales.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;">
                    <i class="fa-solid fa-users" style="font-size:2.5rem;color:#00aaff;"></i>
                    <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">User Reviews</h2>
                    <p style="color:#555;font-size:1em;">Analyze user reviews and suggestions.</p>
                    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                        <div class="circular-progress" data-value="40" data-max="100" style="margin-bottom:4px;"></div>
                    </div>
                    <button onclick="window.location.href='reviews.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                </div>
                    <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;display:none;">
                        <i class="fa-solid fa-box" style="font-size:2.5rem;color:#00aaff;"></i>
                        <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Product Inventory</h2>
                        <p style="color:#555;font-size:1em;">Monitor product stock and supplies.</p>
                        <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                            <div class="circular-progress" data-value="60" data-max="100" style="margin-bottom:4px;"></div>
                        </div>
                        <button onclick="window.location.href='products.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                    </div>
                    <div style="background:#cceeff;border-radius:12px;padding:24px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,0.04);width:100%;max-width:none;visibility:hidden;">
                        <i class="fa-solid fa-user-tie" style="font-size:2.5rem;color:#00aaff;"></i>
                        <h2 style="font-size:1.3rem;font-weight:600;margin:14px 0 7px 0;">Employee Performance</h2>
                        <p style="color:#555;font-size:1em;">Review employee performance metrics.</p>
                        <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:8px;">
                            <div class="circular-progress" data-value="85" data-max="100" style="margin-bottom:4px;"></div>
                        </div>
                        <button onclick="window.location.href='employees.php'" style="background:#00aaff;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:500;cursor:pointer;">View</button>
                    </div>
            </section>
        </main>
    </div>
    <style>
    .circular-progress {
    visibility: hidden !important;
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
    </script>
</body>
</html>
