<?php
session_start();
if (!isset($_SESSION['adminID']) && !isset($_SESSION['barberID']) && !isset($_SESSION['sadminID'])) {
    header("Location: session_expired.html");
    exit();
}

// Set the default timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Language handling
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$lang = $_SESSION['language'];
$translations = include("languages/{$lang}.php");

require_once "Connection.php";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$adminID = isset($_SESSION['adminID']) ? (int)$_SESSION['adminID'] : null;
$barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;

// Handle archive action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive']) && isset($_POST['archive_sales_id'])) {
    $archive_sales_id = (int)$_POST['archive_sales_id'];
    $update_sql = "UPDATE sales SET sales_status = 'archived' WHERE salesID = $archive_sales_id AND adminID = $adminID AND shopID = $barbershopID";
    $conn->query($update_sql);
    // Redirect to avoid form resubmission, preserving existing filters
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit();
}

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch barbershop name for sidebar
$barbershopName = '';
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $result = $conn->query("SELECT name FROM barbershops WHERE shopID = $barbershopID");
    if ($result && $row = $result->fetch_assoc()) {
        $barbershopName = $row['name'];
    }
}
// Fetch count of new queue notifications (items with status 'In Queue')
$newQueueCount = 0;
$result = $conn->query("SELECT COUNT(*) AS cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID");
if ($result && $row = $result->fetch_assoc()) {
    $newQueueCount = (int)$row['cnt'];
}

// Get filter parameters from URL
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$service_filter = isset($_GET['service_filter']) ? $conn->real_escape_string($_GET['service_filter']) : '';
$date_filter = isset($_GET['date_filter']) ? $conn->real_escape_string($_GET['date_filter']) : '';
$barber_filter = isset($_GET['barber_filter']) ? $conn->real_escape_string($_GET['barber_filter']) : '';
$search_status = isset($_GET['search_status']) ? $conn->real_escape_string($_GET['search_status']) : '';

// Build the main query to display sales records
$sql = "SELECT salesID, sales_name, sales_service, barber, sales_price, sales_dateTime, sales_status, haircut_name, color_name, shave_name, massage_name 
FROM sales 
    WHERE adminID = $adminID AND shopID = $barbershopID";

// Apply status filter: By default, show non-archived sales. If 'archived' is selected, show only archived.
if ($search_status === 'archived') {
    $sql .= " AND sales_status = 'archived'";
} else {
    $sql .= " AND (sales_status IS NULL OR sales_status != 'archived')";
}

// Apply search filters
if (!empty($search)) {
    $sql .= " AND (sales_name LIKE '%$search%' OR sales_service LIKE '%$search%' OR barber LIKE '%$search%')";
}

if (!empty($service_filter)) {
    if ($service_filter === 'both') {
        $sql .= " AND (haircut_name != 'N/A' AND color_name != 'N/A')";
    } elseif ($service_filter === 'haircut') {
        $sql .= " AND haircut_name != 'N/A'";
    } elseif ($service_filter === 'coloring') {
        $sql .= " AND color_name != 'N/A'";
    } elseif ($service_filter === 'shave') {
        $sql .= " AND shave_name != 'N/A'";
    } elseif ($service_filter === 'massage') {
        $sql .= " AND massage_name != 'N/A'";
    }
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(sales_dateTime) = '$date_filter'";
}

if (!empty($barber_filter)) {
    $sql .= " AND barber = '$barber_filter'";
}


$sql .= " ORDER BY salesID DESC";
$result = $conn->query($sql);

// Fetch all active haircuts and colors for the computation dropdown
// Fetch all active haircuts, colors, and shaves for the computation dropdown
$haircut_color_options = [];
$hc_result = $conn->query("SELECT Name, Service FROM haircut WHERE Status = 'active' AND AdminID = $adminID AND shopID = $barbershopID");
if ($hc_result && $hc_result->num_rows > 0) {
    while ($row = $hc_result->fetch_assoc()) {
        $haircut_color_options[] = $row;
    }
}

// Fetch all unique shaves for the computation dropdown



$shave_options = [];
$shave_result = $conn->query("SELECT DISTINCT TRIM(shave_name) AS shave_name FROM sales WHERE shave_name IS NOT NULL AND shave_name != '' AND shave_name != 'N/A' AND adminID = $adminID AND shopID = $barbershopID");
if ($shave_result && $shave_result->num_rows > 0) {
    while ($row = $shave_result->fetch_assoc()) {
        $shave = $row['shave_name'];
        if ($shave !== '') {
            $shave_options[] = $shave;
        }
    }
}

