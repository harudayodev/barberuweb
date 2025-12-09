<?php
date_default_timezone_set('Asia/Manila'); // Set the default timezone to Asia/Manila
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
}

// Fetch active haircuts and colors for dropdowns
$haircuts = [];
$colors = [];
$adminID = (int)$_SESSION['adminID'];
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $haircutResult = $conn->query("SELECT Name, Price, Service FROM haircut WHERE Status = 'active' AND adminID = $adminID AND shopID = $barbershopID");
} else {
    $haircutResult = $conn->query("SELECT Name, Price, Service FROM haircut WHERE Status = 'active' AND adminID = $adminID");
}
if ($haircutResult && $haircutResult->num_rows > 0) {
    while ($row = $haircutResult->fetch_assoc()) {
        if ($row['Service'] === 'Haircut') {
            $haircuts[] = $row;
        } elseif ($row['Service'] === 'Color') {
            $colors[] = $row;
        }
    }
}

// Fetch active shaves for dropdown
$shaves = [];
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $shaveResult = $conn->query("SELECT Name, Price FROM haircut WHERE Status = 'active' AND Service = 'Shave' AND adminID = $adminID AND shopID = $barbershopID");
} else {
    $shaveResult = $conn->query("SELECT Name, Price FROM haircut WHERE Status = 'active' AND Service = 'Shave' AND adminID = $adminID");
}
if ($shaveResult && $shaveResult->num_rows > 0) {
    while ($row = $shaveResult->fetch_assoc()) {
        $shaves[] = $row;
    }
}
// Fetch active massages for dropdown
$massages = [];
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $massageResult = $conn->query("SELECT Name, Price FROM haircut WHERE Status = 'active' AND Service = 'Massage' AND adminID = $adminID AND shopID = $barbershopID");
} else {
    $massageResult = $conn->query("SELECT Name, Price FROM haircut WHERE Status = 'active' AND Service = 'Massage' AND adminID = $adminID");
}

if ($massageResult && $massageResult->num_rows > 0) {
    while ($row = $massageResult->fetch_assoc()) {
        $massages[] = $row;
    }
}

// Fetch barbers (employees) for the current barbershop
$barbers = [];
if (isset($_SESSION['barbershopID'])) {
    $barbershopID = (int)$_SESSION['barbershopID'];
    $today = date('l'); // e.g., 'Monday'
    $currentDate = date('Y-m-d');
    $barberResult = $conn->query(
        "SELECT e.employeeID, e.FirstName, e.LastName 
         FROM employee e 
         INNER JOIN employee_availability a ON e.employeeID = a.employee_id 
         WHERE e.shopID = $barbershopID 
           AND e.Status = 'active' 
           AND a.day_of_week = '$today' 
           AND e.employeeID NOT IN (
               SELECT employee_id FROM employee_unavailability WHERE unavailable_date = '$currentDate'
           )
         GROUP BY e.employeeID"
    );
    if ($barberResult && $barberResult->num_rows > 0) {
        while ($row = $barberResult->fetch_assoc()) {
            $barbers[] = $row;
        }
    }
}

// --- Barber queue count logic ---
$barberQueueCounts = [];
if (!empty($barbers)) {
    $adminID = (int)$_SESSION['adminID'];
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    // Build a list of barber full names
    $barberNames = [];
    foreach ($barbers as $barber) {
        $fullname = $barber['FirstName'] . ' ' . $barber['LastName'];
        $barberNames[] = $conn->real_escape_string($fullname);
    }
    // Query queue counts for all barbers at once
    if (count($barberNames) > 0) {
        $inClause = "'" . implode("','", $barberNames) . "'";
        $result = $conn->query("SELECT barber, COUNT(*) as cnt FROM queue WHERE status = 'In Queue' AND adminID = $adminID AND shopID = $barbershopID AND barber IN ($inClause) GROUP BY barber");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $barberQueueCounts[$row['barber']] = (int)$row['cnt'];
            }
        }
    }
    // Ensure all barbers have a count (even if 0)
    foreach ($barberNames as $fullname) {
        if (!isset($barberQueueCounts[$fullname])) {
            $barberQueueCounts[$fullname] = 0;
        }
    }
}

// --- Barber and Date filter logic ---
$selectedBarber = '';
$selectedDateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'today';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['barber']) && $_GET['barber'] !== '') {
        $selectedBarber = $_GET['barber'];
    } elseif (!empty($barbers)) {
        // Default to first barber if not selected
        $selectedBarber = $barbers[0]['FirstName'] . ' ' . $barbers[0]['LastName'];
    }
    if (isset($_GET['date_filter']) && in_array($_GET['date_filter'], ['today', 'upcoming'])) {
        $selectedDateFilter = $_GET['date_filter'];
    }
}
// Add this for POST requests to preserve barber filter and date filter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['barber'])) {
        $selectedBarber = $_POST['barber'];
    }
    if (isset($_POST['date_filter']) && in_array($_POST['date_filter'], ['today', 'upcoming'])) {
        $selectedDateFilter = $_POST['date_filter'];
    }
}

// Add validation for required haircut/color/shave/massage selection
$error_message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_queue'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $services = isset($_POST['service']) ? $_POST['service'] : [];
        $price = (float)$_POST['price'];
        $currentBarberFilter = isset($_POST['current_barber_filter']) ? $_POST['current_barber_filter'] : '';
    // Use Manila timezone for date_time
    $date_time = date('Y-m-d H:i:s');
    $status = 'In Queue';
    $adminID = (int)$_SESSION['adminID'];

    $Haircut_Name = isset($_POST['haircut']) && $_POST['haircut'] !== '' ? $conn->real_escape_string($_POST['haircut']) : 'N/A';
    $Color_Name = isset($_POST['color']) && $_POST['color'] !== '' ? $conn->real_escape_string($_POST['color']) : 'N/A';
    $Shave_Name = isset($_POST['shave']) && $_POST['shave'] !== '' ? $conn->real_escape_string($_POST['shave']) : 'N/A';
    $Massage_Name = isset($_POST['massage']) && $_POST['massage'] !== '' ? $conn->real_escape_string($_POST['massage']) : 'N/A';

    $barber = isset($_POST['barber']) && $_POST['barber'] !== '' ? $conn->real_escape_string($_POST['barber']) : 'Unassigned';
    $valid = true;

    // Validate service selection
    if (empty($services)) {
        $error_message = 'Please select at least one service.';
        $valid = false;
    }

    // Validate each service type
    if (in_array('haircut', $services) && $Haircut_Name === 'N/A') {
        $error_message = 'Please select a haircut.';
        $valid = false;
    }
    if (in_array('coloring', $services) && $Color_Name === 'N/A') {
        $error_message = 'Please select a color.';
        $valid = false;
    }
    if (in_array('shave', $services) && $Shave_Name === 'N/A') {
        $error_message = 'Please select a shave.';
        $valid = false;
    }
    if (in_array('massage', $services) && $Massage_Name === 'N/A') {
        $error_message = 'Please select a massage.';
        $valid = false;
    }

    // Validate barber
    if ($barber === '') {
        $error_message = 'Please select a barber.';
        $valid = false;
    }

    // Insert to DB if valid
        if ($valid) {
            $service_str = [];
            if (in_array('haircut', $services)) $service_str[] = 'Haircut';
            if (in_array('coloring', $services)) $service_str[] = 'Coloring';
            if (in_array('shave', $services)) $service_str[] = 'Shave';
            if (in_array('massage', $services)) $service_str[] = 'Massage';
            $service = implode(', ', $service_str);

            $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;

            // Insert into queue (add Massage_Name column)
            $sql = "INSERT INTO queue (name, service, barber, price, date_time, status, adminID, shopID, Haircut_Name, Color_Name, Shave_Name, Massage_Name) 
                    VALUES ('$name', '$service', '$barber', $price, '$date_time', '$status', $adminID, $barbershopID, 
                            '$Haircut_Name', '$Color_Name', '$Shave_Name', '$Massage_Name')";

            if (!$conn->query($sql)) {
                die('MySQL Error: ' . $conn->error);
            }

            // Redirect to preserve barber filter
            $redirectBarber = urlencode($currentBarberFilter);
            header("Location: queueing.php?barber=$redirectBarber");
            exit();
        }
}


