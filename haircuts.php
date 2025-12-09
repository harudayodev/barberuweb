<?php
session_start();
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

// Database connection
require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$AdminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : null;
$shopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : null;

// Fetch barbershop name for sidebar
$barbershopName = '';
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
}
// Fetch employees only for the logged-in shop
// Fetch employees for the current active shop only
$employees = [];
if (isset($_SESSION['barbershopID'])) {
    $shopID = (int)$_SESSION['barbershopID'];

    $resultEmployees = $conn->query("
        SELECT e.EmployeeID, CONCAT(e.FirstName, ' ', e.LastName) AS FullName
        FROM employee e
        INNER JOIN barbershops b ON e.shopID = b.shopID
        WHERE e.shopID = $shopID
        AND e.Status = 'active'
        AND b.Status = 'active'
    ");

    if ($resultEmployees && $resultEmployees->num_rows > 0) {
        while ($e = $resultEmployees->fetch_assoc()) {
            $employees[] = $e;
        }
    }
}



// Handle add haircut
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['hc_name']) &&
    $AdminID &&
    $shopID
) {
    $name = $conn->real_escape_string($_POST['hc_name']);
    $service = $conn->real_escape_string($_POST['hc_service']);
    $price = (float)$_POST['hc_price'];
    $barbers = isset($_POST['hc_employee']) ? $_POST['hc_employee'] : []; // array of selected barbers

    // Insert haircut (no EmployeeID stored directly now)
    $sql = "INSERT INTO haircut (Name, Service, Price, Status, AdminID, shopID)
            VALUES ('$name', '$service', $price, 'active', $AdminID, $shopID)";
    
    if ($conn->query($sql)) {
        $haircutID = $conn->insert_id;

        // Link selected barbers to this haircut
        foreach ($barbers as $barberID) {
            $barberID = (int)$barberID;
            $conn->query("
                INSERT INTO haircut_barbers (HaircutID, EmployeeID)
                VALUES ($haircutID, $barberID)
            ");
        }

        header("Location: haircuts.php");
        exit();
    } else {
        die('Error adding haircut: ' . $conn->error);
    }
}

// Handle edit haircut
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['edit_haircut_id']) &&
    $AdminID &&
    $shopID
) {
    $haircutID = (int)$_POST['edit_haircut_id'];
    $name = $conn->real_escape_string($_POST['edit_hc_name']);
    $service = $conn->real_escape_string($_POST['edit_hc_service']);
    $price = (float)$_POST['edit_hc_price'];
    $barbers = isset($_POST['edit_hc_employee']) ? $_POST['edit_hc_employee'] : [];
    $status = isset($_POST['edit_hc_status']) && $_POST['edit_hc_status'] === 'unavailable' ? 'unavailable' : 'active';

    // Update haircut info
    $sql = "UPDATE haircut 
            SET Name = '$name', Service = '$service', Price = $price, Status = '$status'
            WHERE HaircutID = $haircutID AND shopID = $shopID";
    if (!$conn->query($sql)) {
        die('Error updating haircut: ' . $conn->error);
    }

    // Update assigned barbers
    $conn->query("DELETE FROM haircut_barbers WHERE HaircutID = $haircutID");
    foreach ($barbers as $barberID) {
        $barberID = (int)$barberID;
        $conn->query("
            INSERT INTO haircut_barbers (HaircutID, EmployeeID)
            VALUES ($haircutID, $barberID)
        ");
    }

    header("Location: haircuts.php");
    exit();
}

// Handle toggle haircut status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_haircut_id']) && $AdminID && $shopID) {
    $haircutID = (int)$_POST['toggle_haircut_id'];
    $newStatus = ($_POST['new_status'] === 'active') ? 'active' : 'unavailable';
    $conn->query("UPDATE haircut SET Status = '$newStatus' WHERE HaircutID = $haircutID AND shopID = $shopID");
    header("Location: haircuts.php");
    exit();
}