// Fetch all unique barbers for the computation dropdown, excluding archived employees
$barber_options = [];
$barber_result = $conn->query(
    "SELECT DISTINCT s.barber FROM sales s 
     INNER JOIN employee e ON s.barber = CONCAT(e.FirstName, ' ', e.LastName) 
     WHERE s.barber IS NOT NULL AND s.barber != '' 
     AND s.adminID = $adminID AND s.shopID = $barbershopID 
     AND (e.status IS NULL OR e.status != 'archived')
     ORDER BY s.barber ASC"
);
if ($barber_result && $barber_result->num_rows > 0) {
    while ($row = $barber_result->fetch_assoc()) {
        $barber_options[] = $row['barber'];
    }
}

// --- SALES COMPUTATION LOGIC (WITH SEPARATE BARBER FILTER) ---
$computation_type = isset($_GET['computation_type']) ? $_GET['computation_type'] : 'daily';
$period_label = '';
$period_total = 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$selected_service = isset($_GET['service_calc_filter']) ? $_GET['service_calc_filter'] : '';
$selected_barber_calc = isset($_GET['barber_calc_filter']) ? $_GET['barber_calc_filter'] : '';

// Barber commission logic
$barber_commission = null;
$barber_earning = null;
$barbershop_earning = null;
$selected_barber_name = null;

if (!empty($selected_barber_calc)) {
    $selected_barber_name = $selected_barber_calc;
    // Fetch commission rate for this barber (percentage)
    $emp_result = $conn->query("SELECT Commission FROM employee WHERE CONCAT(FirstName, ' ', LastName) = '" . $conn->real_escape_string($selected_barber_name) . "' AND AdminID = $adminID AND shopID = $barbershopID LIMIT 1");
    if ($emp_result && $emp_row = $emp_result->fetch_assoc()) {
        $barber_commission = floatval($emp_row['Commission']); // e.g. 15.00 for 15%
    }
}

// Base query for sales computation
$period_sql = "SELECT SUM(sales_price) as total FROM sales WHERE adminID = $adminID AND shopID = $barbershopID";

// 1. Apply Status Filter
if ($search_status === 'archived') {
    $period_sql .= " AND sales_status = 'archived'";
} else {
    $period_sql .= " AND (sales_status IS NULL OR sales_status != 'archived')";
}

// 2. Apply Date/Period Filter
switch ($computation_type) {
    case 'daily':
        $period_label = 'This Day\'s Income';
        $today_manila = date('Y-m-d');
        $period_sql .= " AND DATE(sales_dateTime) = '" . $conn->real_escape_string($today_manila) . "'";
        break;
    case 'weekly':
        $period_label = 'This Week\'s Income';
        $period_sql .= " AND YEARWEEK(sales_dateTime, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'monthly':
        $period_label = 'This Month\'s Income';
        $period_sql .= " AND YEAR(sales_dateTime) = YEAR(CURDATE()) AND MONTH(sales_dateTime) = MONTH(CURDATE())";
        break;
    case 'yearly':
        $period_label = 'This Year\'s Income';
        $period_sql .= " AND YEAR(sales_dateTime) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $period_label = 'Custom Period Income';
            $period_sql .= " AND DATE(sales_dateTime) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
        } else {
            $period_label = 'Custom Period (Select Dates)';
        }
        break;
    default:
        $period_label = 'This Day\'s Income';
        $today_manila = date('Y-m-d');
        $period_sql .= " AND DATE(sales_dateTime) = '" . $conn->real_escape_string($today_manila) . "'";
}

// 3. Apply Service Filter
if ($selected_service === '__all_haircuts__') {
    $period_sql .= " AND (haircut_name IS NOT NULL AND haircut_name != '' AND haircut_name != 'N/A')";
} elseif ($selected_service === '__all_colors__') {
    $period_sql .= " AND (color_name IS NOT NULL AND color_name != '' AND color_name != 'N/A')";
} elseif ($selected_service === '__all_shaves__') {
    $period_sql .= " AND (shave_name IS NOT NULL AND shave_name != '' AND shave_name != 'N/A')";
} elseif (!empty($selected_service)) {
    $period_sql .= " AND (haircut_name = '" . $conn->real_escape_string($selected_service) . "' OR color_name = '" . $conn->real_escape_string($selected_service) . "' OR shave_name = '" . $conn->real_escape_string($selected_service) . "')";
}