// Handle bill action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bill_id'])) {
    $bill_id = (int)$_POST['bill_id'];
    $adminID = (int)$_SESSION['adminID'];
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $barberFilter = isset($_POST['barber']) ? $_POST['barber'] : $selectedBarber;

    // Get the queue entry
    $result = $conn->query("SELECT * FROM queue WHERE QueueID = $bill_id AND adminID = $adminID AND shopID = $barbershopID");
    if ($result && $row = $result->fetch_assoc()) {
        // Insert into sales (now includes barber, shave, massage)
        $name = $conn->real_escape_string($row['name']);
        $service = $conn->real_escape_string($row['service']);
        $barber = isset($row['barber']) ? $conn->real_escape_string($row['barber']) : '';
        $price = (float)$row['price'];
        $date_time = $conn->real_escape_string($row['date_time']);
        $status = 'Completed';
        $haircut_name = isset($row['Haircut_Name']) ? $conn->real_escape_string($row['Haircut_Name']) : '';
        $color_name = isset($row['Color_Name']) ? $conn->real_escape_string($row['Color_Name']) : '';
        $shave_name = isset($row['Shave_Name']) ? $conn->real_escape_string($row['Shave_Name']) : '';
        $massage_name = isset($row['Massage_Name']) ? $conn->real_escape_string($row['Massage_Name']) : '';

        // ✅ Insert Massage_Name into the sales table
        $sql = "INSERT INTO sales (
                    sales_name, 
                    sales_service, 
                    barber, 
                    sales_price, 
                    sales_dateTime, 
                    sales_status, 
                    adminID, 
                    haircut_name, 
                    color_name, 
                    shave_name, 
                    massage_name, 
                    shopID
                ) VALUES (
                    '$name', 
                    '$service', 
                    '$barber', 
                    $price, 
                    '$date_time', 
                    '$status', 
                    $adminID, 
                    '$haircut_name', 
                    '$color_name', 
                    '$shave_name', 
                    '$massage_name', 
                    $barbershopID
                )";

        if ($conn->query($sql)) {
            // Delete from queue after successful insertion
            $conn->query("DELETE FROM queue WHERE QueueID = $bill_id AND adminID = $adminID AND shopID = $barbershopID");
            header("Location: queueing.php?barber=" . urlencode($barberFilter));
            exit();
        } else {
            die('Error moving to sales: ' . $conn->error);
        }
    }
}

// Handle remove action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $remove_id = (int)$_POST['remove_id'];
    $adminID = (int)$_SESSION['adminID'];
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $barberFilter = isset($_POST['barber']) ? $_POST['barber'] : $selectedBarber;
    $conn->query("DELETE FROM queue WHERE QueueID = $remove_id AND adminID = $adminID AND shopID = $barbershopID");
    header("Location: queueing.php?barber=" . urlencode($barberFilter));
    exit();
}

// Handle assign action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_id']) && isset($_POST['assign_barber'])) {
    $assign_id = (int)$_POST['assign_id'];
    $assign_barber = $conn->real_escape_string($_POST['assign_barber']);
    $adminID = (int)$_SESSION['adminID'];
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $conn->query("UPDATE queue SET barber = '$assign_barber' WHERE QueueID = $assign_id AND adminID = $adminID AND shopID = $barbershopID");
    header("Location: queueing.php?barber=" . urlencode($assign_barber));
    exit();
}

// Handle edit action (update queue record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $name = $conn->real_escape_string($_POST['edit_name']);
    // FIX: Convert array to string before escaping
    $serviceArr = isset($_POST['edit_service']) ? $_POST['edit_service'] : [];
    $service_str = [];
    if (in_array('haircut', $serviceArr)) $service_str[] = 'Haircut';
    if (in_array('coloring', $serviceArr)) $service_str[] = 'Coloring';
    if (in_array('shave', $serviceArr)) $service_str[] = 'Shave';
    if (in_array('massage', $serviceArr)) $service_str[] = 'Massage';
    $service = $conn->real_escape_string(implode(', ', $service_str));

    $barber = isset($_POST['edit_barber']) && $_POST['edit_barber'] !== '' ? $conn->real_escape_string($_POST['edit_barber']) : 'Unassigned';
    $price = (float)$_POST['edit_price'];
    $Haircut_Name = isset($_POST['edit_haircut']) && $_POST['edit_haircut'] !== '' ? $conn->real_escape_string($_POST['edit_haircut']) : 'N/A';
    $Color_Name = isset($_POST['edit_color']) && $_POST['edit_color'] !== '' ? $conn->real_escape_string($_POST['edit_color']) : 'N/A';
    $Shave_Name = isset($_POST['edit_shave']) && $_POST['edit_shave'] !== '' ? $conn->real_escape_string($_POST['edit_shave']) : 'N/A';
    $Massage_Name = isset($_POST['edit_massage']) && $_POST['edit_massage'] !== '' ? $conn->real_escape_string($_POST['edit_massage']) : 'N/A';

    $adminID = (int)$_SESSION['adminID'];
    $barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
    $barberFilter = isset($_POST['edit_barber']) ? $_POST['edit_barber'] : $selectedBarber;

    $sql = "UPDATE queue SET 
                name = '$name',
                service = '$service',
                barber = '$barber',
                price = $price,
                Haircut_Name = '$Haircut_Name',
                Color_Name = '$Color_Name',
                Shave_Name = '$Shave_Name',
                Massage_Name = '$Massage_Name'
            WHERE QueueID = $edit_id AND adminID = $adminID AND shopID = $barbershopID";
    $conn->query($sql);
    header("Location: queueing.php?barber=" . urlencode($barberFilter));
    exit();
}
// Build service-to-barber mapping
$serviceNameBarbers = [
    'haircut' => [],
    'coloring' => [],
    'shave' => [],
    'massage' => []
];

foreach (['Haircut', 'Color', 'Shave', 'Massage'] as $serviceType) {
    $serviceKey = $serviceType === 'Color' ? 'coloring' : strtolower($serviceType);
    $sql = "SELECT h.Name, hb.EmployeeID, e.FirstName, e.LastName
            FROM haircut h
            INNER JOIN haircut_barbers hb ON h.HaircutID = hb.HaircutID
            INNER JOIN employee e ON hb.EmployeeID = e.EmployeeID
            WHERE h.Service = '$serviceType' AND h.Status = 'active' AND h.shopID = $barbershopID";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $serviceName = $row['Name'];
            if (!isset($serviceNameBarbers[$serviceKey][$serviceName])) {
                $serviceNameBarbers[$serviceKey][$serviceName] = [];
            }
            $serviceNameBarbers[$serviceKey][$serviceName][] = [
                'EmployeeID' => $row['EmployeeID'],
                'FirstName' => $row['FirstName'],
                'LastName' => $row['LastName']
            ];
        }
    }
}

