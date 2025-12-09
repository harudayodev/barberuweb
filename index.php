<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID'])) {
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
$conn = new mysqli("localhost", "root", "", "barberu");
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
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['dashboard'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #e6f7ff;
        font-family: 'Poppins', sans-serif;
    }
    .main-header {
        background: linear-gradient(90deg, #00aaff 60%, #cceeff 100%);
        padding: 10px 0 8px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        position:fixed; top:0; left:0; width:100vw; z-index:1000;
    }
    .sidebar {
        background:#fff;
        border-radius:16px;
        box-shadow:0 2px 12px rgba(0,0,0,0.07);
        padding:32px 24px;
        min-width:220px;
        max-width:260px;
        flex:0 0 220px;
        margin-top:0;
    }
    .sidebar-header {
        display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%;
    }
    .sidebar-header img {
        height:60px; display:block; margin:0 auto;
    }
    .nav-links {
        list-style:none; padding:0; margin:32px 0 0 0; display:flex; flex-direction:column; gap:18px;
    }
    .nav-links li a {
        color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s;
        padding:6px 18px; border-radius:8px;
    }
    .nav-links li a.active, .nav-links li a:hover {
        background:#e6f7ff; color:#0077b6;
    }
    .nav-links .logout a { color:#dc3545; }
    .main-content {
        flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 32px 40px 32px; min-width:0;
        margin-top:0;
    }
    .queue-container {
        width:100%;
    }
    .dashboard-options {
        display: flex;
        gap: 32px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    .dashboard-card {
        background: #f7fbff;
        border-radius: 12px;
        box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        padding: 28px 24px 24px 24px;
        min-width: 260px;
        flex: 1 1 260px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        max-width: 400px;
    }
    .dashboard-card h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.2rem;
        font-weight: 700;
        color: #0077b6;
    }
    .dashboard-card p {
        margin-bottom: 18px;
        color: #333;
        font-size: 1rem;
    }
    .dashboard-card button {
        background: #00aaff;
        color: #fff;
        border: none;
        padding: 8px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        transition: background 0.2s;
    }
    .dashboard-card button:hover {
        background: #0077b6;
    }
    </style>
</head>
<body>
    <header class="main-header">
        <div style="max-width:1800px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;width:100%;">
            <div style="display:flex;align-items:center;gap:8px;">
                <img src="Resources/ab.png" alt="Logo" style="height:50px;width:50px;">
                <span style="font-size:2.3rem;font-weight:700;color:#fff;letter-spacing:1px;">BARBERU</span>
            </div>
        </div>
    </header>
    <div style="display:flex;max-width:1800px;width:98vw;margin:100px auto 0 auto;gap:32px;min-height:calc(100vh - 80px);">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="Resources/ab.png" alt="">
                <?php if (!empty($barbershopName)) { echo '<div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">' . htmlspecialchars($barbershopName) . '</div>'; } ?>
            </div>
            <ul class="nav-links">
                <li><a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>">Queue</a></li>
                <li><a href="management.php" class="<?= $current_page === 'management.php' ? 'active' : '' ?>">Management</a></li>
                <li><a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">Reports</a></li>
                <li><a href="options.php" class="<?= $current_page === 'options.php' ? 'active' : '' ?>">Options</a></li>
                <li class="logout" style="margin-top:32px;"><a href="logout.php">Logout</a></li>
            </ul>
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <header>
                <h1><?= strtoupper($translations['dashboard']) ?></h1>
            </header>
            <section class="dashboard-options">
                <div class="dashboard-card">
                    <h3><?= $translations['reports'] ?></h3>
                    <p><?= $translations['reports_description'] ?? 'Review application activity, trends, and reviews.' ?></p>
                    <button onclick="window.location.href='reports.php'"><?= $translations['view'] ?? 'View' ?></button>
                </div>
                <div class="dashboard-card">
                    <h3><?= $translations['management'] ?></h3>
                    <p><?= $translations['management_description'] ?? 'Monitor employees, users, haircuts, and available products.' ?></p>
                    <button onclick="window.location.href='management.php'"><?= $translations['view'] ?? 'View' ?></button>
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