// Handle archive haircut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_haircut_id']) && $AdminID && $shopID) {
    $haircutID = (int)$_POST['archive_haircut_id'];
    $conn->query("UPDATE haircut SET Status = 'archived' WHERE HaircutID = $haircutID AND shopID = $shopID");
    header("Location: haircuts.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['haircuts'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
    /* Modal styles for Add Haircut */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background-color: #fff;
        padding: 28px 36px 24px 36px;
        border: none;
        width: 420px;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
        margin: 0;
    }
    .close {
        color: #aaa;
        position: absolute;
        right: 16px;
        top: 8px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }
    .modal-content h2 {
        margin-top: 0;
        margin-bottom: 18px;
        font-size: 1.4rem;
        font-weight: 700;
        text-align: center;
    }
    .modal-content form {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 12px 10px;
        align-items: center;
        width: 100%;
    }
    .modal-content form label {
        text-align: right;
        font-weight: 500;
        font-size: 0.98rem;
        margin-right: 4px;
    }
    .modal-content form input[type="text"],
    .modal-content form input[type="number"],
    .modal-content form select {
        width: 100%;
        padding: 7px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 2px;
        margin-left: 2px;
        box-sizing: border-box;
        grid-column: 2 / 3;
    }
    .modal-content form button[type="submit"] {
        grid-column: 1 / 3;
        margin-top: 10px;
        background: #00aaff;
        color: #fff;
        border: none;
        padding: 10px 0;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        width: 100%;
        max-width: 260px;
        justify-self: center;
        transition: background 0.2s;
    }
    .modal-content form button[type="submit"]:hover {
        background: #0077b6;
    }
    .modal-content form .error-message {
        grid-column: 1 / 3;
        color: red;
        margin-bottom: 10px;
        font-weight: bold;
        text-align: center;
    }
        .add-haircut-btn {
            background: #00aaff;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            transition: background 0.2s;
        }
        .add-haircut-btn:hover {
            background: #0077b6;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 24px 32px 20px 32px;
            border: 1px solid #888;
            width: 420px;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            margin: 0;
        }
        .close {
            color: #aaa;
            position: absolute;
            right: 16px;
            top: 8px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-content h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            width: 100%;
        }
        .modal-content form {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 12px 10px;
            align-items: center;
            width: 100%;
            justify-items: center;
        }
        .modal-content form label {
            text-align: right;
            font-weight: 500;
            font-size: 0.98rem;
            margin-right: 4px;
        }
        .modal-content form input[type="text"] {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 2px;
        }
        .modal-content form input[type="number"] {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 2px;
        }
        .modal-content form select {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 2px;
            margin-left: 0;
            box-sizing: border-box;
        }
        .modal-content form button[type="submit"] {
            grid-column: 1 / 3;
            margin-top: 10px;
            background: #007bff;
            color: #fff;
            border: none;
            padding: 8px 0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            max-width: 260px;
            justify-self: center;
        }
        .modal-content form .error-message {
            grid-column: 1 / 3;
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
            text-align: center;
        }
    /* Service card design to match employees.php image */
    .service-card-list {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 18px;
        width: 100%;
    }
    .service-card {
        background: linear-gradient(90deg, #e6f7ff 60%, #cceeff 100%);
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(0,170,255,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
        padding: 0;
        min-width: 0;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        border: none;
        transition: box-shadow 0.2s, transform 0.2s;
        margin-bottom: 2px;
        overflow: hidden;
    }
    .service-card:hover {
        box-shadow: 0 8px 24px rgba(0,170,255,0.18), 0 2px 12px rgba(0,0,0,0.08);
        transform: translateY(-2px) scale(1.01);
    }
    .service-card-title {
        font-size: 1.18rem;
        font-weight: 600;
        color: #000000ff;
        margin-left: 32px;
        margin-right: 0;
        flex: 1;
        text-align: left;
        padding: 18px 0;
        letter-spacing: 0.5px;
        text-shadow: 0 1px 2px #cceeff;
    }
    .service-card-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-right: 32px;
    }
    .service-action-btn {
        background: #00aaff;
        border: none;
        border-radius: 25%;
        width: 25px;
        height: 25px;
        padding: 0;
        cursor: pointer;
        font-size: 1.08rem;
        color: #fff;
        transition: background 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,170,255,0.10);
    }
    .service-action-btn.info { background: #00aaff; }
    .service-action-btn.edit { background: #ffc107; color: #fff; }
    .service-action-btn.archive { background: #6c757d; color: #fff; }
    .service-action-btn:hover { filter: brightness(0.95); box-shadow: 0 4px 12px rgba(0,170,255,0.18); }
    /* Modal styles for service info */
    #serviceInfoModal .modal-content {
        padding: 24px;
        border-radius: 12px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
    }
    #serviceInfoModal h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 16px;
        color: #0077b6;
        text-align: center;
    }
    #serviceInfoModal .close {
        right: 12px;
        top: 12px;
        font-size: 24px;
    }
    #serviceInfoModal .modal-content div {
        margin-bottom: 12px;
        font-size: 1rem;
        color: #333;
    }
    @media (max-width: 700px) {
        .service-card-title { margin-left: 12px; padding: 12px 0; }
        .service-card-actions { margin-right: 12px; }
        .service-card-details { margin-left: 12px; margin-right: 12px; }
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
            // --- Queue Notification Badge Logic (copied from employees.php) ---
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

                <!-- Modal for Add Service -->
                <div id="haircutModal" class="modal">
                    <div class="modal-content">
                        <span class="close" id="closeHaircutModal">&times;</span>
                        <h2>Add Service</h2>
                        <form id="addHaircutForm" method="post" action="haircuts.php">
                            <label for="haircutName">Name:</label>
                            <input type="text" id="haircutName" name="hc_name" placeholder="Enter service name" required>
                            <label for="serviceType">Service:</label>
                            <select id="serviceType" name="hc_service" required>
    <option value="Haircut">Haircut</option>
    <option value="Color">Color</option>
    <option value="Shave">Shave</option>
    <option value="Massage">Massage</option> <!-- ✅ Massage Added -->
    </select>
    
   <label for="barber">Barber(s):</label>
<select id="barber" name="hc_employee[]" class="form-control" multiple required>
  <?php foreach ($employees as $employee): ?>
    <option value="<?= $employee['EmployeeID'] ?>">
      <?= htmlspecialchars($employee['FullName']) ?>
    </option>
  <?php endforeach; ?>
</select>

                            <label for="haircutPrice">Price:</label>
                            <input type="number" id="haircutPrice" name="hc_price" placeholder="Enter price" min="0" step="0.01" required>
                            <button type="submit" name="add_haircut">Add Service</button>
                        </form>
                    </div>
                </div>

                <div class="queue-list">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0;">Service List</h2>
                        <form method="get" style="display: flex; gap: 10px; align-items: center; margin: 0; position:relative;">
                                                                <div style="display:flex;align-items:center;gap:0;">
                                                                    <input type="text" name="search_hc" id="search_hc" placeholder="<?= $translations['name'] ?>/Service" value="<?= isset($_GET['search_hc']) ? htmlspecialchars($_GET['search_hc']) : '' ?>" style="padding: 8px; border-radius: 4px 0 0 4px; border: 1px solid #ccc; border-right:none; height:30px;">
                                                                    <button type="submit" id="searchBtn" class="search-button" style="background: #28a745; color: #fff; border: none; border-radius: 0 4px 4px 0; cursor: pointer; font-size: 18px;display:flex;align-items:center;justify-content:center; width:30px ;height:30px; margin-left:0;" title="Search">
                                                                        <i class="fa-solid fa-magnifying-glass"></i>
                                                                    </button>
                                                                </div>
                                                        <select name="service_filter" id="service_filter" style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; margin-left:8px;">
        <option value="">All Services</option>
        <option value="Haircut" <?= (isset($_GET['service_filter']) && $_GET['service_filter'] === 'Haircut') ? 'selected' : '' ?>>Haircut</option>
        <option value="Color" <?= (isset($_GET['service_filter']) && $_GET['service_filter'] === 'Color') ? 'selected' : '' ?>>Color</option>
        <option value="Shave" <?= (isset($_GET['service_filter']) && $_GET['service_filter'] === 'Shave') ? 'selected' : '' ?>>Shave</option>
        <option value="Massage" <?= (isset($_GET['service_filter']) && $_GET['service_filter'] === 'Massage') ? 'selected' : '' ?>>Massage</option>
    </select>
                            <select name="search_status" id="search_status" style="padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; margin-left:8px;">
                                    <option value="">All Status</option>
                                    <option value="active" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'active') ? 'selected' : '' ?>><?= $translations['available'] ?></option>
                                    <option value="unavailable" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'unavailable') ? 'selected' : '' ?>><?= $translations['discontinued'] ?? 'Unavailable' ?></option>
                                    <option value="archived" <?= (isset($_GET['search_status']) && $_GET['search_status'] === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                            <button id="openHaircutModal" type="button" class="add-haircut-btn" style="background: #00aaff; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 500;">Add Service</button>
                                                        <!-- Removed Clear Button -->
                                                        <script>
                                                            document.getElementById('service_filter').addEventListener('change', function() {
                                                                this.form.submit();
                                                            });
                                                            document.getElementById('search_status').addEventListener('change', function() {
                                                                this.form.submit();
                                                            });
                                                        </script>
                        </form>
                    </div>
                    <!-- Card layout for services -->
                    <div class="service-card-list">
<?php
// Build filter/search SQL
$where = [];
$join = '';
if ($shopID) {
    $where[] = "h.shopID = $shopID";
}
if (!empty($_GET['search_hc'])) {
    $searchHc = $conn->real_escape_string($_GET['search_hc']);
    // Join haircut_barbers and employee to allow searching by barber name
    $join .= " LEFT JOIN haircut_barbers hb ON h.HaircutID = hb.HaircutID LEFT JOIN employee e ON hb.EmployeeID = e.EmployeeID ";
    $where[] = "(h.Name LIKE '%$searchHc%' OR h.Service LIKE '%$searchHc%' OR CONCAT(e.FirstName, ' ', e.LastName) LIKE '%$searchHc%')";
}
if (!empty($_GET['service_filter'])) {
    $serviceFilter = $conn->real_escape_string($_GET['service_filter']);
    $where[] = "h.Service = '$serviceFilter'";
}
if (!empty($_GET['search_status'])) {
    $searchStatus = $conn->real_escape_string($_GET['search_status']);
    if ($searchStatus === 'archived') {
        $where[] = "h.Status = 'archived'";
    } else {
        $where[] = "h.Status = '$searchStatus'";
    }
}
$sql = "SELECT DISTINCT h.* FROM haircut h $join";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Hide archived haircuts unless filter is set to archived
        if ((!isset($_GET['search_status']) || $_GET['search_status'] !== 'archived') && isset($row['Status']) && $row['Status'] === 'archived') {
            continue;
        }
        $status = 'Available';
        $statusClass = '';
        if (isset($row['Status']) && $row['Status'] === 'archived') {
            $status = 'Archived';
            $statusClass = 'archived';
        } elseif (isset($row['Status']) && $row['Status'] === 'unavailable') {
            $status = 'Unavailable';
            $statusClass = 'unavailable';
        }
        // Get barbers
        $barberNames = [];
        $barberIDs = [];
        $barberResult = $conn->query("SELECT e.FirstName, e.LastName, e.EmployeeID FROM haircut_barbers hb INNER JOIN employee e ON hb.EmployeeID = e.EmployeeID WHERE hb.HaircutID = " . (int)$row['HaircutID']);
        if ($barberResult && $barberResult->num_rows > 0) {
            while ($empRow = $barberResult->fetch_assoc()) {
                $barberNames[] = htmlspecialchars($empRow['FirstName'] . ' ' . $empRow['LastName']);
                $barberIDs[] = $empRow['EmployeeID'];
            }
        }
        $barberName = $barberNames ? implode(', ', $barberNames) : "N/A";
        $barberIDsJson = htmlspecialchars(json_encode($barberIDs));
        ?>
        <div class="service-card" data-haircut-id="<?= htmlspecialchars($row['HaircutID']) ?>">
            <div class="service-card-title"><?= htmlspecialchars($row['Name']) ?></div>
            <div class="service-card-actions">
                <button class="service-action-btn info" title="More Info" onclick="toggleServiceInfo(this)"
                    data-haircut-id="<?= htmlspecialchars($row['HaircutID']) ?>"
                >
                    <i class="fa-solid fa-circle-info"></i>
                </button>
                <button type="button" class="service-action-btn edit edit-haircut-btn" title="Edit"
                    data-haircut-id="<?= htmlspecialchars($row['HaircutID']) ?>"
                    data-haircut-name="<?= htmlspecialchars($row['Name']) ?>"
                    data-haircut-service="<?= htmlspecialchars($row['Service']) ?>"
                    data-haircut-price="<?= htmlspecialchars($row['Price']) ?>"
                    data-haircut-status="<?= htmlspecialchars($row['Status']) ?>"
                    data-haircut-barbers='<?= $barberIDsJson ?>'
                >
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <?php if ($row['Status'] !== 'archived'): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="archive_haircut_id" value="<?= htmlspecialchars($row['HaircutID']) ?>">
                    <button type="submit" name="archive" class="service-action-btn archive" title="Archive" onclick="return confirm('Are you sure you want to archive this service?')">
                        <i class="fa-solid fa-box-archive"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="service-card-details" id="service-details-<?= htmlspecialchars($row['HaircutID']) ?>" style="display:none; background:#f7fbff; border-top:1px solid #e0e0e0; padding:16px 32px;">
                <div><strong>Service:</strong> <?= htmlspecialchars($row['Service']) ?></div>
                <div><strong>Barber(s):</strong> <?= htmlspecialchars($barberName) ?></div>
                <div><strong>Price:</strong> <?= htmlspecialchars($row['Price']) ?></div>
                <div><strong>Status:</strong> <?= $status ?></div>
            </div>
        </div>
        <?php
    }
} else {
    echo '<div style="width:100%;text-align:center;padding:24px 0;">' . ($translations['no_results'] ?? 'No result found') . '</div>';
}
?>
                    </div>
                </div>