// 4. Apply Barber Filter
if (!empty($selected_barber_calc)) {
    $period_sql .= " AND barber = '" . $conn->real_escape_string($selected_barber_calc) . "'";
}

$period_result = $conn->query($period_sql);
if ($period_result && $row = $period_result->fetch_assoc()) {
    $period_total = floatval($row['total'] ?? 0);
} else {
    $period_total = 0.00;
}

// If a barber is selected, calculate commission and earnings
if ($selected_barber_name && $barber_commission !== null) {
    $barber_earning = $period_total * ($barber_commission / 100.0);
    $barbershop_earning = $period_total - $barber_earning;
    // Format for display
    $barber_earning = number_format($barber_earning, 2);
    $barbershop_earning = number_format($barbershop_earning, 2);
}
$period_total_display = number_format($period_total, 2);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['sales_overview'] ?? 'Sales Overview' ?> | Admin Dashboard</title>
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
    .modal-content .close {
        color: #6c757d !important;
        transition: color 0.2s;
    }
    .modal-content .close:hover {
        color: #343a40 !important;
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
            <ul class="nav-links">
                    <li style="position:relative;display:flex;align-items:center;">
                        <a href="queueing.php" class="<?= $current_page === 'queueing.php' ? 'active' : '' ?>" style="display:inline-flex;align-items:center;gap:8px;">
                            <i class="fa-solid fa-list"></i> Queue
                            <?php if (isset($newQueueCount) && $newQueueCount > 0): ?>
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
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 38px; flex-wrap: wrap; gap: 10px;"></div>
                    <div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
                        <!-- Modal Popup for Calculator -->
                        <div id="computationModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100vw; height:100vh; overflow:auto; background:rgba(0,0,0,0.18);">
    <div class="modal-content" style="background:#fff; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); padding:32px 28px 24px 28px; border-radius:16px; width:90vw; max-width:700px; box-shadow:0 4px 24px rgba(0,0,0,0.13); display:flex; flex-direction:row; gap:32px;">
        <span class="close" id="closeComputationModal" style="position:absolute;top:18px;right:18px;font-size:2em;font-weight:700;cursor:pointer;">&times;</span>
        <!-- Left column: Form -->
        <div style="flex:1; min-width:320px;">
            <h2 style="margin-bottom:18px;">Income Calculator</h2>
            <form method="get" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" name="show_modal" value="1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="service_filter" value="<?= htmlspecialchars($service_filter) ?>">
                <input type="hidden" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>">
                <input type="hidden" name="search_status" value="<?= htmlspecialchars($search_status) ?>">

                <label for="computation_type">Compute by:</label>
                <select id="computation_type_modal" name="computation_type" onchange="toggleDateInputsModal()" style="width: 100%; margin-left: -1px; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="daily" <?= $computation_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $computation_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $computation_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= $computation_type === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    <option value="custom" <?= $computation_type === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                </select>
                <label for="service_calc_filter">Filter by Service:</label>
                <div style="max-height:120px;overflow-y:auto;width:100%;margin-left:-1px;padding:0;border-radius:4px;border:1px solid #ccc;background:#fff;">
                    <select id="service_calc_filter" name="service_calc_filter" size="1" style="width: 100%; padding: 8px; border: none; border-radius: 0; box-shadow: none; background: transparent;">
                        <option value="">All</option>
                        <optgroup label="By Service">
                            <option value="__all_haircuts__" <?= $selected_service === '__all_haircuts__' ? 'selected' : '' ?>>All Haircuts</option>
                            <option value="__all_colors__" <?= $selected_service === '__all_colors__' ? 'selected' : '' ?>>All Colors</option>
                            <option value="__all_shaves__" <?= $selected_service === '__all_shaves__' ? 'selected' : '' ?>>All Shaves</option>
                            <?php foreach ($haircut_color_options as $opt): ?>
                                <option value="<?= htmlspecialchars($opt['Name']) ?>" <?= $selected_service === $opt['Name'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['Name']) ?> (<?= htmlspecialchars($opt['Service']) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <label for="barber_calc_filter">Filter by Barber:</label>
                <div style="max-height:120px;overflow-y:auto;width:100%;margin-left:-1px;padding:0;border-radius:4px;border:1px solid #ccc;background:#fff;">
                    <select id="barber_calc_filter" name="barber_calc_filter" size="1" style="width: 100%; padding: 8px; border: none; border-radius: 0; box-shadow: none; background: transparent;">
                        <option value="">All Barbers</option>
                        <?php foreach ($barber_options as $barber): ?>
                            <option value="<?= htmlspecialchars($barber) ?>" <?= $selected_barber_calc === $barber ? 'selected' : '' ?>><?= htmlspecialchars($barber) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="dateRangeInputsModal" style="display: <?= $computation_type === 'custom' ? 'flex' : 'none' ?>; flex-direction: column; gap: 8px;">
                    <div>
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date_modal" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>
                    <div>
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date_modal" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>
                </div>
                <button type="submit" style="padding: 8px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Calculate</button>
                <button type="button" onclick="window.location.href='sales.php'" style="padding: 8px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 4px;">Clear All</button>
            </form>
        </div>
        <!-- Right column: Results -->
        <div style="flex:1; min-width:260px; display:flex; flex-direction:column; justify-content:center; align-items:flex-start; border-left:1px solid #eee; padding-left:32px;">
            <div style="font-size: 20px; font-weight: bold; margin-bottom: 18px;">
                <?= $period_label ?>
            </div>
            <div style="font-size: 22px; color: #3498db; font-weight: bold; margin-bottom: 18px;">
                ₱<?= $period_total_display ?>
            </div>
            <?php if ($selected_barber_name && $barber_commission !== null): ?>
                <div style="font-size:16px;color:#333;margin-bottom:12px;">
                    Barbershop Earnings:<br>
                    <span style="color:#28a745;font-size:20px;font-weight:bold;">₱<?= $barbershop_earning ?></span>
                </div>
                <div style="font-size:16px;color:#333;">
                    <?= htmlspecialchars($selected_barber_name) ?>'s Earnings (<?= number_format($barber_commission, 2) ?>%):<br>
                    <span style="color:#dc3545;font-size:20px;font-weight:bold;">₱<?= $barber_earning ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                        <div style="flex: 1; margin-top: -32px;">
                            <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
                               <form method="get" class="filter-form" id="filterForm" style="display:flex;gap:0;align-items:center;">
        <div style="position:relative;min-width:200px;">
            <input type="text" name="search" placeholder="Name/Service/Barber" 
                value="<?= htmlspecialchars($search) ?>" 
                style="padding:8px 12px 8px 12px;border:1px solid #ddd;border-radius:4px 0 0 4px;min-width:200px;">
        </div>
        <button type="submit" class="search-button" 
            style="background:#28a745;color:#fff;border:none;padding:8px 14px;border-radius:0 4px 4px 0;cursor:pointer;display:flex;align-items:center;margin-left:-1px;">
            <i class="fa fa-search" style="color:#fff;font-size:18px;"></i>
        </button>
        <select name="service_filter" style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;margin-left:8px;">
            <option value="">All Services</option>
            <option value="haircut" <?= $service_filter === 'haircut' ? 'selected' : '' ?>>Haircut</option>
            <option value="coloring" <?= $service_filter === 'coloring' ? 'selected' : '' ?>>Coloring</option>
            <option value="shave" <?= $service_filter === 'shave' ? 'selected' : '' ?>>Shave</option>
            <option value="massage" <?= $service_filter === 'massage' ? 'selected' : '' ?>>Massage</option>
        </select>
        <select name="search_status" style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;margin-left:8px;">
            <option value="">Completed</option>
            <option value="archived" <?= $search_status === 'archived' ? 'selected' : '' ?>>Archived</option>
        </select>
        <input type="date" name="date_filter" 
            value="<?= htmlspecialchars($date_filter) ?>" 
            style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;margin-left:8px;">
        <select name="barber_filter" style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;margin-left:8px;">
            <option value="">All Barbers</option>
            <?php foreach ($barber_options as $barber): ?>
                <option value="<?= htmlspecialchars($barber) ?>" 
                    <?= (isset($_GET['barber_filter']) && $_GET['barber_filter'] === $barber) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($barber) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="openComputationModal" 
            style="background: #3498db; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 18px;display:flex;align-items:center;justify-content:center;margin-left:8px;">
            <i class="fa-solid fa-calculator"></i>
        </button>
    </form>
                            </div>
                           <table>
    <thead>
        <tr>
            <th>#</th>
            <th><?= $translations['name'] ?? 'Name' ?></th>
            <th><?= $translations['service'] ?? 'Service' ?></th>
            <th>Barber</th>
            <th>Haircut</th>
            <th>Color</th>
            <th>Shave</th>
            <th>Massage</th> <!-- ✅ Added -->
            <th>Price</th>
            <th>Date & Time</th>
            <th><?= $translations['status'] ?? 'Status' ?></th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $idx = 1;
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $status = ($row['sales_status'] === 'archived') ? 'Archived' : 'Completed';
                
                // ✅ Collect all active services
                $services = [];
                if (!empty($row['haircut_name']) && $row['haircut_name'] !== 'N/A') $services[] = 'Haircut';
                if (!empty($row['color_name']) && $row['color_name'] !== 'N/A') $services[] = 'Coloring';
                if (!empty($row['shave_name']) && $row['shave_name'] !== 'N/A') $services[] = 'Shave';
                if (!empty($row['massage_name']) && $row['massage_name'] !== 'N/A') $services[] = 'Massage'; // ✅ Added

                $service_display = $services ? implode(', ', $services) : htmlspecialchars(ucfirst($row['sales_service'] ?? ''));

                // Format date and time
                $formattedDateTime = '';
                if (!empty($row['sales_dateTime'])) {
                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['sales_dateTime']);
                    if ($dt) {
                        $month = strtolower($dt->format('F'));
                        $month = ucfirst($month);
                        $formattedDateTime = $month . $dt->format(' d, Y h:iA');
                    } else {
                        $formattedDateTime = htmlspecialchars($row['sales_dateTime']);
                    }
                }
                echo "<tr>
                    <td>{$idx}</td>
                    <td>" . htmlspecialchars($row['sales_name'] ?? '') . "</td>
                    <td>" . $service_display . "</td>
                    <td>" . htmlspecialchars($row['barber'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['haircut_name'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['color_name'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['shave_name'] ?? '') . "</td>
                    <td>" . htmlspecialchars($row['massage_name'] ?? '') . "</td> <!-- ✅ Added -->
                    <td>" . htmlspecialchars($row['sales_price'] ?? '') . "</td>
                    <td>" . htmlspecialchars($formattedDateTime) . "</td>
                    <td>" . htmlspecialchars($status) . "</td>
                    <td>";

                // ✅ Only show "Archive" button if not yet archived, now as icon
                if ($row['sales_status'] !== 'archived') {
                    echo "<form method='post' style='display:inline;'>
                            <input type='hidden' name='archive_sales_id' value='" . htmlspecialchars($row['salesID']) . "'>
                            <button type='submit' name='archive' onclick=\"return confirm('Are you sure you want to archive this sale?')\" style='background-color: #6c757d; color: #fff; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; margin-left: 4px; font-weight: 600;'>
                                <i class='fa-solid fa-box-archive'></i>
                            </button>
                        </form>";
                }

                echo "</td>
                </tr>";
                $idx++;
            }
        } else {
            echo '<tr><td colspan="12" style="text-align:center;">' . ($translations['no_results'] ?? 'No result found') . '</td></tr>'; // ✅ colspan updated
        }
        ?>
    </tbody>
