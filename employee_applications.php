<?php
session_start();
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

$lang = $_SESSION['language'] ?? 'en';
$translations = include("languages/{$lang}.php");

require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch barbershop name for sidebar
$barbershopName = '';
$shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : null;
if ($shopID) {
    $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $shopID");
    if ($result && $row = $result->fetch_assoc()) {
        $barbershopName = $row['name'];
    }
}

// Handle Decline action
require_once "send_email.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'decline' && isset($_POST['app_id'])) {
    $decline_id = (int)$_POST['app_id'];
    // Get applicant info
    $decline_sql = "SELECT app_emailadd, app_firstname, app_lastname FROM employee_applications WHERE applicationID = $decline_id LIMIT 1";
    $decline_result = $conn->query($decline_sql);
    if ($decline_result && $decline_result->num_rows > 0) {
        $decline_row = $decline_result->fetch_assoc();
        $to = $decline_row['app_emailadd'];
        $name = $decline_row['app_firstname'] . ' ' . $decline_row['app_lastname'];
        // Update status
        $conn->query("UPDATE employee_applications SET status = 'declined' WHERE applicationID = $decline_id");
        // Send rejection email
        send_employee_rejection_email($to, $name);
        // Redirect to avoid resubmission
        header("Location: employee_applications.php?declined=1");
        exit();
    }
}

// Search/filter logic
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$where = [];
if ($shopID) {
    $where[] = "shopID = $shopID";
}
if ($search) {
    $where[] = "(app_firstname LIKE '%$search%' OR app_lastname LIKE '%$search%' OR app_emailadd LIKE '%$search%' OR app_contact LIKE '%$search%' OR app_address LIKE '%$search%')";
}
if ($status_filter) {
    $where[] = "status = '$status_filter'";
} else {
    $where[] = "status = 'pending'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "SELECT * FROM employee_applications $whereSql ORDER BY applicationID DESC";
$result = $conn->query($sql);
$applications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['application_approval'] ?> | Admin Dashboard</title>
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
        border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,170,255,0.07);
        transition: background 0.2s, color 0.2s, border-radius 0.2s;
    }
    .nav-links .logout a { color:#dc3545; }
    .main-content {
        flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:32px 32px 40px 32px; min-width:0;
        margin-top:0;
    }
    .queue-container {
        width:100%;
    }
    .queue-list {
        background:#f7fbff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.04); padding:24px 18px; margin-top:18px;
    }
    table {
        width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.04);
    }
    th, td {
        padding:10px 8px; text-align:center; border-bottom:1px solid #e0e0e0;
    }
    th {
        background:#e6f7ff; color:#0077b6; font-weight:600;
    }
    tr:last-child td { border-bottom:none; }
    /* Styles for action buttons */
    .actions-container {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
    }
    .btn {
        color: white !important;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        text-decoration: none;
        font-size: 14px;
        display: inline-block;
        text-align: center;
        transition: background-color 0.2s;
    }
    .btn-accept {
        background-color: #28a745; /* Green */
    }
    .btn-accept:hover {
        background-color: #218838;
    }
    .btn-decline {
        background-color: #dc3545; /* Red */
    }
    .btn-decline:hover {
        background-color: #c82333;
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
                <img src="Resources/cd.png" alt="">
                <?php if (!empty($barbershopName)) { echo '<div style="font-weight:600;margin-top:10px;color:#00aaff;font-size:1.1em;">' . htmlspecialchars($barbershopName) . '</div>'; } ?>
            </div>
            <?php
            $newQueueCount = 0;
            $adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : 0;
            $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
            $result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
            if ($result && $row = $result->fetch_assoc()) {
                $newQueueCount = (int)$row['cnt'];
            }
            ?>
            <ul class="nav-links">
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
        </aside>
        <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['application_list'] ?>
                        </h2>
                        <form method="get" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                            <input type="text" name="search" placeholder="<?= $translations['search_placeholder'] ?>" value="<?= htmlspecialchars($search) ?>" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                            <select name="status" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="">All Status</option>
                                <option value="<?= $translations['pending'] ?>" <?= $status_filter === $translations['pending'] ? 'selected' : '' ?>><?= $translations['pending'] ?></option>
                                <option value="<?= $translations['declined'] ?>" <?= $status_filter === $translations['declined'] ? 'selected' : '' ?>><?= $translations['declined'] ?></option>
                            </select>
                            <button type="submit" class="search-button" style="background: #28a745; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">Search</button>
                            <button type="button" class="clear-button" onclick="window.location.href='approval.php'" style="background: #dc3545; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 16px;">Clear</button>
                        </form>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Email</th>
                                <th>Resume</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($applications) > 0): ?>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($app['applicationID']) ?></td>
                                        <td><?= htmlspecialchars($app['app_firstname']) ?></td>
                                        <td><?= htmlspecialchars($app['app_lastname']) ?></td>
                                        <td><?= htmlspecialchars($app['app_contact']) ?></td>
                                        <td><?= htmlspecialchars($app['app_address']) ?></td>
                                        <td><?= htmlspecialchars($app['app_emailadd']) ?></td>
                                        <td>
                                            <?php if (!empty($app['app_resume'])): ?>
                                                <a href="<?= htmlspecialchars($app['app_resume']) ?>" target="_blank">View</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($app['status']) ?></td>
                                        <td style="min-width:140px;">
                                            <?php if (strtolower($app['status']) === 'pending'): ?>
                                                <div class="actions-container">
                                                    <a href="adcheck.php?app_id=<?= $app['applicationID'] ?>" class="btn btn-accept">Accept</a>
                                                    <form method="POST" style="display:inline-block; margin:0;">
                                                        <input type="hidden" name="app_id" value="<?= $app['applicationID'] ?>">
                                                        <input type="hidden" name="action" value="decline">
                                                        <button type="submit" class="btn btn-decline">Decline</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" style="text-align:center;">No pending applications</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