<!-- Remove More Info Modal -->
<!-- Edit Haircut Modal -->
<div id="editHaircutModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeEditHaircutModal">&times;</span>
        <h2><?= $translations['edit_haircut'] ?? 'Edit Service' ?></h2>
        <form id="editHaircutForm" method="post">
            <input type="hidden" id="editHaircutId" name="edit_haircut_id">
            <label for="editHaircutName">Name:</label>
            <input type="text" id="editHaircutName" name="edit_hc_name" required>
            <label for="editServiceType">Service:</label>
            <select id="editServiceType" name="edit_hc_service" required>
                <option value="Haircut">Haircut</option>
                <option value="Color">Color</option>
                <option value="Shave">Shave</option>
                <option value="Massage">Massage</option>
            </select>
            <label for="editBarber">Barber(s):</label>
            <select id="editBarber" name="edit_hc_employee[]" class="form-control" multiple required>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?= $employee['EmployeeID'] ?>">
                        <?= htmlspecialchars($employee['FullName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="editHaircutPrice">Price:</label>
            <input type="number" id="editHaircutPrice" name="edit_hc_price" min="0" step="0.01" required>
            <label for="editStatusDropdown">Status:</label>
            <select id="editStatusDropdown" name="edit_hc_status" required>
                <option value="active"><?= $translations['available'] ?? 'Available' ?></option>
                <option value="unavailable"><?= $translations['discontinued'] ?? 'Unavailable' ?></option>
            </select>
            <button type="submit" name="edit_haircut">Save Changes</button>
        </form>
    </div>
</div>
    <script>
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

            // Modal logic for Add Haircut
            var haircutModal = document.getElementById('haircutModal');
            var openHaircutBtn = document.getElementById('openHaircutModal');
            var closeHaircutSpan = document.getElementById('closeHaircutModal');
            if (openHaircutBtn && haircutModal && closeHaircutSpan) {
                openHaircutBtn.onclick = function() {
                    haircutModal.style.display = 'flex';
                }
                closeHaircutSpan.onclick = function() {
                    haircutModal.style.display = 'none';
                }
                window.onclick = function(event) {
                    if (event.target == haircutModal) {
                        haircutModal.style.display = 'none';
                    }
                }
                // Ensure modal is hidden on page load
                haircutModal.style.display = 'none';
            }

            // Remove More Info modal logic
            // Add expand/collapse logic for service info
            window.toggleServiceInfo = function(btn) {
                var haircutId = btn.getAttribute('data-haircut-id');
                var detailsDiv = document.getElementById('service-details-' + haircutId);
                // Hide all details except the one being toggled
                document.querySelectorAll('.service-card-details').forEach(function(div) {
                    if (div !== detailsDiv) div.style.display = 'none';
                });
                // Toggle the selected details
                if (detailsDiv) {
                    if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
                        detailsDiv.style.display = 'block';
                    } else {
                        detailsDiv.style.display = 'none';
                    }
                }
            };

            // Edit modal logic
    var editHaircutModal = document.getElementById('editHaircutModal');
    var closeEditHaircutSpan = document.getElementById('closeEditHaircutModal');
    var editHaircutForm = document.getElementById('editHaircutForm');
    document.querySelectorAll('.edit-haircut-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editHaircutId').value = btn.getAttribute('data-haircut-id');
            document.getElementById('editHaircutName').value = btn.getAttribute('data-haircut-name');
            document.getElementById('editServiceType').value = btn.getAttribute('data-haircut-service');
            document.getElementById('editHaircutPrice').value = btn.getAttribute('data-haircut-price');
            document.getElementById('editStatusDropdown').value = btn.getAttribute('data-haircut-status');
            // Pre-fill assigned barbers from data attribute
            var barberIDs = [];
            try {
                barberIDs = JSON.parse(btn.getAttribute('data-haircut-barbers'));
            } catch (e) {}
            $('#editBarber').val(barberIDs).trigger('change');
            editHaircutModal.style.display = 'flex';
        });
    });
                if (closeEditHaircutSpan && editHaircutModal) {
                    closeEditHaircutSpan.onclick = function() {
                        editHaircutModal.style.display = 'none';
                    }
                    window.addEventListener('click', function(event) {
                        if (event.target == editHaircutModal) {
                            editHaircutModal.style.display = 'none';
                        }
                    });
                    // Ensure modal is hidden on page load
                    editHaircutModal.style.display = 'none';
                }
        });
$(document).ready(function() {
  $('#barber').select2({
    placeholder: "Select one or more barbers",
    width: '100%',
    closeOnSelect: false,
    allowClear: true
  });
  $('#editBarber').select2({
    placeholder: "Select one or more barbers",
    width: '100%',
    closeOnSelect: false,
    allowClear: true
  });
});
        
  $(document).ready(function() {
  $('#barber').select2({
    placeholder: "Select one or more barbers",
    width: '100%',
    closeOnSelect: false, // ✅ lets you select multiple without closing
    allowClear: true      // ✅ optional: adds a small “x” to clear selections
  });
});


    </script>
</body>
</html>