</table>

                        </div>
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

    // Modal logic
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('computationModal');
        var btn = document.getElementById('openComputationModal');
        var span = document.getElementById('closeComputationModal');
        // Open modal on button click
        btn.onclick = function() { modal.style.display = 'block'; }
        // Close modal on close button click
        span.onclick = function() { modal.style.display = 'none'; }
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) { modal.style.display = 'none'; }
        }

        // Date input toggle for modal
        function toggleDateInputsModal() {
            var computationType = document.getElementById('computation_type_modal').value;
            var dateRangeInputs = document.getElementById('dateRangeInputsModal');
            dateRangeInputs.style.display = computationType === 'custom' ? 'flex' : 'none';
        }
        window.toggleDateInputsModal = toggleDateInputsModal;
        toggleDateInputsModal();

        // Show modal if show_modal=1 in URL
        function getUrlParam(name) {
            var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.search);
            return results ? decodeURIComponent(results[1]) : null;
        }
        if (getUrlParam('show_modal') === '1') {
            modal.style.display = 'block';
        }
    });

    // Auto-submit filters on change
    document.addEventListener('DOMContentLoaded', function() {
        var filterForm = document.getElementById('filterForm');
        var filterInputs = filterForm.querySelectorAll('select,input[type="date"]');
        filterInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                filterForm.submit();
            });
        });
        // Optionally, auto-submit on search input enter
        filterForm.querySelector('input[name="search"]').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                filterForm.submit();
            }
        });
    });
    </script>
</body>
</html>