// Filter by today's available barbers
$availableBarberIDs = array_map(function($b) { return $b['employeeID']; }, $barbers);
foreach ($serviceNameBarbers as $serviceKey => $serviceMap) {
    foreach ($serviceMap as $serviceName => $barberList) {
        $serviceNameBarbers[$serviceKey][$serviceName] = array_values(array_filter($barberList, function($barber) use ($availableBarberIDs) {
            return in_array($barber['EmployeeID'], $availableBarberIDs);
        }));
    }
}

// Pass to JS
echo "<script>var serviceNameBarbers = " . json_encode($serviceNameBarbers) . ";</script>";
echo "<!-- DEBUG FADED: " . (isset($serviceNameBarbers['haircut']['Faded']) ? print_r($serviceNameBarbers['haircut']['Faded'], true) : 'N/A') . " -->";
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translations['queue_service'] ?> | Admin Dashboard</title>
    <link rel="stylesheet" href="joinus.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
var serviceBarbers = <?= json_encode($serviceBarbers) ?>;
</script>
    <style>
    body {
        background-color: #e6f7ff;
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
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
        padding:2rem 1.5rem;
        min-width:180px;
        max-width:260px;
        flex:0 0 220px;
        margin-top:0;
        box-sizing: border-box;
    }
    .sidebar-header {
        display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; width:100%;
    }
    .sidebar-header img {
        height:60px; display:block; margin:0 auto;
    }
    .nav-links {
        list-style:none; padding:0; margin:2rem 0 0 0; display:flex; flex-direction:column; gap:1rem;
    }
    .nav-links li a {
        color:#008bcc; font-weight:500; text-decoration:none; transition: background 0.2s, color 0.2s;
        padding:0.4rem 1rem; border-radius:8px;
        font-size:1rem;
    }
    .nav-links li a.active, .nav-links li a:hover {
        background:#e6f7ff; color:#0077b6;
    }
    .nav-links .logout a { color:#dc3545; }
    .main-content {
        flex:1; background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); padding:2rem 2vw 2.5rem 2vw; min-width:0;
        margin-top:0;
        box-sizing: border-box;
    }
    .queue-container {
        width:100%;
    }
    .queue-list {
        background:#f7fbff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.04); padding:1.5rem 1vw; margin-top:1rem;
        box-sizing: border-box;
    }
    table {
        width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,0.04);
        font-size:0.98rem;
    }
    th, td {
        padding:0.7rem 0.5rem; text-align:center; border-bottom:1px solid #e0e0e0;
    }
    th {
        background:#e6f7ff; color:#0077b6; font-weight:600;
    }
    tr:last-child td { border-bottom:none; }
    /* Modal styles for Add to Queue */
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
        padding: 2rem 1.5rem 1.5rem 1.5rem;
        border: none;
        width: 400px;
        max-width: 98vw;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
        margin: 0 auto;
        box-sizing: border-box;
        /* --- Make modal scrollable --- */
        max-height: 90vh;
        overflow-y: auto;
        /* --- Hide scrollbar for Chrome, Edge, Safari --- */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE and Edge */
    }
    .modal-content::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }
    .close {
        color: #aaa;
        position: absolute;
        right: 16px;
        top: 8px;
        font-size: 1.8rem;
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
        margin-bottom: 1rem;
        font-size: 1.2rem;
        font-weight: 700;
        text-align: center;
    }
    .modal-content form {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.7rem;
        width: 100%;
    }
    .modal-content form label {
        text-align: left;
        font-weight: 500;
        font-size: 0.98rem;
        margin-bottom: -10px;
        align-self: flex-start;
        width: 100%;
        display: block;
    }
    .modal-content form input[type="text"],
    .modal-content form input[type="number"],
    .modal-content form select {
        width: 100%;
        max-width: 350px;
        padding: 0.5rem 0.5rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 2px;
        box-sizing: border-box;
    }
    .modal-content form button[type="submit"] {
        margin-top: 10px;
        background: #00aaff;
        color: #fff;
        border: none;
        padding: 0.7rem 0;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        width: 100%;
        max-width: 280px;
        align-self: center;
        transition: background 0.2s;
    }
    .modal-content form button[type="submit"]:hover {
        background: #0077b6;
    }
    .modal-content form .error-message {
        color: red;
        margin-bottom: 10px;
        font-weight: bold;
        text-align: center;
        width: 100%;
    }
    .modal-content form #serviceCheckboxes {
        margin-bottom: 2px;
        width: 100%;
        max-width: 350px;
    }
    @media (max-width: 1100px) {
        .main-content {
            padding: 1rem 1vw 1.5rem 1vw;
        }
        .sidebar {
            min-width: 140px;
            padding: 1.2rem 0.7rem;
        }
        .queue-list {
            padding: 1rem 0.5vw;
        }
        table {
            font-size: 0.95rem;
        }
    }
    @media (max-width: 800px) {
        .main-header {
            padding: 8px 0 6px 0;
        }
        .sidebar {
            min-width: 100px;
            max-width: 160px;
            padding: 0.7rem 0.3rem;
        }
        .main-content {
            padding: 0.7rem 0.5vw 1rem 0.5vw;
        }
        .queue-list {
            padding: 0.7rem 0.2vw;
        }
        table th, table td {
            padding: 0.4rem 0.2rem;
        }
    }
    @media (max-width: 600px) {
        .main-header {
            padding: 6px 0 4px 0;
        }
        .sidebar {
            display: none;
        }
        .main-content {
            padding: 0.5rem 0.2vw 0.7rem 0.2vw;
        }
        .queue-list {
            padding: 0.5rem 0.1vw;
        }
        table {
            font-size: 0.9rem;
        }
        .modal-content {
            width: 98vw;
            max-width: 98vw;
            padding: 1rem 0.5rem;
        }
        .modal-content form {
            grid-template-columns: 1fr;
        }
        .modal-content form label {
            text-align: left;
        }
    }
    @media (max-width: 450px) {
        .main-header span {
            font-size: 1.2rem !important;
        }
        .modal-content {
            padding: 0.5rem 0.2rem;
        }
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
    <main class="main-content" style="display:flex;flex-direction:column;justify-content:flex-start;min-height:calc(100vh - 120px);">
            <section class="queue-container" style="flex:1;display:flex;flex-direction:column;justify-content:flex-start;">
                <!-- Modal for Add to Queue -->
                <div id="queueModal" class="modal">
                    <div class="modal-content">
                        <span class="close" id="closeQueueModal">&times;</span>
                        <h2><?= $translations['add_to_queue'] ?? 'Add to Queue' ?></h2>
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message"> <?= htmlspecialchars($error_message) ?> </div>
                        <?php endif; ?>
                        <form id="queueForm" method="post">
                            <label for="name"><?= $translations['name'] ?? 'Name' ?>:</label>
                            <input type="text" id="name" name="name" placeholder="<?= $translations['enter_name'] ?? 'Enter name' ?>" required>
                            <!-- Hidden input to store current barber filter -->
                            <input type="hidden" id="current_barber_filter" name="current_barber_filter" value="<?= htmlspecialchars($selectedBarber ?? '') ?>">

                            <label><?= $translations['service'] ?? 'Service' ?>:</label>
                            <div id="serviceCheckboxes" style="width:100%;display:flex;flex-direction:column;gap:8px;margin-bottom:2px;">
                                <label for="service_haircut" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="service[]" value="haircut" id="service_haircut">
                                    Haircut
                                </label>
                                <label for="service_coloring" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="service[]" value="coloring" id="service_coloring">
                                    Coloring
                                </label>
                                <label for="service_shave" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="service[]" value="shave" id="service_shave">
                                    Shave
                                </label>
                                <label for="service_massage" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="service[]" value="massage" id="service_massage">
                                    Massage
                                </label>
                            </div>

                            <label for="haircut" id="haircutLabel" style="display:none;">Haircut:</label>
                            <select id="haircut" name="haircut" style="display:none;">
                                <option value="">Select a haircut</option>
                                <?php foreach ($haircuts as $hc): ?>
                                    <option value="<?= htmlspecialchars($hc['Name']) ?>" data-price="<?= htmlspecialchars($hc['Price']) ?>">
                                        <?= htmlspecialchars($hc['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="color" id="colorLabel" style="display:none;">Color:</label>
                            <select id="color" name="color" style="display:none;">
                                <option value="">Select a color</option>
                                <?php foreach ($colors as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl['Name']) ?>" data-price="<?= htmlspecialchars($cl['Price']) ?>">
                                        <?= htmlspecialchars($cl['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="shave" id="shaveLabel" style="display:none;">Shave:</label>
                            <select id="shave" name="shave" style="display:none;">
                                <option value="">Select a shave</option>
                                <?php foreach ($shaves as $sv): ?>
                                    <option value="<?= htmlspecialchars($sv['Name']) ?>" data-price="<?= htmlspecialchars($sv['Price']) ?>">
                                        <?= htmlspecialchars($sv['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="massage" id="massageLabel" style="display:none;">Massage:</label>
                            <select id="massage" name="massage" style="display:none;">
                                <option value="">Select a massage</option>
                                <?php foreach ($massages as $sv): ?>
                                    <option value="<?= htmlspecialchars($sv['Name']) ?>" data-price="<?= htmlspecialchars($sv['Price']) ?>">
                                        <?= htmlspecialchars($sv['Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="barber">Barber (optional):</label>
                            <select id="barber" name="barber">
    <option value="">Unassigned</option>
    <!-- Options will be filled by JS -->
</select>

                            <label for="price">Price:</label>
                            <input type="number" id="price" name="price" placeholder="Enter price" min="0" step="0.01" required>

                            <button type="submit" name="add_queue">Add</button>
                        </form>
                    </div>
                </div>
                <!-- End Modal -->
                <div class="queue-list" style="margin-top:0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="margin: 0; display: flex; align-items: center;">
                            <?= $translations['current_queue'] ?? 'Current Queue' ?>
                        </h2>
                        <!-- Move barber filter and button into a flex row -->
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <form method="GET" action="" style="display:flex;align-items:center;gap:10px;">
                                <label for="barberFilter" style="font-weight:600;">Select Barber:</label>
                                <select name="barber" id="barberFilter" onchange="this.form.submit()" 
                                        style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;" required>
                                    <?php foreach ($barbers as $barber): 
                                        $fullname = htmlspecialchars($barber['FirstName'] . ' ' . $barber['LastName']);
                                        $selected = ($selectedBarber === $fullname) ? 'selected' : '';
                                        $count = isset($barberQueueCounts[$barber['FirstName'] . ' ' . $barber['LastName']]) ? $barberQueueCounts[$barber['FirstName'] . ' ' . $barber['LastName']] : 0;
                                    ?>
                                        <option value="<?= $fullname ?>" <?= $selected ?>>
                                            <?= $fullname ?> (<?= $count ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="dateFilter" style="font-weight:600;">Show:</label>
                                <select name="date_filter" id="dateFilter" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
                                    <option value="today" <?= $selectedDateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="upcoming" <?= $selectedDateFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                    <option value="pastdue" <?= $selectedDateFilter === 'pastdue' ? 'selected' : '' ?>>Past Due</option>
                                </select>
                            </form>
                            <button id="openQueueModal" type="button" style="background: #007bff; color: #fff; border: none; width:30px; height:30px; border-radius: 4px; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
<style>
.queue-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}
.queue-card {
  background: #f7fbff;
  border-radius: 18px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  padding: 1.2rem 1rem 1rem 1rem;
  display: flex;
  flex-direction: column;
  position: relative;
  border: 1px solid #e6f7ff;
  transition: box-shadow 0.2s;
}
.queue-card:hover {
  box-shadow: 0 6px 24px rgba(0,170,255,0.10);
}
.queue-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}
.queue-card-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: #0077b6;
}
.queue-card-status {
  font-size: 0.95rem;
  font-weight: 500;
  color: #00aaff;
  background: #e6f7ff;
  border-radius: 8px;
  padding: 2px 10px;
}
.queue-card-body {
  font-size: 0.98rem;
  color: #222;
  margin-bottom: 0.7rem;
}
.queue-card-label {
  font-weight: 500;
  color: #008bcc;
}
.queue-card-actions {
  display: flex;
  gap: 10px;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}
.queue-card-actions form, .queue-card-actions button {
  margin: 0;
}
.queue-card .assign-btn {
  background-color: #007bff;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.queue-card .edit-btn {
  background-color: #ffc107;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.queue-card .bill-btn {
  background-color: #1fb72e;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.queue-card .remove-btn {
  background-color: #c52424;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.queue-card .cancel-btn {
  background-color: #6c757d;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.queue-card-info-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1.2rem;
  margin-bottom: 0.3rem;
}
.queue-card-info-row > div {
  min-width: 120px;
}
.queue-card-label {
  font-size: 0.97rem;
  color: #008bcc;
  font-weight: 500;
}
.queue-card-value {
  font-size: 0.97rem;
  color: #222;
  font-weight: 400;
}
@media (max-width: 600px) {
  .queue-card-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  .queue-card {
    padding: 1rem 0.5rem;
  }
}
</style>
<div class="queue-card-grid">
<?php
$adminID = (int)$_SESSION['adminID'];
$barbershopID = isset($_SESSION['barbershopID']) ? (int)$_SESSION['barbershopID'] : 0;
$selectedBarberEscaped = $conn->real_escape_string($selectedBarber);
// Get the earliest date_time for the current queue (for this barber/shop)
$todayDate = date('Y-m-d');
$nowDateTime = date('Y-m-d H:i:s');
if ($selectedDateFilter === 'today') {
    $queueQuery = "SELECT * FROM queue WHERE adminID = $adminID AND shopID = $barbershopID 
        AND (barber = '$selectedBarberEscaped' OR barber = 'Unassigned')
        AND DATE(date_time) = '$todayDate' ORDER BY QueueID ASC";
} else if ($selectedDateFilter === 'upcoming') {
    $queueQuery = "SELECT * FROM queue WHERE adminID = $adminID AND shopID = $barbershopID 
        AND (barber = '$selectedBarberEscaped' OR barber = 'Unassigned')
        AND DATE(date_time) > '$todayDate' ORDER BY QueueID ASC";
} else if ($selectedDateFilter === 'pastdue') {
    // We'll filter past due in PHP after fetching all relevant records
    $queueQuery = "SELECT * FROM queue WHERE adminID = $adminID AND shopID = $barbershopID 
        AND (barber = '$selectedBarberEscaped' OR barber = 'Unassigned') ORDER BY QueueID ASC";
} else {
    $queueQuery = "SELECT * FROM queue WHERE adminID = $adminID AND shopID = $barbershopID 
        AND (barber = '$selectedBarberEscaped' OR barber = 'Unassigned') ORDER BY QueueID ASC";
}
$result = $conn->query($queueQuery);
if ($result && $result->num_rows > 0) {
    $idx = 1;
    // Find the earliest date_time in the queue
    $earliestResult = $conn->query("SELECT MIN(date_time) as min_time FROM queue WHERE adminID = $adminID AND shopID = $barbershopID AND (barber = '$selectedBarberEscaped' OR barber = 'Unassigned')");
    if ($earliestResult && ($earliestRow = $earliestResult->fetch_assoc()) && !empty($earliestRow['min_time'])) {
        $queueStart = new DateTime($earliestRow['min_time']);
    } else {
        $queueStart = new DateTime(); // fallback
    }
    $queuePointer = clone $queueStart;
    $rows = [];
    while($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    // For 'pastdue', filter only those whose end time is before now
    if ($selectedDateFilter === 'pastdue') {
        $now = new DateTime();
        $filteredRows = [];
        $queuePointerPast = clone $queueStart;
        foreach ($rows as $row) {
            $duration = 0;
            if (!empty($row['Haircut_Name']) && $row['Haircut_Name'] !== 'N/A') $duration += 30;
            if (!empty($row['Color_Name']) && $row['Color_Name'] !== 'N/A') $duration += 150;
            if (!empty($row['Shave_Name']) && $row['Shave_Name'] !== 'N/A') $duration += 20;
            if (!empty($row['Massage_Name']) && $row['Massage_Name'] !== 'N/A') $duration += 20;
            $slotStart = clone $queuePointerPast;
            $slotEnd = clone $queuePointerPast;
            $slotEnd->modify("+{$duration} minutes");
            $queuePointerPast->modify("+{$duration} minutes");
            if ($slotEnd < $now) {
                $row['__slotStart'] = clone $slotStart;
                $row['__slotEnd'] = clone $slotEnd;
                $filteredRows[] = $row;
            }
        }
        $rows = $filteredRows;
        $queuePointer = $queueStart = new DateTime(); // reset for display
    }
    foreach ($rows as $row) {
        $service_display = [];
        $duration = 0;
        if (!empty($row['Haircut_Name']) && $row['Haircut_Name'] !== 'N/A') {
            $service_display[] = 'Haircut';
            $duration += 30;
        }
        if (!empty($row['Color_Name']) && $row['Color_Name'] !== 'N/A') {
            $service_display[] = 'Coloring';
            $duration += 150;
        }
        if (!empty($row['Shave_Name']) && $row['Shave_Name'] !== 'N/A') {
            $service_display[] = 'Shave';
            $duration += 20;
        }
        if (!empty($row['Massage_Name']) && $row['Massage_Name'] !== 'N/A') {
            $service_display[] = 'Massage';
            $duration += 20;
        }
        $service_str = !empty($service_display)
            ? implode(', ', $service_display)
            : htmlspecialchars(ucfirst($row['service'] ?? ''));
        $isUnassigned = ($row['barber'] === 'Unassigned');
        // For 'pastdue', use precomputed slot times
        if ($selectedDateFilter === 'pastdue' && isset($row['__slotStart']) && isset($row['__slotEnd'])) {
            $slotStart = $row['__slotStart'];
            $slotEnd = $row['__slotEnd'];
        } else {
            $slotStart = clone $queuePointer;
            $slotEnd = clone $queuePointer;
            $slotEnd->modify("+{$duration} minutes");
            $queuePointer->modify("+{$duration} minutes");
        }
        $month = ucfirst(strtolower($slotStart->format('F')));
        $dateStr = $month . $slotStart->format(' d, Y');
        $startStr = $slotStart->format('h:ia');
        $endStr = $slotEnd->format('h:ia');
        $startStr = str_replace(['12:00pm', '12:00am'], ['12:00nn', '12:00mn'], $startStr);
        $endStr = str_replace(['12:00pm', '12:00am'], ['12:00nn', '12:00mn'], $endStr);
        $formattedSlot = "$dateStr, $startStr-$endStr";
        $now = new DateTime();
        $isPastSlot = $slotEnd < $now;
        $status_display = htmlspecialchars($row['status'] ?? '');
        $status_class = 'queue-card-status';
        $status_style = '';
        if (($row['status'] ?? '') === 'In Queue' && $isPastSlot) {
            $status_display = 'Overdue';
            $status_style = 'color:#dc3545;background:#ffe6e6;';
        }
        echo '<div class="queue-card">';
        // Queue number badge and name side by side
        echo '<div class="queue-card-header" style="margin-bottom:0.7rem;display:flex;align-items:center;gap:10px;">';
        echo '<span style="background:#00aaff;color:#fff;font-weight:700;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 2px 8px rgba(0,170,255,0.10);">' . $idx . '</span>';
        echo '<span class="queue-card-title" style="font-size:1.2rem;">' . htmlspecialchars($row['name'] ?? '') . '</span>';
        echo '<span class="' . $status_class . '" style="' . $status_style . '">' . $status_display . '</span>';
        echo '</div>';
        echo '<div class="queue-card-body">';
        // Services and Barber
        echo '<div style="margin-bottom:0.5rem;">';
        echo '<span class="queue-card-label">Services:</span> <span class="queue-card-value">' . htmlspecialchars($service_str) . '</span><br>';
        echo '<span class="queue-card-label">Barber:</span> <span class="queue-card-value">' . ($isUnassigned ? '<span style="color:#dc3545;font-weight:600;">Unassigned</span>' : htmlspecialchars($row['barber'] ?? '')) . '</span>';
        echo '</div>';
        // Details in two columns
        echo '<div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-bottom:0.5rem;">';
        echo '<div><span class="queue-card-label">Haircut:</span> <span class="queue-card-value">' . htmlspecialchars($row['Haircut_Name'] ?? '') . '</span></div>';
        echo '<div><span class="queue-card-label">Color:</span> <span class="queue-card-value">' . htmlspecialchars($row['Color_Name'] ?? '') . '</span></div>';
        echo '<div><span class="queue-card-label">Shave:</span> <span class="queue-card-value">' . htmlspecialchars($row['Shave_Name'] ?? '') . '</span></div>';
        echo '<div><span class="queue-card-label">Massage:</span> <span class="queue-card-value">' . htmlspecialchars($row['Massage_Name'] ?? '') . '</span></div>';
        echo '</div>';
        // Price and Est. Time
        echo '<div style="margin-bottom:0.5rem;">';
        echo '<span class="queue-card-label">Price:</span> <span class="queue-card-value">₱' . htmlspecialchars($row['price'] ?? '') . '</span><br>';
        echo '<span class="queue-card-label">Est. Time:</span> <span class="queue-card-value">' . htmlspecialchars($formattedSlot) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="queue-card-actions">';
            $confirmText = $isPastSlot ? 'Are you sure you want to cancel this record?' : 'Are you sure you want to remove this record?';
            $btnClass = $isPastSlot ? 'cancel-btn' : 'remove-btn';
            $iconHtml = $isPastSlot ? '<i class="fa-solid fa-ban"></i>' : '<i class="fa-solid fa-trash"></i>';
            echo '<form method="post"><input type="hidden" name="remove_id" value="' . ($row['QueueID'] ?? '') . '"><input type="hidden" name="barber" value="' . htmlspecialchars($selectedBarber ?? '') . '"><button type="submit" name="remove" class="' . $btnClass . '" onclick="return confirm(\'' . $confirmText . '\')">' . $iconHtml . ' Remove</button></form>';
            echo '<form method="post"><input type="hidden" name="bill_id" value="' . ($row['QueueID'] ?? '') . '"><input type="hidden" name="barber" value="' . htmlspecialchars($selectedBarber ?? '') . '"><button type="submit" name="bill" class="bill-btn" onclick="return confirm(\'Are you sure you want to bill this record?\')"><i class="fa-solid fa-receipt"></i> Bill</button></form>';
            echo '<button type="button" class="edit-btn" data-id="' . ($row['QueueID'] ?? '') . '" data-name="' . htmlspecialchars($row['name'] ?? '', ENT_QUOTES) . '" data-service="' . htmlspecialchars($row['service'] ?? '', ENT_QUOTES) . '" data-barber="' . htmlspecialchars($row['barber'] ?? '', ENT_QUOTES) . '" data-price="' . htmlspecialchars($row['price'] ?? '', ENT_QUOTES) . '" data-haircut="' . htmlspecialchars($row['Haircut_Name'] ?? '', ENT_QUOTES) . '" data-color="' . htmlspecialchars($row['Color_Name'] ?? '', ENT_QUOTES) . '" data-shave="' . htmlspecialchars($row['Shave_Name'] ?? '', ENT_QUOTES) . '" data-massage="' . htmlspecialchars($row['Massage_Name'] ?? '', ENT_QUOTES) . '"><i class="fa-solid fa-pen-to-square"></i> Edit</button>';
            if ($isUnassigned) {
                $canAssign = false;
                if (!empty($row['Haircut_Name']) && $row['Haircut_Name'] !== 'N/A' && isset($serviceNameBarbers['haircut'][$row['Haircut_Name']])) {
                    foreach ($serviceNameBarbers['haircut'][$row['Haircut_Name']] as $barber) {
                        $fullname = $barber['FirstName'] . ' ' . $barber['LastName'];
                        if ($fullname === $selectedBarberEscaped) {
                            $canAssign = true;
                            break;
                        }
                    }
                }
                if (!$canAssign && !empty($row['Color_Name']) && $row['Color_Name'] !== 'N/A' && isset($serviceNameBarbers['coloring'][$row['Color_Name']])) {
                    foreach ($serviceNameBarbers['coloring'][$row['Color_Name']] as $barber) {
                        $fullname = $barber['FirstName'] . ' ' . $barber['LastName'];
                        if ($fullname === $selectedBarberEscaped) {
                            $canAssign = true;
                            break;
                        }
                    }
                }
                if (!$canAssign && !empty($row['Shave_Name']) && $row['Shave_Name'] !== 'N/A' && isset($serviceNameBarbers['shave'][$row['Shave_Name']])) {
                    foreach ($serviceNameBarbers['shave'][$row['Shave_Name']] as $barber) {
                        $fullname = $barber['FirstName'] . ' ' . $barber['LastName'];
                        if ($fullname === $selectedBarberEscaped) {
                            $canAssign = true;
                            break;
                        }
                    }
                }
                if (!$canAssign && !empty($row['Massage_Name']) && $row['Massage_Name'] !== 'N/A' && isset($serviceNameBarbers['massage'][$row['Massage_Name']])) {
                    foreach ($serviceNameBarbers['massage'][$row['Massage_Name']] as $barber) {
                        $fullname = $barber['FirstName'] . ' ' . $barber['LastName'];
                        if ($fullname === $selectedBarberEscaped) {
                            $canAssign = true;
                            break;
                        }
                    }
                }
                if ($canAssign) {
                    echo '<form method="post"><input type="hidden" name="assign_id" value="' . ($row['QueueID'] ?? '') . '"><input type="hidden" name="assign_barber" value="' . htmlspecialchars($selectedBarberEscaped ?? '') . '"><button type="submit" name="assign" class="assign-btn"><i class="fa-solid fa-user-check"></i> Assign</button></form>';
                }
            }
            echo '</div>';
            echo '</div>';
            $idx++;
    }
    if (count($rows) === 0) {
        echo '<div style="text-align:center;width:100%;font-size:1.1rem;color:#888;padding:2rem 0;">No result found</div>';
    }
} else {
    echo '<div style="text-align:center;width:100%;font-size:1.1rem;color:#888;padding:2rem 0;">No result found</div>';
}
?>
</div>
                </div>
            </section>
        </main>
    </div>

    <!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeEditModal">&times;</span>
        <h2>Edit Queue Record</h2>
        <form id="editForm" method="post">
            <input type="hidden" id="edit_id" name="edit_id">

            <label for="edit_name">Name:</label>
            <input type="text" id="edit_name" name="edit_name" required>

            <label>Service:</label>
            <div id="editServiceCheckboxes" style="width:100%;display:flex;flex-direction:column;gap:8px;margin-bottom:2px;">
                <label for="edit_service_haircut" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="edit_service[]" value="haircut" id="edit_service_haircut">
                    Haircut
                </label>
                <label for="edit_service_coloring" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="edit_service[]" value="coloring" id="edit_service_coloring">
                    Coloring
                </label>
                <label for="edit_service_shave" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="edit_service[]" value="shave" id="edit_service_shave">
                    Shave
                </label>
                <label for="edit_service_massage" style="font-weight:400;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="edit_service[]" value="massage" id="edit_service_massage">
                    Massage
                </label>
            </div>

            <label for="edit_haircut" id="edit_haircutLabel" style="display:none;">Haircut:</label>
            <select id="edit_haircut" name="edit_haircut" style="display:none;">
                <option value="">Select a haircut</option>
                <?php foreach ($haircuts as $hc): ?>
                    <option value="<?= htmlspecialchars($hc['Name']) ?>" data-price="<?= htmlspecialchars($hc['Price']) ?>">
                        <?= htmlspecialchars($hc['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="edit_color" id="edit_colorLabel" style="display:none;">Color:</label>
            <select id="edit_color" name="edit_color" style="display:none;">
                <option value="">Select a color</option>
                <?php foreach ($colors as $cl): ?>
                    <option value="<?= htmlspecialchars($cl['Name']) ?>" data-price="<?= htmlspecialchars($cl['Price']) ?>">
                        <?= htmlspecialchars($cl['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="edit_shave" id="edit_shaveLabel" style="display:none;">Shave:</label>
            <select id="edit_shave" name="edit_shave" style="display:none;">
                <option value="">Select a shave</option>
                <?php foreach ($shaves as $sv): ?>
                    <option value="<?= htmlspecialchars($sv['Name']) ?>" data-price="<?= htmlspecialchars($sv['Price']) ?>">
                        <?= htmlspecialchars($sv['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="edit_massage" id="edit_massageLabel" style="display:none;">Massage:</label>
            <select id="edit_massage" name="edit_massage" style="display:none;">
                <option value="">Select a massage</option>
                <?php foreach ($massages as $sv): ?>
                    <option value="<?= htmlspecialchars($sv['Name']) ?>" data-price="<?= htmlspecialchars($sv['Price']) ?>">
                        <?= htmlspecialchars($sv['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="edit_barber">Barber (optional):</label>
            <select id="edit_barber" name="edit_barber">
                <option value="">Unassigned</option>
                <?php foreach ($barbers as $barber): 
                    $fullname = htmlspecialchars($barber['FirstName'] . ' ' . $barber['LastName']);
                    $count = isset($barberQueueCounts[$barber['FirstName'] . ' ' . $barber['LastName']]) ? $barberQueueCounts[$barber['FirstName'] . ' ' . $barber['LastName']] : 0;
                ?>
                    <option value="<?= $fullname ?>"><?= $fullname ?> (<?= $count ?>)</option>
                <?php endforeach; ?>
            </select>

            <label for="edit_price">Price:</label>
            <input type="number" id="edit_price" name="edit_price" min="0" step="0.01" required>

            <button type="submit">Save</button>
        </form>
    </div>
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
            var haircutSelect = document.getElementById('haircut');
            var colorSelect = document.getElementById('color');
            var shaveSelect = document.getElementById('shave');
            var massageSelect = document.getElementById('massage');
            var priceInput = document.getElementById('price');
            var serviceHaircut = document.getElementById('service_haircut');
            var serviceColoring = document.getElementById('service_coloring');
            var serviceShave = document.getElementById('service_shave');
            var serviceMassage = document.getElementById('service_massage');
            var massageLabel = document.getElementById('massageLabel');

function updateBarberDropdown() {
    var barberSelect = document.getElementById('barber');
    barberSelect.innerHTML = '<option value="">Unassigned</option>';

    let selectedBarbers = [];

    // Haircut
    if (document.getElementById('service_haircut').checked) {
        let haircutName = document.getElementById('haircut').value;
        if (haircutName && serviceNameBarbers.haircut[haircutName]) {
            serviceNameBarbers.haircut[haircutName].forEach(function(barber) {
                selectedBarbers.push(barber);
            });
        }
    }
    // Coloring
    if (document.getElementById('service_coloring').checked) {
        let colorName = document.getElementById('color').value;
        if (colorName && serviceNameBarbers.coloring[colorName]) {
            serviceNameBarbers.coloring[colorName].forEach(function(barber) {
                selectedBarbers.push(barber);
            });
        }
    }
    // Shave
    if (document.getElementById('service_shave').checked) {
        let shaveName = document.getElementById('shave').value;
        if (shaveName && serviceNameBarbers.shave[shaveName]) {
            serviceNameBarbers.shave[shaveName].forEach(function(barber) {
                selectedBarbers.push(barber);
            });
        }
    }
    // Massage
    if (document.getElementById('service_massage').checked) {
        let massageName = document.getElementById('massage').value;
        if (massageName && serviceNameBarbers.massage[massageName]) {
            serviceNameBarbers.massage[massageName].forEach(function(barber) {
                selectedBarbers.push(barber);
            });
        }
    }

    // Remove duplicates by EmployeeID
    let uniqueBarbers = [];
    let seen = {};
    selectedBarbers.forEach(function(barber) {
        if (!seen[barber.EmployeeID]) {
            uniqueBarbers.push(barber);
            seen[barber.EmployeeID] = true;
        }
    });

    uniqueBarbers.forEach(function(barber) {
        var fullname = barber.FirstName + ' ' + barber.LastName;
        barberSelect.innerHTML += '<option value="' + fullname + '">' + fullname + '</option>';
    });
}

// Attach to service checkboxes and selects
['service_haircut', 'service_coloring', 'service_shave', 'service_massage',
 'haircut', 'color', 'shave', 'massage'].forEach(function(id) {
    document.getElementById(id).addEventListener('change', updateBarberDropdown);
});

// Initial fill
updateBarberDropdown();

            function updateServiceFields() {
                var haircutLabel = document.getElementById('haircutLabel');
                var colorLabel = document.getElementById('colorLabel');
                var shaveLabel = document.getElementById('shaveLabel');
                // Haircut
                if (serviceHaircut.checked) {
                    haircutLabel.style.display = 'block';
                    haircutSelect.style.display = 'block';
                } else {
                    haircutLabel.style.display = 'none';
                    haircutSelect.style.display = 'none';
                    haircutSelect.selectedIndex = 0;
                }
                // Coloring
                if (serviceColoring.checked) {
                    colorLabel.style.display = 'block';
                    colorSelect.style.display = 'block';
                } else {
                    colorLabel.style.display = 'none';
                    colorSelect.style.display = 'none';
                    colorSelect.selectedIndex = 0;
                }
                // Shave
                if (serviceShave.checked) {
                    shaveLabel.style.display = 'block';
                    shaveSelect.style.display = 'block';
                } else {
                    shaveLabel.style.display = 'none';
                    shaveSelect.style.display = 'none';
                    shaveSelect.selectedIndex = 0;
                }
                if (serviceMassage.checked) {
                 massageLabel.style.display = 'block';
                 massageSelect.style.display = 'block';
                } else {
                 massageLabel.style.display = 'none';
                 massageSelect.style.display = 'none';
                 massageSelect.selectedIndex = 0;
                }
                updatePrice();
            }

           function updatePrice() {
    var haircutPrice = 0;
    var colorPrice = 0;
    var shavePrice = 0;
    var massagePrice = 0; // ✅ New variable

    var selectedHaircut = haircutSelect.options[haircutSelect.selectedIndex];
    var selectedColor = colorSelect.options[colorSelect.selectedIndex];
    var selectedShave = shaveSelect.options[shaveSelect.selectedIndex];
    var selectedMassage = massageSelect.options[massageSelect.selectedIndex]; // ✅ New variable

    // Haircut
    if (serviceHaircut.checked && haircutSelect.value && selectedHaircut.getAttribute('data-price')) {
        haircutPrice = parseFloat(selectedHaircut.getAttribute('data-price')) || 0;
    }

    // Coloring
    if (serviceColoring.checked && colorSelect.value && selectedColor.getAttribute('data-price')) {
        colorPrice = parseFloat(selectedColor.getAttribute('data-price')) || 0;
    }

    // Shave
    if (serviceShave.checked && shaveSelect.value && selectedShave.getAttribute('data-price')) {
        shavePrice = parseFloat(selectedShave.getAttribute('data-price')) || 0;
    }

    // ✅ Massage
    if (serviceMassage.checked && massageSelect.value && selectedMassage.getAttribute('data-price')) {
        massagePrice = parseFloat(selectedMassage.getAttribute('data-price')) || 0;
    }

    // ✅ Total now includes massage
    var total = haircutPrice + colorPrice + shavePrice + massagePrice;
    priceInput.value = total > 0 ? total.toFixed(2) : '';
}


            serviceHaircut.addEventListener('change', updateServiceFields);
serviceColoring.addEventListener('change', updateServiceFields);
serviceShave.addEventListener('change', updateServiceFields);
serviceMassage.addEventListener('change', updateServiceFields); // ✅ NEW

haircutSelect.addEventListener('change', updatePrice);
colorSelect.addEventListener('change', updatePrice);
shaveSelect.addEventListener('change', updatePrice);
massageSelect.addEventListener('change', updatePrice); // ✅ NEW

updateServiceFields();


            // Modal JS for Add to Queue
            var queueModal = document.getElementById('queueModal');
            var openQueueBtn = document.getElementById('openQueueModal');
            var closeQueueSpan = document.getElementById('closeQueueModal');
            openQueueBtn.onclick = function() {
                // Set the hidden input to the current barber filter value
                var barberFilter = document.getElementById('barberFilter');
                var currentBarberInput = document.getElementById('current_barber_filter');
                if (barberFilter && currentBarberInput) {
                    currentBarberInput.value = barberFilter.value;
                }
                queueModal.style.display = 'flex';
            }
            closeQueueSpan.onclick = function() {
                queueModal.style.display = 'none';
            }
            window.onclick = function(event) {
                if (event.target == queueModal) {
                    queueModal.style.display = 'none';
                }
            }

            // Edit modal logic
            var editModal = document.getElementById('editModal');
            var closeEditModal = document.getElementById('closeEditModal');
            var editForm = document.getElementById('editForm');
            var editServiceHaircut = document.getElementById('edit_service_haircut');
            var editServiceColoring = document.getElementById('edit_service_coloring');
            var editServiceShave = document.getElementById('edit_service_shave');
            var editServiceMassage = document.getElementById('edit_service_massage');
            var editHaircutSelect = document.getElementById('edit_haircut');
            var editColorSelect = document.getElementById('edit_color');
            var editShaveSelect = document.getElementById('edit_shave');
            var editMassageSelect = document.getElementById('edit_massage');
            var editPriceInput = document.getElementById('edit_price');

            function updateEditServiceFields() {
                var editHaircutLabel = document.getElementById('edit_haircutLabel');
                var editColorLabel = document.getElementById('edit_colorLabel');
                var editShaveLabel = document.getElementById('edit_shaveLabel');
                var editMassageLabel = document.getElementById('edit_massageLabel');
                // Haircut
                if (editServiceHaircut.checked) {
                    editHaircutLabel.style.display = 'block';
                    editHaircutSelect.style.display = 'block';
                } else {
                    editHaircutLabel.style.display = 'none';
                    editHaircutSelect.style.display = 'none';
                    editHaircutSelect.selectedIndex = 0;
                }
                // Coloring
                if (editServiceColoring.checked) {
                    editColorLabel.style.display = 'block';
                    editColorSelect.style.display = 'block';
                } else {
                    editColorLabel.style.display = 'none';
                    editColorSelect.style.display = 'none';
                    editColorSelect.selectedIndex = 0;
                }
                // Shave
                if (editServiceShave.checked) {
                    editShaveLabel.style.display = 'block';
                    editShaveSelect.style.display = 'block';
                } else {
                    editShaveLabel.style.display = 'none';
                    editShaveSelect.style.display = 'none';
                    editShaveSelect.selectedIndex = 0;
                }
                // Massage
                if (editServiceMassage.checked) {
                    editMassageLabel.style.display = 'block';
                    editMassageSelect.style.display = 'block';
                } else {
                    editMassageLabel.style.display = 'none';
                    editMassageSelect.style.display = 'none';
                    editMassageSelect.selectedIndex = 0;
                }
                updateEditPrice();
            }

            function updateEditPrice() {
                var haircutPrice = 0;
                var colorPrice = 0;
                var shavePrice = 0;
                var massagePrice = 0;

                var selectedHaircut = editHaircutSelect.options[editHaircutSelect.selectedIndex];
                var selectedColor = editColorSelect.options[editColorSelect.selectedIndex];
                var selectedShave = editShaveSelect.options[editShaveSelect.selectedIndex];
                var selectedMassage = editMassageSelect.options[editMassageSelect.selectedIndex];

                if (editServiceHaircut.checked && editHaircutSelect.value && selectedHaircut.getAttribute('data-price')) {
                    haircutPrice = parseFloat(selectedHaircut.getAttribute('data-price')) || 0;
                }
                if (editServiceColoring.checked && editColorSelect.value && selectedColor.getAttribute('data-price')) {
                    colorPrice = parseFloat(selectedColor.getAttribute('data-price')) || 0;
                }
                if (editServiceShave.checked && editShaveSelect.value && selectedShave.getAttribute('data-price')) {
                    shavePrice = parseFloat(selectedShave.getAttribute('data-price')) || 0;
                }
                if (editServiceMassage.checked && editMassageSelect.value && selectedMassage.getAttribute('data-price')) {
                    massagePrice = parseFloat(selectedMassage.getAttribute('data-price')) || 0;
                }
                var total = haircutPrice + colorPrice + shavePrice + massagePrice;
                editPriceInput.value = total > 0 ? total.toFixed(2) : '';
            }

            editServiceHaircut.addEventListener('change', updateEditServiceFields);
    editServiceColoring.addEventListener('change', updateEditServiceFields);
    editServiceShave.addEventListener('change', updateEditServiceFields);
    editServiceMassage.addEventListener('change', updateEditServiceFields);

    editHaircutSelect.addEventListener('change', updateEditPrice);
    editColorSelect.addEventListener('change', updateEditPrice);
    editShaveSelect.addEventListener('change', updateEditPrice);
    editMassageSelect.addEventListener('change', updateEditPrice);

    document.querySelectorAll('.edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_name').value = btn.getAttribute('data-name');

            // Set checkboxes and dropdowns based on individual service columns
            var haircutVal = btn.getAttribute('data-haircut');
            var colorVal = btn.getAttribute('data-color');
            var shaveVal = btn.getAttribute('data-shave');
            var massageVal = btn.getAttribute('data-massage');

            // Haircut
            if (haircutVal && haircutVal !== 'N/A') {
                editServiceHaircut.checked = true;
                editHaircutSelect.value = haircutVal;
            } else {
                editServiceHaircut.checked = false;
                editHaircutSelect.value = '';
            }
            // Coloring
            if (colorVal && colorVal !== 'N/A') {
                editServiceColoring.checked = true;
                editColorSelect.value = colorVal;
            } else {
                editServiceColoring.checked = false;
                editColorSelect.value = '';
            }
            // Shave
            if (shaveVal && shaveVal !== 'N/A') {
                editServiceShave.checked = true;
                editShaveSelect.value = shaveVal;
            } else {
                editServiceShave.checked = false;
                editShaveSelect.value = '';
            }
            // Massage
            if (massageVal && massageVal !== 'N/A') {
                editServiceMassage.checked = true;
                editMassageSelect.value = massageVal;
            } else {
                editServiceMassage.checked = false;
                editMassageSelect.value = '';
            }

            updateEditServiceFields();

            document.getElementById('edit_barber').value = btn.getAttribute('data-barber');
            editPriceInput.value = btn.getAttribute('data-price');
            editModal.style.display = 'flex';
        });
    });
    closeEditModal.onclick = function() {
        editModal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == editModal) {
            editModal.style.display = 'none';
        }
    }
    // Initialize edit modal fields hidden
    updateEditServiceFields();
});
    </script>
</body>
</html>
<?php $conn->close(); ?>
